/* global document */

(function () {
	'use strict';

	var HIDDEN_CLASS = 'creatorreactor-gutenberg-gate-hidden';
	var PREHIDE_CLASS = 'creatorreactor-gutenberg-gate-prehide';
	var MARKER_SELECTOR = '.creatorreactor-gutenberg-gate-marker[data-creatorreactor-gate-match]';
	var DEBUG = window.CreatorReactorGutenbergGatesInheritanceDebug === true;
	var lastDebugLogMs = 0;
	function viewerStateFromBootstrap(obj) {
		if (!obj || typeof obj !== 'object') {
			return null;
		}
		return {
			loggedIn: !!obj.loggedIn,
			roles: Array.isArray(obj.roles) ? obj.roles : [],
			skipClientGateHiding: !!obj.skipClientGateHiding,
			adminBarShowing: !!obj.adminBarShowing,
			canAccessWpAdmin: !!obj.canAccessWpAdmin
		};
	}

	function viewerStateFromAjaxData(data) {
		if (!data || typeof data !== 'object') {
			return null;
		}
		return {
			loggedIn: !!data.logged_in,
			roles: Array.isArray(data.roles) ? data.roles : [],
			skipClientGateHiding: !!data.skip_client_gate_hiding,
			adminBarShowing: !!data.admin_bar_showing,
			canAccessWpAdmin: !!data.can_access_wp_admin
		};
	}

	var viewerState = viewerStateFromBootstrap(window.CreatorReactorViewerState);

	function ensureHideCss() {
		if (document.querySelector('style[data-creatorreactor-gutenberg-gate-hidden="1"]')) {
			return;
		}

		var style = document.createElement('style');
		style.setAttribute('data-creatorreactor-gutenberg-gate-hidden', '1');
		style.textContent = ''
			+ '.' + HIDDEN_CLASS + '{display:none !important;}'
			+ '.' + PREHIDE_CLASS + '{visibility:hidden !important;pointer-events:none !important;}';
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
		return marker.closest('[class*="wp-block-creatorreactor-"]');
	}

	function hydrateViewerStateFromMarkers() {
		if (viewerState && typeof viewerState === 'object') {
			return;
		}
		var markers = Array.prototype.slice.call(document.querySelectorAll(MARKER_SELECTOR));
		if (!markers.length) {
			return;
		}
		for (var i = 0; i < markers.length; i++) {
			var rolesAttr = markers[i].getAttribute('data-creatorreactor-user-roles') || '';
			var roles = rolesAttr.split(',').map(function (r) { return r.trim(); }).filter(Boolean);
			if (roles.length) {
				viewerState = {
					loggedIn: !!(document.body && document.body.classList && document.body.classList.contains('logged-in')),
					roles: roles,
					skipClientGateHiding: false,
					adminBarShowing: false,
					canAccessWpAdmin: false
				};
				return;
			}
		}
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

		if (viewerState && viewerState.skipClientGateHiding) {
			return '1';
		}

		// Cache-safe: when not logged in for gates, never trust SSR match attrs (stale cache can ship match=1).
		if (!isLoggedIn) {
			if (gate === 'logged_out') {
				return '1';
			}
			return '0';
		}

		// For authenticated users, pre-hide role-gated content until live viewer state arrives.
		// This preserves layout space and prevents visible shifts for matching subscribers.
		if (!viewerState && (gate === 'subscriber' || gate === 'follower')) {
			return 'pending';
		}

		// Role-driven gates must be derived from role payload, not stale match markers.
		if (gate === 'subscriber') {
			return hasSubscriberRole ? '1' : '0';
		}
		if (gate === 'follower') {
			return hasFollowerRole && !hasSubscriberRole ? '1' : '0';
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
				viewerState = viewerStateFromAjaxData(json.data);
				scanAndHide();
			})
			.catch(function () {
				// Keep marker-based fallback when endpoint is temporarily unavailable.
			});
	}

	function sealNoGatePageWhenStillEmpty() {
		if (!document.body || document.body.classList.contains('creatorreactor-gates-ready')) {
			return;
		}
		// Must consider Elementor markers too: both scripts load on Elementor sites; otherwise
		// this would unlock FOUC CSS before creatorreactor-elementor-gates-inheritance.js runs.
		var hasElementor = !!document.querySelector('.creatorreactor-elementor-gate-marker[data-creatorreactor-gate-match]');
		if (!document.querySelector(MARKER_SELECTOR) && !hasElementor) {
			document.body.classList.add('creatorreactor-gates-ready');
		}
	}

	function scanAndHide() {
		Array.prototype.slice
			.call(document.querySelectorAll('.' + HIDDEN_CLASS + ',.' + PREHIDE_CLASS))
			.forEach(function (el) {
				el.classList.remove(HIDDEN_CLASS);
				el.classList.remove(PREHIDE_CLASS);
			});

		var markers = Array.prototype.slice.call(document.querySelectorAll(MARKER_SELECTOR));
		if (!markers.length) {
			return;
		}

		// Deterministic per-container behavior (your "1 gate rule per container" setup):
		// toggle each nearest block container directly based on this marker's match value.
		var hiddenCount = 0;
		markers.forEach(function (marker) {
			var match = resolveEffectiveMatch(marker);
			var shouldHide = match === '0';
			var shouldPrehide = match === 'pending';
			var container = findPreferredGutenbergContainer(marker);
			if (!container) {
				return;
			}
			if (shouldHide) {
				container.classList.remove(PREHIDE_CLASS);
				container.classList.add(HIDDEN_CLASS);
				hiddenCount += 1;
			} else if (shouldPrehide) {
				container.classList.remove(HIDDEN_CLASS);
				container.classList.add(PREHIDE_CLASS);
			} else {
				container.classList.remove(PREHIDE_CLASS);
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

		if (document.body) {
			document.body.classList.add('creatorreactor-gates-ready');
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
	hydrateViewerStateFromMarkers();
	refreshViewerState();

	// Best-effort scan immediately.
	scanAndHide();

	window.addEventListener('load', sealNoGatePageWhenStillEmpty);

	// Set up MutationObserver immediately (do not wait for `DOMContentLoaded`).
	main();
})();

