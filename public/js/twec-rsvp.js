/**
 * Front-end RSVP form (twec_rsvp shortcode).
 *
 * @package PlanIt_Event_Manager
 */

(function () {
	'use strict';

	var l10n = window.twecRsvpL10n || {};

	document.querySelectorAll('.twec-rsvp').forEach(function (formRoot) {
		var sendButton = formRoot.querySelector('.twec-rsvp-send');
		var cancelButton = formRoot.querySelector('.twec-rsvp-cancel');
		var messageEl = formRoot.querySelector('.twec-rsvp-message');

		if (!sendButton || !messageEl) {
			return;
		}

		sendButton.addEventListener('click', function () {
			var remindCheckbox = formRoot.querySelector('.twec-rsvp-remind');
			var emailField = formRoot.querySelector('.twec-rsvp-email');
			var nameField = formRoot.querySelector('.twec-rsvp-name');
			var endpoint = sendButton.getAttribute('data-endpoint');
			var nonce = sendButton.getAttribute('data-nonce');
			var eventId = parseInt(sendButton.getAttribute('data-event'), 10);

			if (!endpoint || !nonce) {
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
					event_id: eventId,
					email: emailField ? emailField.value : '',
					name: nameField ? nameField.value : '',
					remind: !!(remindCheckbox && remindCheckbox.checked)
				})
			})
				.then(function (response) {
					return response.json().then(function (body) {
						return { status: response.status, body: body };
					});
				})
				.then(function (result) {
					messageEl.style.display = 'block';
					if (
						result.status === 200 &&
						result.body &&
						result.body.waitlist
					) {
						messageEl.textContent = l10n.waitlist || '';
					} else if (result.status === 200) {
						messageEl.textContent = l10n.onList || '';
					} else {
						messageEl.textContent =
							(result.body && result.body.message) ||
							l10n.rsvpFailed ||
							'';
					}
				})
				.catch(function () {
					messageEl.style.display = 'block';
					messageEl.textContent = l10n.networkError || '';
				});
		});

		if (!cancelButton) {
			return;
		}

		cancelButton.addEventListener('click', function () {
			var emailField = formRoot.querySelector('.twec-rsvp-email');
			var email = emailField
				? String(emailField.value || '').trim()
				: '';
			var endpoint = cancelButton.getAttribute('data-cancel-endpoint');
			var nonce = cancelButton.getAttribute('data-nonce');
			var eventId = parseInt(cancelButton.getAttribute('data-event'), 10);

			if (!email) {
				messageEl.style.display = 'block';
				messageEl.textContent = l10n.cancelEmailRequired || '';
				return;
			}

			if (!endpoint || !nonce) {
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
					event_id: eventId,
					email: email
				})
			})
				.then(function (response) {
					return response.json().then(function (body) {
						return { status: response.status, body: body };
					});
				})
				.then(function (result) {
					messageEl.style.display = 'block';
					if (result.status !== 200) {
						messageEl.textContent =
							(result.body && result.body.message) ||
							l10n.cancelFailed ||
							'';
						return;
					}

					var payload = result.body || {};
					if (!payload.removed) {
						messageEl.textContent = l10n.cancelNotFound || '';
						return;
					}

					if (payload.context === 'waitlist') {
						messageEl.textContent = l10n.cancelWaitlistRemoved || '';
					} else {
						messageEl.textContent = l10n.cancelRsvpRemoved || '';
					}
				})
				.catch(function () {
					messageEl.style.display = 'block';
					messageEl.textContent = l10n.networkError || '';
				});
		});
	});
})();
