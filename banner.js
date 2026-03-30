jQuery(document).ready(function($) {
	$('#creatorreactor-oauth-banner .button').on('click', function(e) {
		e.preventDefault();
		var $banner = $('#creatorreactor-oauth-banner');

		// Resolve AJAX endpoint safely.
		var ajaxEndpoint =
			typeof window !== 'undefined' && typeof window.ajaxurl === 'string' && window.ajaxurl
				? window.ajaxurl
				: null;
		if (!ajaxEndpoint) {
			if (window.console && typeof window.console.warn === 'function') {
				window.console.warn('Missing ajaxurl; aborting banner dismissal request.');
			}
			return;
		}

		// Ensure the localized security nonce is available before proceeding.
		var security = null;
		if (
			typeof window !== 'undefined' &&
			typeof window.creatorreactor_oauth_banner === 'object' &&
			window.creatorreactor_oauth_banner !== null &&
			typeof window.creatorreactor_oauth_banner.nonce === 'string'
		) {
			security = window.creatorreactor_oauth_banner.nonce;
		} else {
			if (window.console && typeof window.console.warn === 'function') {
				window.console.warn('Missing creatorreactor_oauth_banner.nonce; aborting banner dismissal request.');
			}
			return;
		}

		// Send AJAX request to dismiss the banner.
		$.post(
			ajaxEndpoint,
			{
				action: 'creatorreactor_oauth_dismiss_banner',
				security: security
			},
			function(response) {
				if (response && response.success) {
					// Remove the banner from the DOM.
					$banner.fadeOut();
				}
			}
		).fail(function(jqXHR, textStatus, errorThrown) {
			// Handle network or server errors gracefully.
			if (window.console && typeof window.console.error === 'function') {
				window.console.error('Failed to dismiss OAuth banner:', textStatus, errorThrown);
			}
		});
	});
});