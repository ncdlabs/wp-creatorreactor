/* global window, document */
(function () {
	'use strict';

	var HIDDEN_CLASS = 'creatorreactor-elementor-gate-hidden';
	var INVALID_CLASS = 'creatorreactor-elementor-gate-invalid-multi';
	var BADGE_CLASS = 'creatorreactor-elementor-gate-invalid-badge';
	var MARKER_SELECTOR = '.creatorreactor-elementor-gate-marker[data-creatorreactor-gate-match]';
	var DEBUG = window.CreatorReactorElementorGateEditorConstraintDebug === true;
	var lastDebugLogMs = 0;

	var warningText = ( window.CreatorReactorElementorGateEditorConstraint
		&& window.CreatorReactorElementorGateEditorConstraint.warningText ) ? window.CreatorReactorElementorGateEditorConstraint.warningText : '';
	if ( ! warningText ) {
		warningText = 'Only one CreatorReactor content gate widget per container is allowed.';
	}

	function ensureHideCss() {
		if (document.querySelector('style[data-creatorreactor-elementor-gate-hidden="1"]')) {
			return;
		}

		var style = document.createElement('style');
		style.setAttribute('data-creatorreactor-elementor-gate-hidden', '1');
		style.textContent = '.' + HIDDEN_CLASS + '{display:none !important;}';
		document.head.appendChild(style);
	}

	function ensureEditorConstraintCss() {
		if (document.querySelector('style[data-creatorreactor-elementor-gate-editor-constraint="1"]')) {
			return;
		}

		var style = document.createElement('style');
		style.setAttribute('data-creatorreactor-elementor-gate-editor-constraint', '1');
		style.textContent = [
			'.' + INVALID_CLASS + '{outline:3px solid #f59e0b !important; outline-offset:-3px;}',
			'.' + INVALID_CLASS + ' > .' + BADGE_CLASS + '{position:absolute; top:8px; left:8px; z-index:999999; background:#f59e0b; color:#111827; font-weight:800; font-size:12px; padding:4px 8px; border-radius:6px; box-shadow:0 6px 20px rgba(17,24,39,.18);}',
			'.' + INVALID_CLASS + '{position:relative;}'
		].join('');
		document.head.appendChild(style);
	}

	function findPreferredElementorContainer(marker) {
		var widget = marker.closest('.elementor-element.elementor-widget');
		var container = null;

		if (widget) {
			container = widget.closest('.elementor-element.e-con')
				|| widget.closest('.elementor-column')
				|| widget.closest('.elementor-section');
			if (container) {
				return container;
			}
		}

		// Fallbacks for unexpected markup.
		container = marker.closest('.elementor-element.e-con')
			|| marker.closest('.elementor-column')
			|| marker.closest('.elementor-section')
			|| marker.closest('.e-con-boxed')
			|| marker.closest('.e-con-inner')
			|| marker.closest('.e-con');
		return container || null;
	}

	function clearInvalidBadgesAndClasses() {
		Array.prototype.slice
			.call(document.querySelectorAll('.' + INVALID_CLASS))
			.forEach(function (el) { el.classList.remove(INVALID_CLASS); });

		Array.prototype.slice
			.call(document.querySelectorAll('.' + BADGE_CLASS))
			.forEach(function (el) { el.remove(); });
	}

	function scanAndEnforce() {
		// Clear prior results for dynamic re-renders.
		Array.prototype.slice
			.call(document.querySelectorAll('.' + HIDDEN_CLASS))
			.forEach(function (el) { el.classList.remove(HIDDEN_CLASS); });

		clearInvalidBadgesAndClasses();

		var markers = Array.prototype.slice.call(document.querySelectorAll(MARKER_SELECTOR));
		if (!markers.length) {
			return;
		}

		var containerToMarkers = new Map();
		markers.forEach(function (marker) {
			var container = findPreferredElementorContainer(marker);
			if (!container) {
				return;
			}

			if (!containerToMarkers.has(container)) {
				containerToMarkers.set(container, []);
			}
			containerToMarkers.get(container).push(marker);
		});

		containerToMarkers.forEach(function (containerMarkers, container) {
			if (containerMarkers.length > 1) {
				container.classList.add(INVALID_CLASS);
				// Outline + badge only — do not display:none the container or nested gates vanish in the canvas.

				// Add badge only once per scan.
				if (!container.querySelector('.' + BADGE_CLASS)) {
					var badge = document.createElement('div');
					badge.className = BADGE_CLASS;
					badge.textContent = warningText;
					container.appendChild(badge);
				}
				return;
			}

			container.classList.remove(HIDDEN_CLASS);
		});

		if (DEBUG) {
			var now = Date.now();
			if (now - lastDebugLogMs > 1000) {
				lastDebugLogMs = now;
				// eslint-disable-next-line no-console
				console.log('[CreatorReactor] editor gate constraint scan:', {
					markers: markers.length,
					containersChecked: containerToMarkers.size
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
				scanAndEnforce();
			}, 50);
		};

		if (!('MutationObserver' in window)) {
			scanAndEnforce();
			return;
		}

		var observer = new MutationObserver(function () {
			scheduleScan();
		});
		observer.observe(document.body, { childList: true, subtree: true });

		// Retry scans shortly after load so we catch Elementor's first re-render.
		var attempts = 0;
		var maxAttempts = 10;
		var attemptScan = function () {
			attempts += 1;
			scanAndEnforce();
			if (attempts < maxAttempts) {
				setTimeout(attemptScan, 60);
			}
		};
		attemptScan();
	}

	ensureHideCss();
	ensureEditorConstraintCss();
	main();
})();

