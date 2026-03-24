/**
 * CreatorReactor Users tab: refresh list via AJAX (pairs with creatorreactor_get_users_table).
 */
(function($) {
	'use strict';

	$(function() {
		if (typeof creatorreactorUsersTable === 'undefined') {
			return;
		}

		var $panel = $('#creatorreactor-users-panel');

		var $modal = $('#creatorreactor-user-details-modal');
		if ($modal.length) {
			$modal.appendTo(document.body);
		}
		var $modalBody = $modal.length
			? $modal.find('#creatorreactor-user-details-modal-body')
			: $();
		var $modalClose = $modal.length
			? $modal.find('.creatorreactor-user-details-close')
			: $();

		function ajaxAdminUrl() {
			if (creatorreactorUsersTable.ajaxUrl) {
				return creatorreactorUsersTable.ajaxUrl;
			}
			return typeof ajaxurl !== 'undefined' ? ajaxurl : '';
		}

		function escapeHtml(text) {
			return $('<div/>').text(String(text)).html();
		}

		function errorPanelMarkup(message) {
			var refresh =
				creatorreactorUsersTable.refreshLabel || 'Sync & refresh list';
			return (
				'<div id="creatorreactor-users-inner" class="creatorreactor-users-inner">' +
				'<div class="notice notice-error inline"><p>' +
				escapeHtml(message) +
				'</p></div>' +
				'<p><button type="button" class="button" id="creatorreactor-users-refresh">' +
				refresh +
				'</button></p></div>'
			);
		}

		function setInnerBusy(busy) {
			if (!$panel.length) {
				return;
			}
			var $inner = $panel.find('#creatorreactor-users-inner');
			if (busy) {
				$inner.attr('aria-busy', 'true');
			} else {
				$inner.removeAttr('aria-busy');
			}
		}

		function parseJsonResponse(raw) {
			if (raw && typeof raw === 'object') {
				return raw;
			}
			if (typeof raw !== 'string' || raw === '') {
				return null;
			}
			var s = raw.replace(/^\s+/, '');
			var start = s.indexOf('{');
			if (start === -1) {
				return null;
			}
			try {
				return JSON.parse(s.slice(start));
			} catch (e) {
				return null;
			}
		}

		function messageFromFailedRequest(jqXHR, textStatus) {
			var defErr =
				creatorreactorUsersTable.loadError || 'Error loading user table.';
			if (textStatus === 'parsererror') {
				return defErr;
			}
			if (!jqXHR) {
				return defErr;
			}
			var raw = (jqXHR.responseText || '').trim();
			if (raw === '-1' || raw === '0') {
				return (
					creatorreactorUsersTable.sessionError ||
					'Request was blocked or your session expired. Reload this page and try again.'
				);
			}
			var j = jqXHR.responseJSON;
			if (j && j.data) {
				if (typeof j.data === 'string') {
					return j.data;
				}
				if (j.data.message) {
					return String(j.data.message);
				}
			}
			var parsed = parseJsonResponse(jqXHR.responseText);
			if (parsed && parsed.data) {
				if (typeof parsed.data === 'string') {
					return parsed.data;
				}
				if (parsed.data.message) {
					return String(parsed.data.message);
				}
			}
			if (jqXHR.status === 403 || jqXHR.status === 0) {
				return (
					creatorreactorUsersTable.sessionError ||
					'Request was blocked or your session expired. Reload this page and try again.'
				);
			}
			return defErr;
		}

		function applyPanelHtml(htmlString) {
			if (!$panel.length) {
				return;
			}
			if (typeof htmlString === 'string' && htmlString !== '') {
				var $next = $('<div>').html(htmlString);
				var $nextInner = $next.find('#creatorreactor-users-inner').first();
				var $currentInner = $panel.find('#creatorreactor-users-inner').first();

				if ($nextInner.length && $currentInner.length) {
					$currentInner.replaceWith($nextInner);
				} else {
					$panel.html(htmlString);
				}
			}
		}

		function panelHtmlFromSuccessPayload(res) {
			if (!res || !res.success || res.data == null) {
				return null;
			}
			if (typeof res.data === 'string' && res.data !== '') {
				return res.data;
			}
			if (typeof res.data === 'object' && typeof res.data.html === 'string') {
				return res.data.html;
			}
			return null;
		}

		function appendSyncLogAndRefreshPanel(message, done) {
			var msg =
				message && String(message).trim() !== ''
					? String(message)
					: creatorreactorUsersTable.loadError ||
					  'Error loading user table.';
			$.ajax({
				url: ajaxAdminUrl(),
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'creatorreactor_append_sync_log',
					security: creatorreactorUsersTable.nonce,
					message: msg
				}
			})
				.done(function(response) {
					var res = parseJsonResponse(response) || response;
					var html = panelHtmlFromSuccessPayload(res);
					if (html) {
						applyPanelHtml(html);
					} else {
						applyPanelHtml(errorPanelMarkup(msg));
					}
				})
				.fail(function(jqXHR) {
					var pdata =
						jqXHR.responseJSON &&
						jqXHR.responseJSON.data &&
						typeof jqXHR.responseJSON.data === 'object'
							? jqXHR.responseJSON.data
							: null;
					if (pdata && pdata.panelHtml) {
						applyPanelHtml(pdata.panelHtml);
					} else {
						applyPanelHtml(errorPanelMarkup(msg));
					}
				})
				.always(function() {
					if (typeof done === 'function') {
						done();
					}
				});
		}

		function refreshTable() {
			if (!$panel.length) {
				return;
			}
			setInnerBusy(true);
			$.ajax({
				url: ajaxAdminUrl(),
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'creatorreactor_get_users_table',
					security: creatorreactorUsersTable.nonce
				}
			})
				.done(function(response) {
					var res = parseJsonResponse(response) || response;
					var defErr =
						creatorreactorUsersTable.loadError || 'Error loading user table.';
					var html = panelHtmlFromSuccessPayload(res);
					if (html) {
						applyPanelHtml(html);
						setInnerBusy(false);
						return;
					}
					var msg = defErr;
					if (res && res.data !== undefined && res.data !== null) {
						if (typeof res.data === 'string') {
							msg = res.data;
						} else if (
							typeof res.data === 'object' &&
							res.data.message
						) {
							msg = String(res.data.message);
						}
					}
					appendSyncLogAndRefreshPanel(String(msg), function() {
						setInnerBusy(false);
					});
				})
				.fail(function(jqXHR, textStatus) {
					var msg = messageFromFailedRequest(jqXHR, textStatus);
					var pdata =
						jqXHR.responseJSON &&
						jqXHR.responseJSON.data &&
						typeof jqXHR.responseJSON.data === 'object'
							? jqXHR.responseJSON.data
							: null;
					if (pdata && pdata.panelHtml) {
						applyPanelHtml(pdata.panelHtml);
						setInnerBusy(false);
						return;
					}
					appendSyncLogAndRefreshPanel(msg, function() {
						setInnerBusy(false);
					});
				});
		}

		function userDetailsModalOpen() {
			return $modal.length && $modal.attr('aria-hidden') === 'false';
		}

		function closeDetailsModal() {
			if (!$modal.length) {
				return;
			}
			$modal.attr('aria-hidden', 'true');
			$(document).off('keydown.creatorreactorUserDetails');
		}

		function openDetailsModal(details) {
			if (!$modal.length || !$modalBody.length) {
				return;
			}
			var parts = ['<dl>'];
			if (details && Array.isArray(details.lines)) {
				for (var i = 0; i < details.lines.length; i++) {
					var line = details.lines[i];
					if (!line) {
						continue;
					}
					var v =
						line.value !== undefined && line.value !== null
							? String(line.value)
							: '';
					parts.push(
						'<dt>' +
							escapeHtml(line.label || '') +
							'</dt><dd>' +
							escapeHtml(v) +
							'</dd>'
					);
				}
			}
			parts.push('</dl>');
			$modalBody.html(parts.join(''));
			$modal.attr('aria-hidden', 'false');
			$(document).on('keydown.creatorreactorUserDetails', function(ev) {
				if (ev.key === 'Escape' && userDetailsModalOpen()) {
					ev.preventDefault();
					closeDetailsModal();
				}
			});
			if ($modalClose.length) {
				$modalClose.trigger('focus');
			}
		}

		$(document).on(
			'click',
			'#creatorreactor-users-panel .creatorreactor-user-action-details',
			function(e) {
				e.preventDefault();
				var entId = $(this).attr('data-entitlement-id');
				if (!entId) {
					return;
				}
				var loading =
					creatorreactorUsersTable.detailsLoading || 'Loading…';
				openDetailsModal({
					lines: [{ label: '', value: loading }]
				});
				$.ajax({
					url: ajaxAdminUrl(),
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'creatorreactor_get_entitlement_details',
						security: creatorreactorUsersTable.nonce,
						entitlement_id: entId
					}
				})
					.done(function(response) {
						var res = parseJsonResponse(response) || response;
						var defErr =
							creatorreactorUsersTable.detailsLoadError ||
							'Could not load record details.';
						function showErr(msg) {
							openDetailsModal({
								lines: [{ label: '', value: msg }]
							});
						}
						if (!res || !res.success) {
							var msg = defErr;
							if (res && res.data !== undefined && res.data !== null) {
								if (typeof res.data === 'string') {
									msg = res.data;
								} else if (
									typeof res.data === 'object' &&
									res.data.message
								) {
									msg = String(res.data.message);
								}
							}
							showErr(msg);
							return;
						}
						if (!res.data || !Array.isArray(res.data.lines)) {
							showErr(defErr);
							return;
						}
						openDetailsModal(res.data);
					})
					.fail(function(jqXHR) {
						var msg = messageFromFailedRequest(
							jqXHR,
							jqXHR.statusText || 'error'
						);
						openDetailsModal({
							lines: [{ label: '', value: msg }]
						});
					});
			}
		);

		if ($panel.length) {
			$panel.on('click', '#creatorreactor-users-refresh', function(e) {
				e.preventDefault();
				refreshTable();
			});

			$panel.on('click', '.creatorreactor-user-action-sync', function(e) {
				e.preventDefault();
				$panel.find('#creatorreactor-users-refresh').trigger('click');
			});

			$panel.on('click', '.creatorreactor-user-action-deactivate', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var entId = $btn.attr('data-entitlement-id');
				var msg =
					creatorreactorUsersTable.confirmDeactivate ||
					'Deactivate this WordPress user?';
				if (!entId || !window.confirm(msg)) {
					return;
				}
				setInnerBusy(true);
				$.ajax({
					url: ajaxAdminUrl(),
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'creatorreactor_deactivate_wp_user',
						security: creatorreactorUsersTable.nonce,
						entitlement_id: entId
					}
				})
					.done(function(response) {
						var res = parseJsonResponse(response) || response;
						var html = panelHtmlFromSuccessPayload(res);
						if (html) {
							applyPanelHtml(html);
							setInnerBusy(false);
							return;
						}
						var err =
							creatorreactorUsersTable.deactivateError ||
							'Could not deactivate user.';
						if (res && res.data) {
							if (typeof res.data === 'string') {
								err = res.data;
							} else if (
								typeof res.data === 'object' &&
								res.data.message
							) {
								err = String(res.data.message);
							}
						}
						window.alert(err);
						setInnerBusy(false);
					})
					.fail(function(jqXHR) {
						var pdata =
							jqXHR.responseJSON &&
							jqXHR.responseJSON.data &&
							typeof jqXHR.responseJSON.data === 'object'
								? jqXHR.responseJSON.data
								: null;
						if (pdata && pdata.panelHtml) {
							applyPanelHtml(pdata.panelHtml);
							setInnerBusy(false);
							return;
						}
						var msg = messageFromFailedRequest(
							jqXHR,
							jqXHR.statusText || 'error'
						);
						window.alert(msg);
						setInnerBusy(false);
					});
			});
		}

		if ($modalClose.length) {
			$modalClose.on('click', function(e) {
				e.preventDefault();
				closeDetailsModal();
			});
		}

		if ($modal.length) {
			$modal.on('click', function(e) {
				if ($(e.target).is('.creatorreactor-modal-backdrop')) {
					closeDetailsModal();
				}
			});
		}
	});
})(jQuery);
