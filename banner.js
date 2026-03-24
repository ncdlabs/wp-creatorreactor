jQuery(document).ready(function($) {
	$('#creatorreactor-oauth-banner .button').on('click', function(e) {
		e.preventDefault();
		var $banner = $('#creatorreactor-oauth-banner');
		var $button = $(this);

		// Send AJAX request to dismiss the banner
		$.post(
			ajaxurl,
			{
				action: 'creatorreactor_oauth_dismiss_banner',
				security: creatorreactor_oauth_banner.nonce
			},
			function(response) {
				if (response.success) {
					// Remove the banner from the DOM
					$banner.fadeOut();
				}
			}
		);
	});
});