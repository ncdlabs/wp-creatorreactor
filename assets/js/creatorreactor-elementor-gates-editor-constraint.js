/* global window, document */
(function () {
	'use strict';

	var CONTAINER_INVALID_CLASS = 'creatorreactor-elementor-gate-container-invalid';
	var DUPLICATE_WIDGET_CLASS = 'creatorreactor-elementor-gate-widget-duplicate';
	var BADGE_CLASS = 'creatorreactor-elementor-gate-invalid-badge';
	var MSG_CLASS = 'creatorreactor-elementor-gate-validation-msg';
	var GATE_WIDGET_SELECTOR = '.elementor-element.elementor-widget[class*="elementor-widget-creatorreactor_"]:not(.elementor-widget-creatorreactor_fanvue_oauth)';
	var DEBUG = window.CreatorReactorElementorGateEditorConstraintDebug === true;
	var lastDebugLogMs = 0;

	var localized = window.CreatorReactorElementorGateEditorConstraint || {};
	var containerWarningText = typeof localized.containerWarningText === 'string' && localized.containerWarningText
		? localized.containerWarningText
		: 'This container has more than one CreatorReactor gate.';
	var duplicateWidgetText = typeof localized.duplicateWidgetText === 'string' && localized.duplicateWidgetText
		? localized.duplicateWidgetText
		: 'Only one CreatorReactor gate is allowed in this container. Remove or move this widget.';

	function ensureEditorConstraintCss() {
		if (document.querySelector('style[data-creatorreactor-elementor-gate-editor-constraint="1"]')) {
			return;
		}

		var style = document.createElement('style');
		style.setAttribute('data-creatorreactor-elementor-gate-editor-constraint', '1');
		style.textContent = [
			'.' + CONTAINER_INVALID_CLASS + '{position:relative; outline:2px solid #f59e0b !important; outline-offset:-2px;}',
			'.' + BADGE_CLASS + '{position:absolute; top:6px; left:6px; right:6px; z-index:999999; background:#b45309; color:#fff; font-weight:700; font-size:11px; line-height:1.35; padding:6px 8px; border-radius:4px; box-shadow:0 4px 14px rgba(0,0,0,.2);}',
			'.' + DUPLICATE_WIDGET_CLASS + '{position:relative; outline:3px solid #dc2626 !important; outline-offset:-3px; background:rgba(220,38,38,.08) !important;}',
			'.' + MSG_CLASS + '{position:absolute; bottom:0; left:0; right:0; z-index:999998; background:#991b1b; color:#fff; font-size:11px; line-height:1.35; padding:6px 8px; font-weight:600;}',
			'.' + DUPLICATE_WIDGET_CLASS + ' > .elementor-widget-container{padding-bottom:36px !important;}'
		].join('');
		document.head.appendChild(style);
	}

	function findGateContainerForWidget(widget) {
		if (!widget || !widget.closest) {
			return null;
		}
		var container = widget.closest('.elementor-element.e-con')
			|| widget.closest('.elementor-column')
			|| widget.closest('.elementor-section');
		if (container) {
			return container;
		}
		return widget.closest('.e-con-inner')
			|| widget.closest('.e-con')
			|| null;
	}

	function sortWidgetsDocumentOrder(widgets) {
		return widgets.slice().sort(function (a, b) {
			var r = a.compareDocumentPosition(b);
			if (r & Node.DOCUMENT_POSITION_FOLLOWING) {
				return -1;
			}
			if (r & Node.DOCUMENT_POSITION_PRECEDING) {
				return 1;
			}
			return 0;
		});
	}

	function clearConstraintUi() {
		Array.prototype.slice
			.call(document.querySelectorAll('.' + CONTAINER_INVALID_CLASS))
			.forEach(function (el) {
				el.classList.remove(CONTAINER_INVALID_CLASS);
			});
		Array.prototype.slice
			.call(document.querySelectorAll('.' + DUPLICATE_WIDGET_CLASS))
			.forEach(function (el) {
				el.classList.remove(DUPLICATE_WIDGET_CLASS);
			});
		Array.prototype.slice
			.call(document.querySelectorAll('.' + BADGE_CLASS))
			.forEach(function (el) {
				el.remove();
			});
		Array.prototype.slice
			.call(document.querySelectorAll('.' + MSG_CLASS))
			.forEach(function (el) {
				el.remove();
			});
	}

	function ensureDuplicateMessage(widget) {
		if (widget.querySelector('.' + MSG_CLASS)) {
			return;
		}
		var msg = document.createElement('div');
		msg.className = MSG_CLASS;
		msg.setAttribute('role', 'alert');
		msg.textContent = duplicateWidgetText;
		widget.appendChild(msg);
	}

	function scanAndEnforce() {
		clearConstraintUi();

		var widgets = Array.prototype.slice.call(document.querySelectorAll(GATE_WIDGET_SELECTOR));
		if (!widgets.length) {
			return;
		}

		var containerToWidgets = new Map();
		widgets.forEach(function (widget) {
			var container = findGateContainerForWidget(widget);
			if (!container) {
				return;
			}
			if (!containerToWidgets.has(container)) {
				containerToWidgets.set(container, []);
			}
			containerToWidgets.get(container).push(widget);
		});

		containerToWidgets.forEach(function (rawList, container) {
			if (rawList.length < 2) {
				return;
			}

			container.classList.add(CONTAINER_INVALID_CLASS);
			if (!container.querySelector('.' + BADGE_CLASS)) {
				var badge = document.createElement('div');
				badge.className = BADGE_CLASS;
				badge.setAttribute('role', 'status');
				badge.textContent = containerWarningText;
				container.appendChild(badge);
			}

			var ordered = sortWidgetsDocumentOrder(rawList);
			ordered.forEach(function (widget, index) {
				if (index === 0) {
					return;
				}
				widget.classList.add(DUPLICATE_WIDGET_CLASS);
				ensureDuplicateMessage(widget);
			});
		});

		if (DEBUG) {
			var now = Date.now();
			if (now - lastDebugLogMs > 1000) {
				lastDebugLogMs = now;
				// eslint-disable-next-line no-console
				console.log('[CreatorReactor] editor gate constraint scan:', {
					gateWidgets: widgets.length,
					containersWithGates: containerToWidgets.size
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
			}, 0);
		};

		if (!('MutationObserver' in window)) {
			scanAndEnforce();
			return;
		}

		var observer = new MutationObserver(function () {
			scheduleScan();
		});
		observer.observe(document.body, { childList: true, subtree: true });

		var attempts = 0;
		var maxAttempts = 8;
		var attemptScan = function () {
			attempts += 1;
			scanAndEnforce();
			if (attempts < maxAttempts) {
				setTimeout(attemptScan, 100);
			}
		};
		attemptScan();
	}

	ensureEditorConstraintCss();
	main();
})();
