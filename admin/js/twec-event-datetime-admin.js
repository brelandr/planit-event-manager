/**
 * Classic Event Data metabox: inline validation for twec_* date/time controls.
 *
 * Mirrors {@see TWEC_Event_Datetime} rules on the admin post screen.
 */
( function ( $ ) {
	'use strict';

	var txt =
		typeof planitTwecEventValidation === 'object' && planitTwecEventValidation
			? planitTwecEventValidation
			: {};

	function normalizeTime( raw, fallback ) {
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

	function showInline( visible, msg ) {
		var $box = $( '#twec-event-datetime-inline-notice' );
		if ( ! $box.length ) {
			return;
		}
		if ( ! visible ) {
			$box.hide().find( 'p' ).first().text( '' );
			return;
		}
		$box.show().find( 'p' ).first().text( msg || '' );
	}

	function parseAllDay() {
		var cb = document.getElementById( 'twec_all_day' );
		return !!( cb && cb.checked );
	}

	function validate() {
		var sd = $.trim(
			String( $( '#twec_start_date' ).val() || '' )
		);
		var ed = $.trim(
			String( $( '#twec_end_date' ).val() || '' )
		);
		if ( ! sd || ! ed ) {
			showInline( false );
			return;
		}
		if (
			! /^\d{4}-\d{2}-\d{2}$/.test( sd ) ||
			! /^\d{4}-\d{2}-\d{2}$/.test( ed )
		) {
			showInline(
				true,
				String( txt.badDates || '' ) ||
					'Start and end dates must use Y-m-d.'
			);
			return;
		}

		var allDay = parseAllDay();
		var startT = allDay ? '00:00:00' : normalizeTime(
			$( '#twec_start_time' ).val(),
			'00:00:00'
		);
		var endT = allDay ? '23:59:59' : normalizeTime(
			$( '#twec_end_time' ).val(),
			'23:59:59'
		);

		var startDt = sd + ' ' + startT;
		var endDt = ed + ' ' + endT;

		if (
			window.Date.parse( startDt.replace( ' ', 'T' ) ) >
			window.Date.parse( endDt.replace( ' ', 'T' ) )
		) {
			showInline(
				true,
				String( txt.invalidRange || '' ) ||
					'End must be on or after the start.'
			);
			return;
		}
		showInline( false );
	}

	$( function () {
		if ( ! $( '.twec-event-details-meta-box' ).length ) {
			return;
		}
		$( document ).on(
			'change input blur',
			'#twec_all_day, #twec_start_date, #twec_end_date, #twec_start_time, #twec_end_time',
			function () {
				validate();
			}
		);
		validate();
	} );
})( jQuery );
