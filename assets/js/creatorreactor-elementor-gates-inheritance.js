/* global document, window, CreatorReactorGatesInheritanceCore */

(function () {
	'use strict';

	var localized = window.CreatorReactorElementorGatesInheritance || {};
	var widgetMap = localized.widgetNameToGate && typeof localized.widgetNameToGate === 'object'
		? localized.widgetNameToGate
		: {};

	if (!window.CreatorReactorGatesInheritanceCore || typeof window.CreatorReactorGatesInheritanceCore.create !== 'function') {
		return;
	}

	window.CreatorReactorGatesInheritanceCore.create({
		hiddenClass: 'creatorreactor-elementor-gate-hidden',
		prehideClass: 'creatorreactor-elementor-gate-prehide',
		markerSelector: '.creatorreactor-elementor-gate-marker[data-creatorreactor-gate-match], .elementor-element.elementor-widget[data-creatorreactor-gate][data-creatorreactor-gate-match], .elementor-element.elementor-widget[class*="elementor-widget-creatorreactor_"]:not(.elementor-widget-creatorreactor_fanvue_oauth)',
		mode: 'elementor',
		styleDataAttr: 'elementor',
		widgetNameToGate: widgetMap,
		logLabel: 'elementor gate inheritance scan',
		debugGlobal: 'CreatorReactorElementorGatesInheritanceDebug'
	}).boot();
})();
