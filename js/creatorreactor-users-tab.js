/**
 * CreatorReactor Users tab: refresh list via AJAX (pairs with creatorreactor_get_users_table).
 */
(function($) {
	$(function() {
		var $inner = $('#creatorreactor-users-inner');
		if (!$inner.length || typeof creatorreactorUsersTable === 'undefined') {
			return;
		}

		function escapeHtml(text) {
			return $('<div/>').text(String(text)).html();
		}

		function errorMarkup(message) {
			var refresh =
				creatorreactorUsersTable.refreshLabel || 'Sync & refresh list';
			return (
				'<div class="notice notice-error inline"><p>' +
				escapeHtml(message) +
				'</p></div>' +
				'<p><button type="button" class="button" id="creatorreactor-users-refresh">' +
				refresh +
				'</button></p>'
			);
		}

		function refreshTable() {
			$inner.attr('aria-busy', 'true');
			$.post(
				typeof ajaxurl !== 'undefined' ? ajaxurl : '',
				{
					action: 'creatorreactor_get_users_table',
					security: creatorreactorUsersTable.nonce
				}
			)
				.done(function(response) {
					if (response && response.success && typeof response.data === 'string') {
						$inner.html(response.data);
						return;
					}
					var defErr =
						creatorreactorUsersTable.loadError || 'Error loading user table.';
					var msg = defErr;
					if (response && response.data) {
						msg =
							typeof response.data === 'string'
								? response.data
								: response.data.message || defErr;
					}
					$inner.html(errorMarkup(String(msg)));
				})
				.fail(function() {
					var defErr =
						creatorreactorUsersTable.loadError || 'Error loading user table.';
					$inner.html(errorMarkup(defErr));
				})
				.always(function() {
					$inner.removeAttr('aria-busy');
				});
		}

		$inner.on('click', '#creatorreactor-users-refresh', function(e) {
			e.preventDefault();
			refreshTable();
		});
	});
})(jQuery);
