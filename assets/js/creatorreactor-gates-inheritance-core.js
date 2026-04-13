/* global document, window */

(function (global) {
	'use strict';

	/**
	 * @typedef {Object} CreatorReactorGatesInheritanceConfig
	 * @property {string} hiddenClass
	 * @property {string} prehideClass
	 * @property {string} markerSelector
	 * @property {'elementor'|'gutenberg'} mode
	 * @property {string} styleDataAttr  Value for style[data-*] so Elementor vs Gutenberg do not collide.
	 * @property {Record<string, string>} [widgetNameToGate] Elementor widget name → gate slug.
	 * @property {string} logLabel Console / debug label prefix.
	 * @property {string} debugGlobal window property name; when true, enables debug logs.
	 */

	/**
	 * @param {CreatorReactorGatesInheritanceConfig} cfg
	 */
	function create(cfg) {
		var HIDDEN_CLASS = cfg.hiddenClass;
		var PREHIDE_CLASS = cfg.prehideClass;
		var MARKER_SELECTOR = cfg.markerSelector;
		var MODE = cfg.mode;
		var STYLE_FLAG = cfg.styleDataAttr;
		var WIDGET_NAME_TO_GATE = cfg.widgetNameToGate && typeof cfg.widgetNameToGate === 'object' ? cfg.widgetNameToGate : {};
		var LOG_LABEL = cfg.logLabel || 'gates inheritance';
		var DEBUG = global[cfg.debugGlobal] === true;
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

		var viewerState = viewerStateFromBootstrap(global.CreatorReactorViewerState);

		function ensureHideCss() {
			if (document.querySelector('style[data-creatorreactor-gate-hide-css="' + STYLE_FLAG + '"]')) {
				return;
			}

			var style = document.createElement('style');
			style.setAttribute('data-creatorreactor-gate-hide-css', STYLE_FLAG);
			style.textContent = ''
				+ '.' + HIDDEN_CLASS + '{display:none !important;}'
				+ '.' + PREHIDE_CLASS + '{visibility:hidden !important;pointer-events:none !important;}';
			document.head.appendChild(style);
		}

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

		function findPreferredGutenbergContainer(marker) {
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

		function gateSlugFromElement(el) {
			var direct = el.getAttribute('data-creatorreactor-gate');
			if (direct) {
				return direct;
			}
			var cls = el.getAttribute('class') || '';
			var m = cls.match(/elementor-widget-(creatorreactor_[a-z0-9_]+)\b/);
			if (!m) {
				return null;
			}
			var wname = m[1];
			if (wname === 'creatorreactor_fanvue_oauth') {
				return null;
			}
			return WIDGET_NAME_TO_GATE[wname] || null;
		}

		function resolveGateSlug(marker) {
			if (MODE === 'elementor') {
				return gateSlugFromElement(marker);
			}
			return marker.getAttribute('data-creatorreactor-gate');
		}

		function findPreferredContainer(marker) {
			return MODE === 'elementor'
				? findPreferredElementorContainer(marker)
				: findPreferredGutenbergContainer(marker);
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
			var gate = resolveGateSlug(marker);
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

			if (MODE === 'gutenberg') {
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

			// elementor
			if (!isLoggedIn) {
				if (gate === 'logged_out') {
					return '1';
				}
				return '0';
			}

			if (gate === 'logged_out') {
				return '0';
			}
			if (gate === 'logged_in') {
				return '1';
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

			if (match === '1' || match === '0') {
				return match;
			}
			if (gate === 'fanvue_connected' || gate === 'fanvue_not_connected' || gate === 'has_tier') {
				return 'pending';
			}
			return '0';
		}

		function refreshViewerState() {
			var ajaxUrl = (global.ajaxurl && typeof global.ajaxurl === 'string')
				? global.ajaxurl
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
			var hasGutenberg = !!document.querySelector('.creatorreactor-gutenberg-gate-marker[data-creatorreactor-gate-match]');
			var hasElementor = !!document.querySelector('.creatorreactor-elementor-gate-marker[data-creatorreactor-gate-match]');
			if (!document.querySelector(MARKER_SELECTOR) && !(MODE === 'elementor' ? hasGutenberg : hasElementor)) {
				document.body.classList.add('creatorreactor-gates-ready');
			}
		}

		function scanAndHide() {
			var markers = Array.prototype.slice.call(document.querySelectorAll(MARKER_SELECTOR));
			if (!markers.length) {
				return;
			}

			var containersToReset = [];
			markers.forEach(function (marker) {
				var c = findPreferredContainer(marker);
				if (!c || containersToReset.indexOf(c) !== -1) {
					return;
				}
				containersToReset.push(c);
			});
			containersToReset.forEach(function (el) {
				el.classList.remove(HIDDEN_CLASS);
				el.classList.remove(PREHIDE_CLASS);
			});

			var hiddenCount = 0;
			markers.forEach(function (marker) {
				var m = resolveEffectiveMatch(marker);
				var shouldHide = m === '0';
				var shouldPrehide = m === 'pending';
				var container = findPreferredContainer(marker);
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
				if (now - lastDebugLogMs > 1000) {
					lastDebugLogMs = now;
					// eslint-disable-next-line no-console
					console.log('[CreatorReactor] ' + LOG_LABEL + ':', {
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

		function mainElementor() {
			var scheduled = false;
			var scheduleScan = function () {
				if (scheduled) {
					return;
				}
				scheduled = true;
				setTimeout(function () {
					scheduled = false;
					scanAndHide();
				}, 0);
			};

			if (!('MutationObserver' in global)) {
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
				scanAndHide();
				observer.observe(document.body, { childList: true, subtree: true });

				var attempts = 0;
				var maxAttempts = 4;
				var tailRescan = function () {
					attempts += 1;
					if (attempts >= maxAttempts) {
						return;
					}
					setTimeout(function () {
						scanAndHide();
						tailRescan();
					}, 120);
				};
				setTimeout(tailRescan, 120);
			};
			startObserver();
		}

		function mainGutenberg() {
			scanAndHide();

			var markers = Array.prototype.slice.call(document.querySelectorAll(MARKER_SELECTOR));
			if (!markers.length) {
				return;
			}

			var scheduled = false;
			var scheduleScan = function () {
				if (scheduled) {
					return;
				}
				scheduled = true;
				setTimeout(function () {
					scheduled = false;
					scanAndHide();
				}, 0);
			};

			if (!('MutationObserver' in global)) {
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

		function boot() {
			ensureHideCss();
			hydrateViewerStateFromMarkers();
			refreshViewerState();
			global.addEventListener('load', sealNoGatePageWhenStillEmpty);
			if (MODE === 'elementor') {
				mainElementor();
			} else {
				scanAndHide();
				mainGutenberg();
			}
		}

		return { boot: boot };
	}

	global.CreatorReactorGatesInheritanceCore = { create: create };
}(typeof window !== 'undefined' ? window : this));
