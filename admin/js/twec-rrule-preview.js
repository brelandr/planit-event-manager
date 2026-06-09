/**
 * Recurring event metabox: advanced RRULE toggle, presets, and REST preview.
 *
 * @package PlanIt_Event_Manager
 */

(function ($) {
	'use strict';

	var l10n = window.twecRrulePreviewL10n || {};

	$(function () {
		$('#twec_recurrence_advanced_cb').on('change', function () {
			$('#twec-advanced-recurrence').toggle($(this).is(':checked'));
		});

		$('#twec_rrule_preset').on('change', function () {
			var value = $(this).val();
			if (value) {
				$('#twec_recurrence_rrule').val(value);
			}
		});

		$('.twec_rrule_preview_btn').on('click', function () {
			var button = $(this);
			var output = $('#twec_rrule_preview_out');
			var endpointRoot = $('#twec_rrule_preview_rest_endpoint').val();
			var nonce = $('#twec_rrule_rest_nonce').val();
			var postIdField = button.closest('form').find('#post_ID');
			var postId = parseInt(postIdField.val(), 10) || l10n.postId || 0;
			var maxShow = parseInt(l10n.maxShow, 10) || 25;

			if (!endpointRoot || !nonce) {
				return;
			}

			output.text(l10n.running || '');

			$.ajax({
				url: endpointRoot.replace(/\/$/, '') + '/preview',
				method: 'POST',
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', nonce);
				},
				contentType: 'application/json',
				data: JSON.stringify({
					nonce: nonce,
					post_id: postId,
					rrule: $('#twec_recurrence_rrule').val(),
					exdates: $('#twec_recurrence_exdates').val()
				}),
				success: function (response) {
					if (!response || !response.preview || !response.preview.length) {
						output.text(l10n.noInstances || '');
						return;
					}

					var lines = [];
					var index;
					for (
						index = 0;
						index < response.preview.length && index < maxShow;
						index++
					) {
						lines.push(
							response.preview[index].start +
								' — ' +
								response.preview[index].end
						);
					}
					if (response.preview.length > maxShow) {
						lines.push(
							String(response.preview.length) +
								'+ ' +
								(l10n.truncated || '')
						);
					}
					output.text(lines.join('\n'));
				},
				error: function (xhr) {
					var payload = xhr.responseJSON;
					var message =
						payload &&
						(payload.message ||
							(payload.data && payload.data.message))
							? payload.message || payload.data.message
							: xhr.statusText;
					output.text(message || l10n.previewFailed || '');
				}
			});
		});
	});
})(jQuery);
