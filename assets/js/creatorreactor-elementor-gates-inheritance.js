/* global document */

(function () {
	'use strict';

	var HIDDEN_CLASS = 'creatorreactor-elementor-gate-hidden';
	var MARKER_SELECTOR = '.creatorreactor-elementor-gate-marker[data-creatorreactor-gate-match]';
	var DEBUG = window.CreatorReactorElementorGatesInheritanceDebug === true;

	function ensureHideCss() {
		if (document.querySelector('style[data-creatorreactor-elementor-gate-hidden="1"]')) {
			return;
		}

		var style = document.createElement('style');
		style.setAttribute('data-creatorreactor-elementor-gate-hidden', '1');
		style.textContent = '.' + HIDDEN_CLASS + '{display:none !important;}';
		document.head.appendChild(style);
	}

	function findPreferredElementorContainer(marker) {
		// Prefer container types that Elementor uses to group widgets so siblings inherit visibility.
		// Avoid `.e-con-inner` because it may be closer than `.e-con` and could hide less than expected.
		var el = marker;
		while (el && el !== document.body) {
			if (el !== marker && el.matches && (el.matches('.e-con') || el.matches('.e-con-boxed'))) {
				return el;
			}
			if (el !== marker && el.matches && el.matches('.elementor-container')) {
				return el;
			}
			if (el !== marker && el.matches && el.matches('.elementor-section')) {
				return el;
			}
			el = el.parentElement;
		}
		return null;
	}

	function scanAndHide() {
		// Keep state consistent for dynamic re-renders by clearing previous results.
		Array.prototype.slice
			.call(document.querySelectorAll('.' + HIDDEN_CLASS))
			.forEach(function (el) { el.classList.remove(HIDDEN_CLASS); });

		var markers = Array.prototype.slice.call(document.querySelectorAll(MARKER_SELECTOR));
		if (!markers.length) {
			return;
		}

		// Semantics are determined per container based on any marker's chosen logic:
		// - default 'and' (current): hide container if ANY gate fails => hide if !allMatch
		// - 'or': show container if ANY gate passes => hide if !anyMatch
		//
		// If a container has a mixture of markers, we let 'or' win (less restrictive).
		var containerState = new Map(); // container -> { allMatch: boolean, anyMatch: boolean, mode: 'and'|'or' }

		markers.forEach(function (marker) {
			var match = marker.getAttribute('data-creatorreactor-gate-match');
			var shouldMatch = match === '1';
			var logic = marker.getAttribute('data-creatorreactor-gate-logic');
			var mode = logic === 'or' ? 'or' : 'and';

			var container = findPreferredElementorContainer(marker);
			if (!container) {
				return;
			}

			if (!containerState.has(container)) {
				containerState.set(container, { allMatch: true, anyMatch: false, mode: 'and' });
			}

			var state = containerState.get(container);
			state.allMatch = state.allMatch && shouldMatch;
			state.anyMatch = state.anyMatch || shouldMatch;
			if (mode === 'or') {
				state.mode = 'or';
			}
		});

		var hiddenCount = 0;
		containerState.forEach(function (state, container) {
			var shouldHide = state.mode === 'or' ? !state.anyMatch : !state.allMatch;
			if (shouldHide) {
				container.classList.add(HIDDEN_CLASS);
				hiddenCount += 1;
			} else {
				container.classList.remove(HIDDEN_CLASS);
			}
		});

		if (DEBUG) {
			// eslint-disable-next-line no-console
			console.log('[CreatorReactor] gate inheritance scan:', {
				markers: markers.length,
				containersHidden: hiddenCount
			});
		}
	}

	function main() {
		ensureHideCss();

		// Initial scan.
		scanAndHide();

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

		observer.observe(document.body, { childList: true, subtree: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', main, { passive: true });
	} else {
		main();
	}
})();

