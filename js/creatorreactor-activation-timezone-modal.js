/**
 * Post-activation timezone confirmation modal (admin).
 */
(function () {
	'use strict';

	function getBrowserTimeZone() {
		try {
			var ro = Intl.DateTimeFormat().resolvedOptions();
			return ro && ro.timeZone ? String(ro.timeZone) : '';
		} catch (e) {
			return '';
		}
	}

	function openModal(modal) {
		if (!modal) {
			return;
		}
		modal.setAttribute('aria-hidden', 'false');
		document.body.classList.add('creatorreactor-activation-tz-open');
		document.body.style.overflow = 'hidden';
	}

	function closeModal(modal) {
		if (!modal) {
			return;
		}
		modal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('creatorreactor-activation-tz-open');
		document.body.style.overflow = '';
	}

	function optionValues(select) {
		var out = {};
		var opts = select ? select.querySelectorAll('option[value]') : [];
		for (var i = 0; i < opts.length; i++) {
			var v = opts[i].getAttribute('value');
			if (v) {
				out[v] = true;
			}
		}
		return out;
	}

	function pickDefaultSelectValue(select, browserTz, siteTz, valuesMap) {
		if (browserTz && valuesMap[browserTz]) {
			return browserTz;
		}
		if (siteTz && valuesMap[siteTz]) {
			return siteTz;
		}
		return select.value || '';
	}

	function initActivationTimezoneModal() {
		var modal = document.getElementById('creatorreactor-activation-timezone-modal');
		var select = document.getElementById('creatorreactor-activation-tz-select');
		var detectedEl = document.getElementById('creatorreactor-activation-tz-detected');
		if (!modal || !select) {
			return;
		}

		var cfg = typeof creatorreactorActivationTz !== 'undefined' ? creatorreactorActivationTz : null;
		var browserTz = getBrowserTimeZone();
		var siteTz = cfg && cfg.siteTz ? String(cfg.siteTz) : '';
		var strings = cfg && cfg.strings ? cfg.strings : {};

		var valuesMap = optionValues(select);
		var chosen = pickDefaultSelectValue(select, browserTz, siteTz, valuesMap);
		if (chosen) {
			select.value = chosen;
		} else if (valuesMap.UTC) {
			select.value = 'UTC';
		}

		if (detectedEl) {
			if (browserTz) {
				var tmpl = strings.detected || 'Detected from your browser: %s';
				var parts = tmpl.split('%s');
				detectedEl.textContent = '';
				if (parts[0]) {
					detectedEl.appendChild(document.createTextNode(parts[0]));
				}
				var strong = document.createElement('strong');
				strong.textContent = browserTz;
				detectedEl.appendChild(strong);
				if (parts.length > 1 && parts[1]) {
					detectedEl.appendChild(document.createTextNode(parts[1]));
				}
			} else {
				detectedEl.textContent = strings.detectedEmpty || '';
			}
		}

		openModal(modal);

		var dialog = modal.querySelector('.creatorreactor-modal-dialog');
		if (dialog) {
			dialog.focus();
		} else {
			select.focus();
		}

		function postConfirm() {
			if (!cfg || !cfg.ajaxUrl || !cfg.nonce) {
				window.location.reload();
				return;
			}
			var tz = select.value || '';
			if (!tz && browserTz) {
				tz = browserTz;
			}
			if (!tz) {
				tz = 'browser';
			}
			var body = new URLSearchParams();
			body.set('action', 'creatorreactor_activation_timezone_confirm');
			body.set('nonce', cfg.nonce);
			body.set('display_timezone', tz);

			var btn = modal.querySelector('.cr-activation-tz-confirm');
			if (btn) {
				btn.disabled = true;
			}

			fetch(cfg.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			})
				.then(function (r) {
					if (!r.ok) {
						throw new Error('HTTP');
					}
					return r.json();
				})
				.then(function (json) {
					if (!json || !json.success) {
						throw new Error('fail');
					}
					window.location.reload();
				})
				.catch(function () {
					if (btn) {
						btn.disabled = false;
					}
					window.alert(strings.saveError || 'Could not save.');
				});
		}

		modal.addEventListener('click', function (e) {
			if (e.target.closest('.cr-activation-tz-confirm')) {
				e.preventDefault();
				postConfirm();
			}
		});

		document.addEventListener('keydown', function (ev) {
			if (ev.key !== 'Escape') {
				return;
			}
			if (modal.getAttribute('aria-hidden') === 'false') {
				ev.preventDefault();
				postConfirm();
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initActivationTimezoneModal);
	} else {
		initActivationTimezoneModal();
	}
})();
