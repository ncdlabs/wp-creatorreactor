/**
 * Show and dismiss the post-activation Elementor / Gutenberg prompt modal.
 */
(function () {
	'use strict';

	function openModal(modal) {
		if (!modal) {
			return;
		}
		modal.setAttribute('aria-hidden', 'false');
		document.body.classList.add('creatorreactor-editor-prompt-open');
		document.body.style.overflow = 'hidden';
		var closeBtn = modal.querySelector('.creatorreactor-editor-prompt-close');
		if (closeBtn) {
			closeBtn.focus();
		}
	}

	function closeModal(modal) {
		if (!modal) {
			return;
		}
		modal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('creatorreactor-editor-prompt-open');
		document.body.style.overflow = '';
	}

	function dismissViaAjax(modal) {
		var cfg = typeof creatorreactorEditorPrompt !== 'undefined' ? creatorreactorEditorPrompt : null;
		if (!cfg || !cfg.ajaxUrl || !cfg.nonce) {
			closeModal(modal);
			return;
		}
		var body = new URLSearchParams();
		body.set('action', 'creatorreactor_dismiss_editor_prompt');
		body.set('nonce', cfg.nonce);
		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		})
			.catch(function () {})
			.finally(function () {
				closeModal(modal);
			});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var modal = document.getElementById('creatorreactor-editor-prompt-modal');
		if (!modal) {
			return;
		}

		openModal(modal);

		modal.querySelectorAll('.creatorreactor-editor-prompt-dismiss, .creatorreactor-modal-backdrop').forEach(function (el) {
			el.addEventListener('click', function () {
				dismissViaAjax(modal);
			});
		});

		var closeX = modal.querySelector('.creatorreactor-editor-prompt-close');
		if (closeX) {
			closeX.addEventListener('click', function () {
				dismissViaAjax(modal);
			});
		}

		document.addEventListener('keydown', function (ev) {
			if (ev.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') {
				dismissViaAjax(modal);
			}
		});
	});
})();
