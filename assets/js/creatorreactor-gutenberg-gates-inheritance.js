/* global document, window, CreatorReactorGatesInheritanceCore */

(function () {
	'use strict';

	if (!window.CreatorReactorGatesInheritanceCore || typeof window.CreatorReactorGatesInheritanceCore.create !== 'function') {
		return;
	}

	window.CreatorReactorGatesInheritanceCore.create({
		hiddenClass: 'creatorreactor-gutenberg-gate-hidden',
		prehideClass: 'creatorreactor-gutenberg-gate-prehide',
		markerSelector: '.creatorreactor-gutenberg-gate-marker[data-creatorreactor-gate-match]',
		mode: 'gutenberg',
		styleDataAttr: 'gutenberg',
		widgetNameToGate: {},
		logLabel: 'gutenberg gate inheritance scan',
		debugGlobal: 'CreatorReactorGutenbergGatesInheritanceDebug'
	}).boot();
})();
