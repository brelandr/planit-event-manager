/**
 * Classic editor AI assist metabox (server-side REST).
 */
( function( $ ) {
	'use strict';

	var $preview = $( '#twec-ai-classic-preview' );
	if ( ! $preview.length ) {
		return;
	}

	var $spinner = $( '.twec-ai-classic-spinner' );
	var $accept = $( '.twec-ai-classic-accept' );
	var $regen = $( '.twec-ai-classic-regenerate' );
	var $last = $( '#twec-ai-classic-last-endpoint' );
	var $excerpt = $( '#twec-ai-classic-excerpt' );
	var $acceptBody = $( '#twec-ai-classic-accept-body' );

	function restRoot() {
		if ( window.wpApiSettings && window.wpApiSettings.root ) {
			return window.wpApiSettings.root;
		}
		return '';
	}

	function run( endpoint ) {
		var postId = parseInt( $( '#twec-ai-classic-post-id' ).val(), 10 ) || 0;
		var nonce = $( '#twec-ai-classic-nonce' ).val();
		if ( postId < 1 ) {
			window.alert( 'Save the event draft first.' );
			return;
		}
		$spinner.addClass( 'is-active' );
		$accept.prop( 'disabled', true );
		$regen.prop( 'disabled', true );
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
			if ( 'publish-prep' === endpoint && res ) {
				var lines = [];
				if ( res.summary ) {
					lines.push( res.summary );
				}
				if ( res.checks && res.checks.length ) {
					res.checks.forEach( function( row ) {
						if ( row.field && row.message ) {
							lines.push( row.field + ': ' + row.message );
						}
					} );
				}
				if ( res.description ) {
					lines.push( '\n' + ( res.description || '' ) );
				}
				text = lines.join( '\n' );
				$acceptBody.val( res.description || '' );
				$excerpt.val( res.excerpt || '' );
				$accept.prop( 'disabled', false );
			} else if ( 'draft-description' === endpoint && res ) {
				text = res.description || '';
				$acceptBody.val( text );
				$excerpt.val( res.excerpt || '' );
				$accept.prop( 'disabled', false );
			} else if ( 'social-snippet' === endpoint && res ) {
				text = res.snippet || '';
			} else if ( 'suggest-taxonomy' === endpoint && res ) {
				var lines = [];
				if ( res.categories && res.categories.length ) {
					lines.push( 'Categories: ' + res.categories.join( ', ' ) );
				}
				if ( res.tags && res.tags.length ) {
					lines.push( 'Tags: ' + res.tags.join( ', ' ) );
				}
				text = lines.join( '\n' );
			}
			$preview.val( text );
			$last.val( endpoint );
			$regen.prop( 'disabled', false );
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

	function setEditorContent( html ) {
		if ( typeof window.tinymce !== 'undefined' ) {
			var ed = window.tinymce.get( 'content' );
			if ( ed && ! ed.isHidden() ) {
				ed.setContent( html || '' );
				return;
			}
		}
		$( '#content' ).val( html || '' );
	}

	$( '.twec-ai-classic-run' ).on( 'click', function() {
		run( $( this ).data( 'endpoint' ) );
	} );

	$regen.on( 'click', function() {
		var ep = $last.val();
		if ( ep ) {
			run( ep );
		}
	} );

	$accept.on( 'click', function() {
		var last = $last.val();
		if ( 'draft-description' !== last && 'publish-prep' !== last ) {
			return;
		}
		var body = 'publish-prep' === last ? ( $acceptBody.val() || $preview.val() ) : $preview.val();
		setEditorContent( body );
		var ex = $excerpt.val();
		if ( ex ) {
			$( '#excerpt' ).val( ex );
		}
		$preview.val( '' );
		$acceptBody.val( '' );
		$excerpt.val( '' );
		$accept.prop( 'disabled', true );
		$regen.prop( 'disabled', true );
	} );

	$( '.twec-ai-classic-discard' ).on( 'click', function() {
		$preview.val( '' );
		$acceptBody.val( '' );
		$excerpt.val( '' );
		$last.val( '' );
		$accept.prop( 'disabled', true );
		$regen.prop( 'disabled', true );
	} );

	// Command palette seed on new/classic screens.
	try {
		var params = new URLSearchParams( window.location.search );
		var seed = params.get( 'twec_ai_seed' );
		if ( seed ) {
			$( '#title' ).val( String( seed ).trim().substring( 0, 200 ) );
		}
	} catch ( e ) {
		// Ignore.
	}
}( window.jQuery ) );
