/**
 * Post-activation editor prompt, then integration onboarding (Next / Ignore / Cancel).
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
	}

	function closeModal(modal) {
		if (!modal) {
			return;
		}
		modal.setAttribute('aria-hidden', 'true');
	}

	function unlockBody() {
		document.body.classList.remove('creatorreactor-editor-prompt-open');
		document.body.style.overflow = '';
	}

	function closeAllPromptModals(editorModal, onboardingModal) {
		closeModal(editorModal);
		closeModal(onboardingModal);
		unlockBody();
	}

	function dismissViaAjax(cfg, onDone, acknowledgeIntegration) {
		if (!cfg || !cfg.ajaxUrl || !cfg.nonce) {
			if (typeof onDone === 'function') {
				onDone();
			}
			return;
		}
		var body = new URLSearchParams();
		body.set('action', 'creatorreactor_dismiss_editor_prompt');
		body.set('nonce', cfg.nonce);
		if (acknowledgeIntegration) {
			body.set('acknowledge_integration', '1');
		}
		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		})
			.catch(function () {})
			.finally(function () {
				if (typeof onDone === 'function') {
					onDone();
				}
			});
	}

	function postOnboarding(action, nonce, ajaxUrl, extra) {
		var body = new URLSearchParams();
		body.set('action', action);
		body.set('nonce', nonce);
		if (extra && typeof extra === 'object') {
			Object.keys(extra).forEach(function (k) {
				body.set(k, extra[k]);
			});
		}
		return fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		}).then(function (r) {
			if (!r.ok) {
				return Promise.reject(new Error('HTTP'));
			}
			return r.json();
		});
	}

	function initEditorPromptModals() {
		var editorModal = document.getElementById('creatorreactor-editor-prompt-modal');
		var onboardingModal = document.getElementById('creatorreactor-onboarding-integration-modal');
		if (!editorModal) {
			return;
		}

		var cfg = typeof creatorreactorEditorPrompt !== 'undefined' ? creatorreactorEditorPrompt : null;

		openModal(editorModal);

		function showOnboarding() {
			closeModal(editorModal);
			if (onboardingModal) {
				openModal(onboardingModal);
				// Focus the dialog, not the primary "Next" button, so Enter does not run auto-fixes
				// when the user meant to use Ignore / Cancel or read the list first.
				var obDialog = onboardingModal.querySelector('#creatorreactor-onboarding-integration-dialog');
				if (obDialog) {
					obDialog.focus();
				}
			}
		}

		function finishFlow() {
			dismissViaAjax(
				cfg,
				function () {
					closeAllPromptModals(editorModal, onboardingModal);
					window.location.reload();
				},
				true
			);
		}

		function finishFlowNoReload(fromIntegrationStep) {
			dismissViaAjax(
				cfg,
				function () {
					closeAllPromptModals(editorModal, onboardingModal);
				},
				!!fromIntegrationStep
			);
		}

		// Delegation: works even if DOMContentLoaded already fired before this script ran (WP admin footer).
		editorModal.addEventListener('click', function (e) {
			var nextEl = e.target.closest('.creatorreactor-editor-prompt-next');
			if (nextEl && editorModal.contains(nextEl)) {
				e.preventDefault();
				showOnboarding();
				return;
			}
			if (e.target.classList.contains('creatorreactor-modal-backdrop')) {
				finishFlowNoReload(false);
			}
		});

		var closeX = editorModal.querySelector('.creatorreactor-editor-prompt-close');
		if (closeX) {
			closeX.addEventListener('click', function () {
				finishFlowNoReload(false);
			});
		}

		var firstNext = editorModal.querySelector('.creatorreactor-editor-prompt-next');
		if (firstNext) {
			firstNext.focus();
		}

		if (onboardingModal) {
			onboardingModal.addEventListener('click', function (e) {
				var t = e.target;
				if (t.classList.contains('creatorreactor-modal-backdrop') || t.classList.contains('creatorreactor-onboarding-integration-backdrop')) {
					finishFlowNoReload(true);
					return;
				}
				if (e.target.closest('.creatorreactor-onboarding-integration-cancel')) {
					e.preventDefault();
					finishFlowNoReload(true);
					return;
				}
				if (e.target.closest('.creatorreactor-onboarding-integration-close')) {
					e.preventDefault();
					finishFlowNoReload(true);
					return;
				}
				if (!cfg || !cfg.onboarding || !cfg.ajaxUrl) {
					return;
				}
				var ignoreEl = e.target.closest('.creatorreactor-onboarding-integration-ignore');
				if (ignoreEl && onboardingModal.contains(ignoreEl)) {
					e.preventDefault();
					e.stopPropagation();
					if (ignoreEl.disabled) {
						return;
					}
					ignoreEl.disabled = true;
					postOnboarding(cfg.onboarding.ignoreAction, cfg.onboarding.nonce, cfg.ajaxUrl)
						.then(function (json) {
							if (!json || !json.success) {
								ignoreEl.disabled = false;
								return;
							}
							finishFlow();
						})
						.catch(function () {
							ignoreEl.disabled = false;
						});
					return;
				}
				var applyEl = e.target.closest('.creatorreactor-onboarding-integration-next');
				if (applyEl && onboardingModal.contains(applyEl)) {
					e.preventDefault();
					e.stopPropagation();
					if (applyEl.disabled) {
						return;
					}
					applyEl.disabled = true;
					postOnboarding(cfg.onboarding.applyFixesAction, cfg.onboarding.nonce, cfg.ajaxUrl, {
						apply_integration_fixes: '1'
					})
						.then(function (json) {
							if (!json || !json.success) {
								applyEl.disabled = false;
								return;
							}
							var w = json.data && json.data.warnings ? json.data.warnings : [];
							if (w.length && cfg.strings && cfg.strings.applyError) {
								window.alert(cfg.strings.applyError);
							}
							finishFlow();
						})
						.catch(function () {
							applyEl.disabled = false;
						});
				}
			});
		}

		document.addEventListener('keydown', function (ev) {
			if (ev.key !== 'Escape') {
				return;
			}
			if (onboardingModal && onboardingModal.getAttribute('aria-hidden') === 'false') {
				ev.preventDefault();
				finishFlowNoReload(true);
				return;
			}
			if (editorModal.getAttribute('aria-hidden') === 'false') {
				ev.preventDefault();
				finishFlowNoReload(false);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initEditorPromptModals);
	} else {
		initEditorPromptModals();
	}
})();
