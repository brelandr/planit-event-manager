/**
 * Confirm bulk AI Publish prep on the events list screen.
 */
( function( $, cfg ) {
	'use strict';

	if ( ! cfg || ! cfg.confirm ) {
		return;
	}

	$( function() {
		$( '#posts-filter' ).on( 'submit', function( e ) {
			var action = $( 'select[name="action"]' ).val();
			if ( ! action || '-1' === action ) {
				action = $( 'select[name="action2"]' ).val();
			}
			if ( 'twec_ai_bulk_publish_prep' !== action ) {
				return;
			}
			var checked = $( 'input[name="post[]"]:checked' ).length;
			if ( checked < 1 ) {
				return;
			}
			if ( ! window.confirm( cfg.confirm ) ) {
				e.preventDefault();
			}
		} );
	} );
}( window.jQuery, window.twecAiBulkPublishPrep ) );
