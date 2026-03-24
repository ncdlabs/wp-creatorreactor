/**
 * CreatorReactor user profile: UUID details modal.
 */
(function($) {
	'use strict';

	$(function() {
		if (typeof creatorreactorUserProfile === 'undefined') {
			return;
		}

		var $modal = $('#creatorreactor-user-details-modal');
		if ($modal.length) {
			$modal.appendTo(document.body);
		}
		var $modalBody = $modal.length ? $modal.find('#creatorreactor-user-details-modal-body') : $();
		var $modalClose = $modal.length ? $modal.find('.creatorreactor-user-details-close') : $();

		function escapeHtml(text) {
			return $('<div/>').text(String(text)).html();
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

		function modalIsOpen() {
			return $modal.length && $modal.attr('aria-hidden') === 'false';
		}

		function closeModal() {
			if (!$modal.length) {
				return;
			}
			$modal.attr('aria-hidden', 'true');
			$(document).off('keydown.creatorreactorUserProfileDetails');
		}

		function openModal(details) {
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
					if (line.section) {
						parts.push('<dt class="creatorreactor-details-section">' + escapeHtml(line.label || '') + '</dt><dd class="creatorreactor-details-section-spacer"></dd>');
						continue;
					}
					var value = line.value !== undefined && line.value !== null ? String(line.value) : '';
					parts.push('<dt>' + escapeHtml(line.label || '') + '</dt><dd>' + escapeHtml(value) + '</dd>');
				}
			}
			parts.push('</dl>');
			$modalBody.html(parts.join(''));
			$modal.attr('aria-hidden', 'false');
			$(document).on('keydown.creatorreactorUserProfileDetails', function(ev) {
				if (ev.key === 'Escape' && modalIsOpen()) {
					ev.preventDefault();
					closeModal();
				}
			});
			if ($modalClose.length) {
				$modalClose.trigger('focus');
			}
		}

		function loadAndShowDetails(userId, hasDetails) {
			if (!userId) {
				return;
			}
			if (String(hasDetails) !== '1') {
				openModal({
					lines: [{ label: '', value: creatorreactorUserProfile.noEntitlementText || 'No CreatorReactor record found for this user.' }]
				});
				return;
			}

			openModal({
				lines: [{ label: '', value: creatorreactorUserProfile.detailsLoading || 'Loading…' }]
			});

			$.ajax({
				url: creatorreactorUserProfile.ajaxUrl || (typeof ajaxurl !== 'undefined' ? ajaxurl : ''),
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'creatorreactor_get_user_entitlement_details',
					security: creatorreactorUserProfile.nonce,
					user_id: userId
				}
			})
				.done(function(response) {
					var res = parseJsonResponse(response) || response;
					var defaultError = creatorreactorUserProfile.detailsLoadError || 'Could not load record details.';
					if (!res || !res.success || !res.data || !Array.isArray(res.data.lines)) {
						var msg = defaultError;
						if (res && res.data) {
							if (typeof res.data === 'string') {
								msg = res.data;
							} else if (typeof res.data === 'object' && res.data.message) {
								msg = String(res.data.message);
							}
						}
						openModal({ lines: [{ label: '', value: msg }] });
						return;
					}
					openModal(res.data);
				})
				.fail(function(jqXHR) {
					var msg = creatorreactorUserProfile.detailsLoadError || 'Could not load record details.';
					if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data) {
						if (typeof jqXHR.responseJSON.data === 'string') {
							msg = jqXHR.responseJSON.data;
						} else if (
							typeof jqXHR.responseJSON.data === 'object' &&
							jqXHR.responseJSON.data.message
						) {
							msg = String(jqXHR.responseJSON.data.message);
						}
					}
					openModal({ lines: [{ label: '', value: msg }] });
				});
		}

		$(document).on('click', '.creatorreactor-open-user-entitlement-details', function(e) {
			e.preventDefault();
			var $el = $(this);
			loadAndShowDetails($el.attr('data-user-id'), $el.attr('data-has-details'));
		});

		$(document).on('click', '.creatorreactor-user-uuid-field', function(e) {
			e.preventDefault();
			var $el = $(this);
			loadAndShowDetails($el.attr('data-user-id'), $el.attr('data-has-details'));
		});

		if ($modalClose.length) {
			$modalClose.on('click', function(e) {
				e.preventDefault();
				closeModal();
			});
		}

		if ($modal.length) {
			$modal.on('click', function(e) {
				if ($(e.target).is('.creatorreactor-modal-backdrop')) {
					closeModal();
				}
			});
		}
	});
})(jQuery);
