/**
 * Venue / organizer AI assist metabox.
 */
( function( $ ) {
	'use strict';

	var $preview = $( '#twec-entity-ai-preview' );
	if ( ! $preview.length ) {
		return;
	}

	var $spinner = $( '.twec-entity-ai-spinner' );
	var $accept = $( '.twec-entity-ai-accept' );

	function restRoot() {
		if ( window.wpApiSettings && window.wpApiSettings.root ) {
			return window.wpApiSettings.root;
		}
		return '';
	}

	function setEditorContent( html ) {
		if ( typeof window.tinymce !== 'undefined' ) {
			var ed = window.tinymce.get( 'content' );
			if ( ed && ! ed.isHidden() ) {
				ed.setContent( html );
				return;
			}
		}
		var $content = $( '#content' );
		if ( $content.length ) {
			$content.val( html );
		}
	}

	function run( endpoint ) {
		var postId = parseInt( $( '#twec-entity-ai-post-id' ).val(), 10 ) || 0;
		var nonce = $( '#twec-entity-ai-nonce' ).val();
		if ( postId < 1 ) {
			window.alert( 'Save the draft first.' );
			return;
		}
		$spinner.addClass( 'is-active' );
		$accept.prop( 'disabled', true );
		$.ajax( {
			url: restRoot() + 'planit/v1/ai/' + endpoint,
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify( { post_id: postId, nonce: nonce } ),
			beforeSend: function( xhr ) {
				if ( window.wpApiSettings && window.wpApiSettings.nonce ) {
					xhr.setRequestHeader( 'X-WP-Nonce', window.wpApiSettings.nonce );
				}
			},
		} ).done( function( res ) {
			var text = '';
			if ( res ) {
				text = res.description || res.bio || '';
			}
			$preview.val( text );
			$accept.prop( 'disabled', ! text );
		} ).fail( function( xhr ) {
			var msg = 'AI request failed.';
			if ( xhr.responseJSON && xhr.responseJSON.message ) {
				msg = xhr.responseJSON.message;
			}
			$preview.val( msg );
		} ).always( function() {
			$spinner.removeClass( 'is-active' );
		} );
	}

	$( '.twec-entity-ai-run' ).on( 'click', function() {
		var endpoint = $( this ).data( 'endpoint' );
		if ( endpoint ) {
			run( endpoint );
		}
	} );

	$accept.on( 'click', function() {
		var html = $preview.val();
		if ( html ) {
			setEditorContent( html );
		}
	} );

	$( '.twec-entity-ai-discard' ).on( 'click', function() {
		$preview.val( '' );
		$accept.prop( 'disabled', true );
	} );
}( jQuery ) );
