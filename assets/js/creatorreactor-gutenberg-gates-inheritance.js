/* global document */

(function () {
	'use strict';

	var HIDDEN_CLASS = 'creatorreactor-gutenberg-gate-hidden';
	var MARKER_SELECTOR = '.creatorreactor-gutenberg-gate-marker[data-creatorreactor-gate-match]';
	var DEBUG = window.CreatorReactorGutenbergGatesInheritanceDebug === true;
	var lastDebugLogMs = 0;
	var viewerState = null;

	function ensureHideCss() {
		if (document.querySelector('style[data-creatorreactor-gutenberg-gate-hidden="1"]')) {
			return;
		}

		var style = document.createElement('style');
		style.setAttribute('data-creatorreactor-gutenberg-gate-hidden', '1');
		style.textContent = '.' + HIDDEN_CLASS + '{display:none !important;}';
		document.head.appendChild(style);
	}

	function findPreferredGutenbergContainer(marker) {
		// The closest wrapper is typically enough to make siblings “inherit” visibility.
		// Prefer hiding the smaller column, then group, then entire columns block.
		var el = marker;
		while (el && el !== document.body) {
			if (el !== marker && el.matches && el.matches('.wp-block-column')) {
				return el;
			}
			if (el !== marker && el.matches && el.matches('.wp-block-group')) {
				return el;
			}
			if (el !== marker && el.matches && el.matches('.wp-block-columns')) {
				return el;
			}
			el = el.parentElement;
		}
		return null;
	}

	function resolveEffectiveMatch(marker) {
		var match = marker.getAttribute('data-creatorreactor-gate-match');
		var gate = marker.getAttribute('data-creatorreactor-gate');
		var isLoggedIn = !!(document.body && document.body.classList && document.body.classList.contains('logged-in'));
		var rolesAttr = marker.getAttribute('data-creatorreactor-user-roles') || '';
		var roles = rolesAttr.split(',').map(function (r) { return r.trim(); }).filter(Boolean);
		var hasFollowerRole = roles.indexOf('creatorreactor_follower') !== -1;
		var hasSubscriberRole = roles.indexOf('creatorreactor_subscriber') !== -1;

		if (viewerState && typeof viewerState === 'object') {
			isLoggedIn = !!viewerState.loggedIn;
			roles = Array.isArray(viewerState.roles) ? viewerState.roles : [];
			hasFollowerRole = roles.indexOf('creatorreactor_follower') !== -1;
			hasSubscriberRole = roles.indexOf('creatorreactor_subscriber') !== -1;
		}

		// Role-driven gates must be derived from role payload, not stale match markers.
		if (gate === 'subscriber') {
			return hasSubscriberRole ? '1' : '0';
		}
		if (gate === 'follower') {
			return hasFollowerRole && !hasSubscriberRole ? '1' : '0';
		}

		// Cache-safe fallback for guests: never trust stale "match=1" for authenticated gates.
		if (!isLoggedIn) {
			if (gate === 'logged_out') {
				return '1';
			}
			if (
				gate === 'subscriber' ||
				gate === 'follower' ||
				gate === 'logged_in' ||
				gate === 'logged_in_no_role' ||
				gate === 'has_tier' ||
				gate === 'fanvue_connected' ||
				gate === 'fanvue_not_connected' ||
				gate === 'onboarding_incomplete' ||
				gate === 'onboarding_complete'
			) {
				return '0';
			}
		}

		return match;
	}

	function refreshViewerState() {
		var ajaxUrl = (window.ajaxurl && typeof window.ajaxurl === 'string')
			? window.ajaxurl
			: '/wp-admin/admin-ajax.php';
		var url = ajaxUrl + (ajaxUrl.indexOf('?') === -1 ? '?' : '&') + 'action=creatorreactor_viewer_state';
		fetch(url, { credentials: 'same-origin', cache: 'no-store' })
			.then(function (resp) { return resp.ok ? resp.json() : null; })
			.then(function (json) {
				if (!json || json.success !== true || !json.data) {
					return;
				}
				viewerState = {
					loggedIn: !!json.data.logged_in,
					roles: Array.isArray(json.data.roles) ? json.data.roles : []
				};
				scanAndHide();
			})
			.catch(function () {
				// Keep marker-based fallback when endpoint is temporarily unavailable.
			});
	}

	function scanAndHide() {
		Array.prototype.slice
			.call(document.querySelectorAll('.' + HIDDEN_CLASS))
			.forEach(function (el) { el.classList.remove(HIDDEN_CLASS); });

		var markers = Array.prototype.slice.call(document.querySelectorAll(MARKER_SELECTOR));
		if (!markers.length) {
			return;
		}

		// Deterministic per-container behavior (your "1 gate rule per container" setup):
		// toggle each nearest block container directly based on this marker's match value.
		var hiddenCount = 0;
		markers.forEach(function (marker) {
			var match = resolveEffectiveMatch(marker);
			var shouldHide = match !== '1';
			var container = findPreferredGutenbergContainer(marker);
			if (!container) {
				return;
			}
			if (shouldHide) {
				container.classList.add(HIDDEN_CLASS);
				hiddenCount += 1;
			} else {
				container.classList.remove(HIDDEN_CLASS);
			}
		});

		if (DEBUG) {
			var now = Date.now();
			if ( now - lastDebugLogMs > 1000 ) {
				lastDebugLogMs = now;
				// eslint-disable-next-line no-console
				console.log('[CreatorReactor] gutenberg gate inheritance scan:', {
					markers: markers.length,
					containersHidden: hiddenCount
				});

				markers.slice(0, 10).forEach(function (marker) {
					// eslint-disable-next-line no-console
					console.log('[CreatorReactor] gate marker:', {
						gate: marker.getAttribute('data-creatorreactor-gate'),
						match: marker.getAttribute('data-creatorreactor-gate-match'),
						logic: marker.getAttribute('data-creatorreactor-gate-logic'),
						roles: marker.getAttribute('data-creatorreactor-user-roles')
					});
				});
			}
		}
	}

	function main() {
		scanAndHide();

		var markers = Array.prototype.slice.call(document.querySelectorAll(MARKER_SELECTOR));
		if (!markers.length) {
			return;
		}

		// Elementor-style robustness for dynamic DOM updates.
		var scheduled = false;
		var scheduleScan = function () {
			if (scheduled) {
				return;
			}
			scheduled = true;
			setTimeout(function () {
				scheduled = false;
				scanAndHide();
			}, 50);
		};

		if (!('MutationObserver' in window)) {
			return;
		}

		var observer = new MutationObserver(function () {
			scheduleScan();
		});
		var startObserver = function () {
			if (!document.body) {
				setTimeout(startObserver, 30);
				return;
			}
			observer.observe(document.body, { childList: true, subtree: true });
		};
		startObserver();
	}

	// Ensure the hide CSS exists ASAP so we don't briefly show gated containers
	// before our first scan runs.
	ensureHideCss();
	refreshViewerState();

	// Best-effort scan immediately.
	scanAndHide();

	// Set up MutationObserver immediately (do not wait for `DOMContentLoaded`).
	main();
})();

