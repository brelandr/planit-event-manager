/**
 * Front-end event submission form (twec_submission_form shortcode).
 *
 * @package PlanIt_Event_Manager
 */

(function () {
	'use strict';

	var l10n = window.twecSubmissionFormL10n || {};

	document.querySelectorAll('.twec-submit-proposal').forEach(function (button) {
		button.addEventListener('click', function () {
			var form = button.closest('.twec-submission-form');
			if (!form) {
				return;
			}

			var titleField = form.querySelector('.twec-subject-title');
			var contentField = form.querySelector('.twec-subject-content');
			var messageEl = form.querySelector('.twec-submission-message');
			var endpoint = button.getAttribute('data-endpoint');
			var nonce = button.getAttribute('data-nonce');

			if (!endpoint || !nonce || !messageEl) {
				return;
			}

			fetch(endpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce
				},
				credentials: 'same-origin',
				body: JSON.stringify({
					nonce: nonce,
					title: titleField ? titleField.value : '',
					content: contentField ? contentField.value : ''
				})
			})
				.then(function (response) {
					return response.json().then(function (body) {
						return { status: response.status, body: body };
					});
				})
				.then(function (result) {
					messageEl.style.display = 'block';
					if (result.status === 201) {
						messageEl.textContent = l10n.thanks || '';
					} else {
						messageEl.textContent =
							(result.body && result.body.message) || l10n.error || '';
					}
				})
				.catch(function () {
					messageEl.style.display = 'block';
					messageEl.textContent = l10n.networkError || '';
				});
		});
	});
})();
