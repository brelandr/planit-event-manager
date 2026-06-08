/**
 * Review request notice - AJAX dismissal.
 *
 * @package The_Event_Calendar
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		var $notice = $('#twec-review-request-notice');
		if (!$notice.length) {
			return;
		}

		$notice.on('click', '.twec-review-btn', function(e) {
			var $btn = $(this);
			var action = $btn.data('action');

			if (action !== 'dismiss') {
				return;
			}

			e.preventDefault();
			$.ajax({
				url: (typeof twecReviewRequest !== 'undefined') ? twecReviewRequest.ajaxUrl : ajaxurl,
				type: 'POST',
				data: {
					action: 'twec_dismiss_review_request',
					nonce: (typeof twecReviewRequest !== 'undefined') ? twecReviewRequest.nonce : $('#twec-review-nonce').val()
				},
				success: function() {
					$notice.slideUp(200, function() {
						$(this).remove();
					});
				}
			});
		});
	});
})(jQuery);
