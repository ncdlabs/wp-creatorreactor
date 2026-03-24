/**
 * CreatorReactor onboarding: ToS modal (focus trap, ARIA), client validation, country hint.
 */
(function () {
	'use strict';

	var modal = document.getElementById('cr-ob-tos-modal');
	var trigger = document.getElementById('cr-ob-tos-trigger');
	var country = document.getElementById('creatorreactor_country');
	var form = document.getElementById('cr-ob-form');
	var tosCheckbox = document.getElementById('creatorreactor_tos_accept');
	var tosError = document.getElementById('cr-ob-tos-error');
	var tosMsg =
		typeof creatorreactorOnboarding !== 'undefined' && creatorreactorOnboarding.tosError
			? creatorreactorOnboarding.tosError
			: 'You must agree to the Terms of Service to continue.';

	var lastFocus = null;
	var trapOnKeydown = null;

	function getPanelFocusables() {
		if (!modal) {
			return [];
		}
		var panel = modal.querySelector('.cr-ob-modal__panel');
		if (!panel) {
			return [];
		}
		var sel = panel.querySelectorAll(
			'a[href]:not([disabled]), button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
		);
		return Array.prototype.filter.call(sel, function (el) {
			if (el.closest('[hidden]')) {
				return false;
			}
			var t = el.getAttribute('tabindex');
			if (t === '-1') {
				return false;
			}
			var style = window.getComputedStyle(el);
			return style.visibility !== 'hidden' && style.display !== 'none';
		});
	}

	function openModal(e) {
		if (e) {
			e.preventDefault();
		}
		if (!modal) {
			return;
		}
		lastFocus = document.activeElement;
		modal.removeAttribute('hidden');
		modal.setAttribute('aria-hidden', 'false');
		modal.classList.add('is-open');
		document.body.classList.add('cr-ob-modal-open');
		if (trigger) {
			trigger.setAttribute('aria-expanded', 'true');
		}

		trapOnKeydown = function (ke) {
			if (!modal.classList.contains('is-open') || ke.key !== 'Tab') {
				return;
			}
			var list = getPanelFocusables();
			if (list.length === 0) {
				return;
			}
			var first = list[0];
			var last = list[list.length - 1];
			if (ke.shiftKey) {
				if (document.activeElement === first || document.activeElement === modal) {
					ke.preventDefault();
					last.focus();
				}
			} else {
				if (document.activeElement === last) {
					ke.preventDefault();
					first.focus();
				}
			}
		};
		document.addEventListener('keydown', trapOnKeydown, true);

		var closeBtn = modal.querySelector('.cr-ob-modal__close');
		if (closeBtn) {
			closeBtn.focus();
		} else {
			modal.focus();
		}
	}

	function closeModal() {
		if (!modal) {
			return;
		}
		if (trapOnKeydown) {
			document.removeEventListener('keydown', trapOnKeydown, true);
			trapOnKeydown = null;
		}
		modal.setAttribute('hidden', '');
		modal.setAttribute('aria-hidden', 'true');
		modal.classList.remove('is-open');
		document.body.classList.remove('cr-ob-modal-open');
		if (trigger) {
			trigger.setAttribute('aria-expanded', 'false');
		}
		if (lastFocus && typeof lastFocus.focus === 'function') {
			lastFocus.focus();
		} else if (trigger) {
			trigger.focus();
		}
		lastFocus = null;
	}

	if (trigger) {
		trigger.addEventListener('click', openModal);
	}

	if (modal) {
		modal.querySelectorAll('[data-cr-ob-close]').forEach(function (el) {
			el.addEventListener('click', closeModal);
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && modal.classList.contains('is-open')) {
				closeModal();
			}
		});
	}

	function showTosError(text) {
		if (!tosError) {
			return;
		}
		tosError.textContent = text;
		tosError.hidden = false;
		if (tosCheckbox) {
			tosCheckbox.setAttribute('aria-invalid', 'true');
		}
	}

	function clearTosError() {
		if (!tosError) {
			return;
		}
		tosError.textContent = '';
		tosError.hidden = true;
		if (tosCheckbox) {
			tosCheckbox.removeAttribute('aria-invalid');
		}
	}

	if (tosCheckbox) {
		tosCheckbox.addEventListener('change', clearTosError);
	}

	if (form && tosCheckbox) {
		form.addEventListener('submit', function (e) {
			if (!tosCheckbox.checked) {
				e.preventDefault();
				showTosError(tosMsg);
				tosCheckbox.focus();
			}
		});
	}

	if (country && !country.value) {
		try {
			var loc = (navigator.language || '').split('-');
			var code = loc[1] || loc[0];
			if (code && typeof Intl !== 'undefined' && Intl.DisplayNames) {
				var dn = new Intl.DisplayNames([navigator.language || 'en'], { type: 'region' });
				var name = dn.of(code.toUpperCase());
				if (name) {
					country.placeholder = name;
				}
			}
		} catch (ignore) {
			/* optional hint only */
		}
	}
})();
