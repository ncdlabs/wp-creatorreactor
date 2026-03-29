/* global document */

(function () {
	'use strict';

	var HIDDEN_CLASS = 'creatorreactor-elementor-gate-hidden';
	var PREHIDE_CLASS = 'creatorreactor-elementor-gate-prehide';
	var MARKER_SELECTOR = '.creatorreactor-elementor-gate-marker[data-creatorreactor-gate-match]';
	var DEBUG = window.CreatorReactorElementorGatesInheritanceDebug === true;
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
		if (document.querySelector('style[data-creatorreactor-elementor-gate-hidden="1"]')) {
			return;
		}

		var style = document.createElement('style');
		style.setAttribute('data-creatorreactor-elementor-gate-hidden', '1');
		style.textContent = ''
			+ '.' + HIDDEN_CLASS + '{display:none !important;}'
			+ '.' + PREHIDE_CLASS + '{visibility:hidden !important;pointer-events:none !important;}';
		document.head.appendChild(style);
	}

	/**
	 * Prefer the flex row that holds sibling widgets (heading, media, etc.), not only
	 * the gate widget — the marker lives inside the gate widget only.
	 */
	function findPreferredElementorContainer(marker) {
		var inner = marker.closest('.e-con-inner');
		if (inner) {
			return inner;
		}
		var legacy = marker.closest('.elementor-container');
		if (legacy) {
			return legacy;
		}
		return marker.closest('.elementor-element');
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

		if (!isLoggedIn) {
			if (gate === 'logged_out') {
				return '1';
			}
			return '0';
		}

		if (!viewerState && (gate === 'subscriber' || gate === 'follower')) {
			return 'pending';
		}

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

		var hiddenCount = 0;
		markers.forEach(function (marker) {
			var match = resolveEffectiveMatch(marker);
			var shouldHide = match === '0';
			var shouldPrehide = match === 'pending';
			var container = findPreferredElementorContainer(marker);
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
				console.log('[CreatorReactor] elementor gate inheritance scan:', {
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

	ensureHideCss();
	hydrateViewerStateFromMarkers();
	refreshViewerState();
	scanAndHide();
	main();
})();
