/* global document */

(function () {
	'use strict';

	var HIDDEN_CLASS = 'creatorreactor-gutenberg-gate-hidden';
	var MARKER_SELECTOR = '.creatorreactor-gutenberg-gate-marker[data-creatorreactor-gate-match]';
	var DEBUG = window.CreatorReactorGutenbergGatesInheritanceDebug === true;

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

	function scanAndHide() {
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
		var containerState = new Map(); // container -> { allMatch, anyMatch, mode }

		markers.forEach(function (marker) {
			var match = marker.getAttribute('data-creatorreactor-gate-match');
			var shouldMatch = match === '1';
			var logic = marker.getAttribute('data-creatorreactor-gate-logic');
			var mode = logic === 'or' ? 'or' : 'and';

			var container = findPreferredGutenbergContainer(marker);
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
			console.log('[CreatorReactor] gutenberg gate inheritance scan:', {
				markers: markers.length,
				containersHidden: hiddenCount
			});
		}
	}

	function main() {
		ensureHideCss();

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
		observer.observe(document.body, { childList: true, subtree: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', main, { passive: true });
	} else {
		main();
	}
})();

