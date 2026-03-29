(function () {
	function creatorreactorRegistrationBannerMount() {
		var wrap = document.getElementById('creatorreactor-oauth-banner');
		var wpwrap = document.getElementById('wpwrap');
		if (!wrap || !wpwrap || !document.body.classList.contains('creatorreactor-registration-banner-active')) {
			return;
		}
		if (wpwrap.previousElementSibling === wrap) {
			return;
		}
		document.body.insertBefore(wrap, wpwrap);
	}

	jQuery(document).ready(function ($) {
		creatorreactorRegistrationBannerMount();

		$(document).on('click', '.creatorreactor-oauth-banner-dismiss', function (e) {
			e.preventDefault();
			var $wrap = $(this).closest('.creatorreactor-registration-alert-wrap');
			if (!$wrap.length) {
				return;
			}
			$.post(
				ajaxurl,
				{
					action: 'creatorreactor_oauth_dismiss_banner',
					security: creatorreactor_oauth_banner.nonce
				},
				function (response) {
					if (response.success) {
						$wrap.fadeOut(function () {
							$(this).remove();
							$('body').removeClass('creatorreactor-registration-banner-active');
						});
					}
				}
			);
		});

		$(document).on('click', '.creatorreactor-registration-alert-fix', function (e) {
			e.preventDefault();
			var cfg = typeof window.creatorreactorIntegrationFix !== 'undefined' ? window.creatorreactorIntegrationFix : null;
			if (
				cfg &&
				cfg.ajaxUrl &&
				cfg.nonce &&
				cfg.action &&
				cfg.fixes &&
				cfg.fixes.membership_signup &&
				cfg.fixes.membership_signup.type === 'ajax'
			) {
				var body = new window.FormData();
				body.append('action', cfg.action);
				body.append('nonce', cfg.nonce);
				body.append('fix_id', 'membership_signup');
				window
					.fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
					.then(function (res) {
						return res.json();
					})
					.then(function (data) {
						if (data && data.success) {
							window.location.reload();
							return;
						}
						var msg =
							(creatorreactor_oauth_banner && creatorreactor_oauth_banner.fixError) ||
							'Could not apply the fix.';
						if (data && data.data && data.data.message) {
							msg = data.data.message;
						}
						window.alert(msg);
					})
					.catch(function () {
						window.alert(
							(creatorreactor_oauth_banner && creatorreactor_oauth_banner.fixError) ||
								'Could not apply the fix.'
						);
					});
				return;
			}
			if (creatorreactor_oauth_banner && creatorreactor_oauth_banner.settingsGeneralUrl) {
				window.location.href = creatorreactor_oauth_banner.settingsGeneralUrl;
			}
		});
	});
})();
