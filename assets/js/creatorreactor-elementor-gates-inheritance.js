/* global document */

(function () {
	'use strict';

	var HIDDEN_CLASS = 'creatorreactor-elementor-gate-hidden';
	var MARKER_SELECTOR = '.creatorreactor-elementor-gate-marker[data-creatorreactor-gate-match]';
	var DEBUG = window.CreatorReactorElementorGatesInheritanceDebug === true;
	var lastDebugLogMs = 0;
	var viewerState = (window.CreatorReactorViewerState && typeof window.CreatorReactorViewerState === 'object')
		? {
			loggedIn: !!window.CreatorReactorViewerState.loggedIn,
			roles: Array.isArray(window.CreatorReactorViewerState.roles) ? window.CreatorReactorViewerState.roles : []
		}
		: null;

	function ensureHideCss() {
		if (document.querySelector('style[data-creatorreactor-elementor-gate-hidden="1"]')) {
			return;
		}

		var style = document.createElement('style');
		style.setAttribute('data-creatorreactor-elementor-gate-hidden', '1');
		style.textContent = '.' + HIDDEN_CLASS + '{display:none !important;}';
		document.head.appendChild(style);
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
		// Keep state consistent for dynamic re-renders by clearing previous results.
		Array.prototype.slice
			.call(document.querySelectorAll('.' + HIDDEN_CLASS))
			.forEach(function (el) {
				el.classList.remove(HIDDEN_CLASS);
			});

		var markers = Array.prototype.slice.call(document.querySelectorAll(MARKER_SELECTOR));
		if (!markers.length) {
			return;
		}

		// Security-first behavior: only gate-wrapped content controls output.
		// Do not infer/hide neighboring widgets, because sibling widgets may contain
		// protected URLs that should never be emitted in HTML for unauthorized viewers.
		// Gate markup itself is already rendered server-side by shortcode access checks.
		markers.forEach(function () {});

		if (DEBUG) {
			var now = Date.now();
			if ( now - lastDebugLogMs > 1000 ) {
				lastDebugLogMs = now;
				// eslint-disable-next-line no-console
				console.log('[CreatorReactor] gate inheritance scan:', {
					markers: markers.length
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

	function runInitialScan() {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', scanAndHide);
		} else {
			scanAndHide();
		}
	}

	function main() {
		// Elementor may render or re-render widgets after `DOMContentLoaded`.
		// A MutationObserver makes this resilient in editor/preview and for dynamic pages.
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

		var observer = new MutationObserver(function (mutations) {
			// If a gate marker appears/disappears, re-scan.
			for (var i = 0; i < mutations.length; i++) {
				var m = mutations[i];
				if (m.addedNodes && m.addedNodes.length) {
					for (var j = 0; j < m.addedNodes.length; j++) {
						var node = m.addedNodes[j];
						if (node && node.nodeType === 1 && node.matches && node.matches(MARKER_SELECTOR)) {
							scheduleScan();
							return;
						}
						if (node && node.nodeType === 1 && node.querySelector && node.querySelector(MARKER_SELECTOR)) {
							scheduleScan();
							return;
						}
					}
				}
			}
		});

		var startObserver = function () {
			if (!document.body) {
				setTimeout(startObserver, 30);
				return;
			}
			observer.observe(document.body, { childList: true, subtree: true });

			// Retry scans shortly after load so we catch Elementor's first re-render.
			var attempts = 0;
			var maxAttempts = 10;
			var attemptScan = function () {
				attempts += 1;
				scanAndHide();
				if (attempts < maxAttempts) {
					setTimeout(attemptScan, 60);
				}
			};
			attemptScan();
		};
		startObserver();
	}

	// Ensure the hide CSS exists ASAP so we don't briefly show gated containers
	// before our first scan runs.
	ensureHideCss();
	refreshViewerState();
	runInitialScan();
	// Set up MutationObserver immediately. Elementor can render/re-render widgets after
	// `DOMContentLoaded`, and waiting to attach observers can miss those updates.
	main();
})();
