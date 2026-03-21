/**
 * CreatorReactor Users tab: refresh list via AJAX (pairs with creatorreactor_get_users_table).
 */
(function($) {
	$(function() {
		var $panel = $('#creatorreactor-users-panel');
		if (!$panel.length || typeof creatorreactorUsersTable === 'undefined') {
			return;
		}

		var $modal = $('#creatorreactor-user-details-modal');
		var $modalBody = $('#creatorreactor-user-details-modal-body');
		var $modalClose = $('#creatorreactor-user-details-modal-close');

		function ajaxAdminUrl() {
			if (creatorreactorUsersTable.ajaxUrl) {
				return creatorreactorUsersTable.ajaxUrl;
			}
			return typeof ajaxurl !== 'undefined' ? ajaxurl : '';
		}

		function escapeHtml(text) {
			return $('<div/>').text(String(text)).html();
		}

		function utcErrorLogLine(message) {
			var iso = new Date().toISOString().replace('T', ' ').replace(/\.\d{3}Z$/, ' UTC');
			return iso + ' [error] ' + String(message);
		}

		/** Full panel when sync log could not be loaded from server — keeps Sync log UI (same structure as PHP). */
		function errorPanelMarkup(message) {
			var refresh =
				creatorreactorUsersTable.refreshLabel || 'Sync & refresh list';
			var sum =
				creatorreactorUsersTable.syncLogSummary || 'Sync log';
			var hint =
				creatorreactorUsersTable.syncLogOffline || '';
			var line = utcErrorLogLine(message);
			return (
				'<div id="creatorreactor-users-inner" class="creatorreactor-users-inner">' +
				'<div class="notice notice-error inline"><p>' +
				escapeHtml(message) +
				'</p></div>' +
				'<p><button type="button" class="button" id="creatorreactor-users-refresh">' +
				refresh +
				'</button></p></div>' +
				'<details id="creatorreactor-users-sync-log" class="creatorreactor-connection-log creatorreactor-sync-log" open>' +
				'<summary>' +
				escapeHtml(sum) +
				'</summary>' +
				'<div class="creatorreactor-connection-log-body">' +
				(hint
					? '<p class="description">' + escapeHtml(hint) + '</p>'
					: '') +
				'<ul class="creatorreactor-connection-log-list"><li>' +
				escapeHtml(line) +
				'</li></ul>' +
				'</div></details>'
			);
		}

		function setInnerBusy(busy) {
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
			if (typeof htmlString === 'string' && htmlString !== '') {
				$panel.html(htmlString);
				// If markup ever nests duplicate sync log blocks, keep a single section.
				var $logs = $panel.find('.creatorreactor-sync-log');
				if ($logs.length > 1) {
					$logs.slice(1).remove();
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

		function closeDetailsModal() {
			if (!$modal.length) {
				return;
			}
			$modal.prop('hidden', true).removeClass('is-open');
			$(document).off('keydown.creatorreactorUserDetails');
		}

		function openDetailsModal(details) {
			if (!$modal.length || !$modalBody.length) {
				return;
			}
			var fields = creatorreactorUsersTable.detailFields;
			var parts = ['<dl>'];
			if (Array.isArray(fields)) {
				for (var i = 0; i < fields.length; i++) {
					var f = fields[i];
					if (!f || !f.key) {
						continue;
					}
					var val =
						details && Object.prototype.hasOwnProperty.call(details, f.key)
							? details[f.key]
							: '';
					parts.push(
						'<dt>' +
							escapeHtml(f.label || f.key) +
							'</dt><dd>' +
							escapeHtml(val) +
							'</dd>'
					);
				}
			}
			parts.push('</dl>');
			$modalBody.html(parts.join(''));
			$modal.prop('hidden', false).addClass('is-open');
			$(document).on('keydown.creatorreactorUserDetails', function(ev) {
				if (ev.key === 'Escape') {
					ev.preventDefault();
					closeDetailsModal();
				}
			});
			if ($modalClose.length) {
				$modalClose.trigger('focus');
			}
		}

		$panel.on('click', '#creatorreactor-users-refresh', function(e) {
			e.preventDefault();
			refreshTable();
		});

		$panel.on('click', '.creatorreactor-user-action-details', function(e) {
			e.preventDefault();
			var raw = $(this).attr('data-details');
			if (!raw) {
				return;
			}
			try {
				var details = JSON.parse(raw);
				openDetailsModal(details);
			} catch (err) {
				openDetailsModal({ product: raw });
			}
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
					var msg = messageFromFailedRequest(jqXHR, jqXHR.statusText || 'error');
					window.alert(msg);
					setInnerBusy(false);
				});
		});

		if ($modalClose.length) {
			$modalClose.on('click', function(e) {
				e.preventDefault();
				closeDetailsModal();
			});
		}

		if ($modal.length) {
			$modal.on('click', function(e) {
				if (e.target === $modal[0]) {
					closeDetailsModal();
				}
			});
			$modal.find('.creatorreactor-user-details-modal-dialog').on('click', function(e) {
				e.stopPropagation();
			});
		}
	});
})(jQuery);
