/**
 * Embedded calendar Quick Add (privileged users).
 *
 * Submits via REST POST /planit/v1/events/quick-add and refreshes the calendar (Interactivity or legacy AJAX).
 */
(function ( $ ) {
	'use strict';

	var cfg = typeof window.twecQuickAdd === 'object' ? window.twecQuickAdd : {};

	function normalizeTimeForRange( raw, fallback ) {
		var s = $.trim( String( raw || '' ) );
		if ( ! s ) {
			return fallback;
		}
		if ( /^\d{2}:\d{2}$/.test( s ) ) {
			return s + ':00';
		}
		if ( /^\d{2}:\d{2}:\d{2}$/.test( s ) ) {
			return s;
		}
		return fallback;
	}

	function buildPayloadFromForm() {
		var allDay = !! $( '#twec-quick-add-all-day' ).prop( 'checked' );
		var payload = {
			all_day: allDay,
			start_date: $.trim(
				String( $( '#twec-quick-add-start-date' ).val() || '' )
			),
			end_date: $.trim(
				String( $( '#twec-quick-add-end-date' ).val() || '' )
			),
			start_time: normalizeTimeFragment(
				$( '#twec-quick-add-start-time' ).val() || ''
			),
			end_time: normalizeTimeFragment(
				$( '#twec-quick-add-end-time' ).val() || ''
			),
		};
		if ( ! allDay && '' === payload.start_time ) {
			payload.start_time = '00:00:00';
		}
		if ( ! allDay && '' === payload.end_time ) {
			payload.end_time = '23:59:59';
		}
		return payload;
	}

	function evaluateDatetime() {
		var i18n = cfg.i18n || {};
		var badDates =
			String( i18n.badDates || '' ) ||
			'Start and end dates must use Y-m-d.';
		var invalidRange =
			String( i18n.invalidRange || '' ) ||
			'End must be on or after the start.';
		var p = buildPayloadFromForm();
		var sd = p.start_date;
		var ed = p.end_date;
		if ( ! sd || ! ed ) {
			return { blockSubmit: false, showError: false, message: '' };
		}
		if (
			! /^\d{4}-\d{2}-\d{2}$/.test( sd ) ||
			! /^\d{4}-\d{2}-\d{2}$/.test( ed )
		) {
			return {
				blockSubmit: true,
				showError: true,
				message: badDates,
			};
		}
		var allDay = p.all_day;
		var startT = allDay ?
			'00:00:00' :
			normalizeTimeForRange( p.start_time, '00:00:00' );
		var endT = allDay ?
			'23:59:59' :
			normalizeTimeForRange( p.end_time, '23:59:59' );
		var startDt = sd + ' ' + startT;
		var endDt = ed + ' ' + endT;
		if (
			window.Date.parse( startDt.replace( ' ', 'T' ) ) >
			window.Date.parse( endDt.replace( ' ', 'T' ) )
		) {
			return {
				blockSubmit: true,
				showError: true,
				message: invalidRange,
			};
		}
		return { blockSubmit: false, showError: false, message: '' };
	}

	function refreshQuickAddDatetimeValidation() {
		var r = evaluateDatetime();
		var $btn = $( '.twec-quick-add-submit' ).first();
		if ( ! r.showError ) {
			setFeedback( '', false );
		} else {
			setFeedback( r.message, true );
		}
		if ( r.blockSubmit ) {
			$btn.prop( 'disabled', true );
		} else {
			$btn.prop( 'disabled', false );
		}
	}

	function setFeedback( msg, visible ) {
		var $fb = $( '#twec-quick-add-dialog .twec-quick-add-feedback' );
		if ( ! $fb.length ) {
			return;
		}
		if ( visible ) {
			$fb.removeAttr( 'hidden' ).text( msg || '' );
		} else {
			$fb.attr( 'hidden', 'hidden' ).text( '' );
		}
	}

	function normalizeTimeFragment( raw ) {
		var s = $.trim( String( raw || '' ) );
		if ( '' === s ) {
			return '';
		}
		if ( /^\d{2}:\d{2}:\d{2}$/.test( s ) ) {
			return s;
		}
		if ( /^\d{2}:\d{2}$/.test( s ) ) {
			return s + ':00';
		}
		return s;
	}

	function toggleAllDayTimes( checked ) {
		var $times = $( '#twec-quick-add-dialog .twec-quick-add-times' );
		$times.toggle( ! checked );
		$( '#twec-quick-add-start-time, #twec-quick-add-end-time' ).prop(
			'disabled',
			checked
		);
	}

	function refreshEmbeddedCalendarAfterCreate() {
		var twecData =
			typeof window.twecData === 'object' ? window.twecData : {};
		var useIntr = !!( twecData && twecData.useInteractivity );

		if (
			useIntr &&
			typeof window.twecPlanitReloadCalendar === 'function'
		) {
			return Promise.resolve(
				window.twecPlanitReloadCalendar()
			);
		}

		if ( useIntr ) {
			return reloadInteractivityCalendarViaAjaxFallback();
		}

		if ( typeof window.TWEC !== 'undefined' && TWEC.loadCalendar ) {
			TWEC.loadCalendar();
			return Promise.resolve();
		}
		return Promise.resolve();
	}

	function reloadInteractivityCalendarViaAjaxFallback() {
		var twecData =
			typeof window.twecData === 'object' ? window.twecData : {};
		var wrap = document.querySelector(
			'.twec-calendar-wrapper[data-wp-interactive="planit/calendar"]'
		);
		if (
			! wrap ||
			typeof twecData.ajaxUrl !== 'string' ||
			twecData.ajaxUrl === ''
		) {
			return Promise.resolve();
		}
		var fd = new window.FormData();
		fd.append( 'action', 'twec_get_calendar' );
		fd.append(
			'nonce',
			typeof twecData.nonce === 'string' ? twecData.nonce : ''
		);
		if ( typeof twecData.calPub !== 'undefined' &&
			String( twecData.calPub ) !== ''
		) {
			fd.append( 'cal_pub', String( twecData.calPub ) );
		}
		fd.append(
			'view',
			wrap.getAttribute( 'data-view' ) || 'month'
		);
		fd.append(
			'date',
			wrap.getAttribute( 'data-current-date' ) || ''
		);
		var ticketCta =
			wrap.getAttribute( 'data-twec-ticket-cta' ) === '1' ?
				'1' :
				'0';
		fd.append( 'ticket_cta', ticketCta );
		fd.append( 'response_format', 'compact' );
		fd.append( 'calendar_payload_version', '2' );

		return window
			.fetch( twecData.ajaxUrl, {
				method: 'POST',
				body: fd,
				credentials: 'same-origin',
			} )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( payload ) {
				if ( ! payload || ! payload.success || ! payload.data ) {
					return;
				}
				var data = payload.data;
				var titleSel =
					'.twec-calendar-wrapper[data-wp-interactive="planit/calendar"] .twec-calendar-title';
				var viewSel =
					'.twec-calendar-wrapper[data-wp-interactive="planit/calendar"] .twec-calendar-view';
				var titleEl = document.querySelector( titleSel );
				if ( titleEl && data.title ) {
					titleEl.textContent = String( data.title );
				}
				var viewHtml =
					typeof data.html === 'string' ? data.html : '';
				var pv =
					typeof data.payloadVersion !== 'undefined' ?
						Number( data.payloadVersion ) :
						1;
				if (
					pv >= 2 &&
					data.grid &&
					typeof window.twecCalendarHtmlFromStructuredGrid ===
						'function'
				) {
					var build =
						window.twecCalendarHtmlFromStructuredGrid.bind(
							window
						);
					var built = build( data.grid );
					if ( built ) {
						viewHtml = built;
					}
				}
				var viewEl = document.querySelector( viewSel );
				if ( viewHtml && viewEl ) {
					viewEl.innerHTML = viewHtml;
				}
				var activeView = String(
					wrap.getAttribute( 'data-view' ) || 'month'
				);
				if (
					activeView === 'map' &&
					data.mapMarkers &&
					data.mapMarkers.length &&
					typeof window.twecMapHydrateCalendarFromAjax ===
						'function'
				) {
					window.twecMapHydrateCalendarFromAjax(
						data.mapMarkers
					);
				} else if (
					activeView === 'map' &&
					typeof window.twecMapInitAll === 'function'
				) {
					window.twecMapInitAll();
				}
				if (
					window.twecAfterCalendarLoad &&
					typeof window.twecAfterCalendarLoad ===
						'function'
				) {
					window.twecAfterCalendarLoad( activeView );
				}
			} )
			.catch( function () {} );
	}

	function wpRestErrorMessage( payload ) {
		var i18n = cfg.i18n || {};
		var fallback = String( i18n.errorGeneric || '' );
		if (
			payload &&
			typeof payload.message === 'string' &&
			payload.message
		) {
			return payload.message;
		}
		return fallback || 'Request failed.';
	}

	function bind() {
		var $dlg = $( '#twec-quick-add-dialog' );
		if ( ! $dlg.length || ! $dlg.get( 0 ) ) {
			return;
		}

		var dlg = $dlg.get( 0 );

		$( '.twec-quick-add-open' ).on(
			'click',
			function ( e ) {
				e.preventDefault();
				setFeedback( '', false );

				var $wrap = $( this ).closest( '.twec-calendar-wrapper' ).first();
				if ( ! $wrap.length ) {
					$wrap = $( '.twec-calendar-wrapper' ).first();
				}
				var dayVal = $.trim( String( $wrap.attr( 'data-current-date' ) || '' ) );
				if ( dayVal ) {
					$( '#twec-quick-add-start-date' ).attr( 'value', dayVal );
					$( '#twec-quick-add-end-date' ).attr( 'value', dayVal );
				}

				if ( typeof dlg.showModal === 'function' ) {
					dlg.showModal();
				}
				window.setTimeout( function () {
					$( '#twec-quick-add-title-field' ).trigger( 'focus' );
					refreshQuickAddDatetimeValidation();
				}, 50 );
			}
		);

		$( '#twec-quick-add-all-day' ).on(
			'change',
			function () {
				toggleAllDayTimes( !! $( this ).prop( 'checked' ) );
				refreshQuickAddDatetimeValidation();
			}
		);

		toggleAllDayTimes(
			!! $( '#twec-quick-add-all-day' ).prop( 'checked' )
		);

		$( document ).on(
			'change input blur',
			'#twec-quick-add-start-date, #twec-quick-add-end-date, #twec-quick-add-start-time, #twec-quick-add-end-time',
			function () {
				refreshQuickAddDatetimeValidation();
			}
		);

		$( '#twec-quick-add-form' ).on(
			'submit',
			function ( ev ) {
				ev.preventDefault();
				setFeedback( '', false );

				var formEl = this;
				var i18n = cfg.i18n || {};
				var rangeCheck = evaluateDatetime();
				if ( rangeCheck.blockSubmit ) {
					setFeedback( rangeCheck.message, true );
					return;
				}

				if ( ! cfg.restUrl ) {
					setFeedback(
						wpRestErrorMessage( {
							message: String(
								( cfg.i18n && cfg.i18n.errorGeneric ) ?
									cfg.i18n.errorGeneric :
									''
							),
						} ),
						true
					);
					return;
				}

				var $titleEl = $( '#twec-quick-add-title-field' );
				var titleVal = $.trim( String( $titleEl.val() || '' ) );
				if ( ! titleVal ) {
					if ( typeof formEl.reportValidity === 'function' ) {
						formEl.reportValidity();
					}
					$titleEl.trigger( 'focus' );
					return;
				}

				var datetime = buildPayloadFromForm();
				var payload = {
					title: titleVal,
					status: $.trim(
						String(
							$( '#twec-quick-add-status' ).val() || 'draft'
						)
					),
					all_day: datetime.all_day,
					start_date: datetime.start_date,
					end_date: datetime.end_date,
					start_time: datetime.start_time,
					end_time: datetime.end_time,
				};

				if ( cfg.canPublish === false && 'publish' === payload.status ) {
					payload.status = 'draft';
				}

				var $btn = $( '.twec-quick-add-submit' ).first();
				var saveLabel = String( $btn.text() || '' );

				$btn.prop( 'disabled', true ).text(
					String( i18n.saving || 'Saving…' )
				);

				window
					.fetch(
						String( cfg.restUrl ),
						{
							method: 'POST',
							credentials: 'same-origin',
							headers: {
								'Content-Type': 'application/json',
								'X-WP-Nonce': String(
									cfg.restNonce || ''
								),
							},
							body: JSON.stringify( payload ),
						}
					)
					.then( function ( response ) {
						return response
							.json()
							.then( function ( body ) {
								return {
									ok: response.ok,
									body: body || {},
								};
							} )
							.catch(
								function () {
									return {
										ok: response.ok,
										body: {},
									};
								}
							);
					} )
					.then( function ( out ) {
						if (
							out.ok &&
							out.body &&
							out.body.id
						) {
							if (
								typeof dlg.close === 'function'
							) {
								dlg.close();
							}
							formEl.reset();
							toggleAllDayTimes( false );
							refreshQuickAddDatetimeValidation();
							return refreshEmbeddedCalendarAfterCreate();
						}
						setFeedback(
							wpRestErrorMessage( out.body ),
							true
						);
					} )
					.catch( function () {
						setFeedback(
							wpRestErrorMessage( { message: '' } ),
							true
						);
					} )
					.finally( function () {
						$btn.text( saveLabel );
						refreshQuickAddDatetimeValidation();
					} );
			}
		);

		$( '.twec-quick-add-cancel' ).on(
			'click',
			function ( e ) {
				e.preventDefault();
				var f = document.getElementById(
					'twec-quick-add-form'
				);
				if ( f ) {
					f.reset();
				}
				toggleAllDayTimes( false );
				if ( typeof dlg.close === 'function' ) {
					dlg.close();
				}
				refreshQuickAddDatetimeValidation();
			}
		);

		refreshQuickAddDatetimeValidation();
	}

	$( bind );
})( jQuery );
