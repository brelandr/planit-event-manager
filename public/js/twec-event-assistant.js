/**
 * PlanIt public event assistant (grounded AI queries).
 */
( function() {
	'use strict';

	var cfg = typeof window.twecEventAssistant === 'object' && window.twecEventAssistant !== null ? window.twecEventAssistant : {};
	var restUrl = typeof cfg.restUrl === 'string' ? cfg.restUrl : '';
	var i18n = cfg.i18n || {};

	function qs( root, sel ) {
		return root ? root.querySelector( sel ) : null;
	}

	function init( wrap ) {
		if ( ! wrap || wrap.getAttribute( 'data-twec-assistant-init' ) ) {
			return;
		}
		wrap.setAttribute( 'data-twec-assistant-init', '1' );
		var form = qs( wrap, '.twec-event-assistant__form' );
		var input = qs( wrap, '.twec-event-assistant__input' );
		var out = qs( wrap, '.twec-event-assistant__answer' );
		var list = qs( wrap, '.twec-event-assistant__events' );
		if ( ! form || ! input || ! out ) {
			return;
		}
		form.addEventListener( 'submit', function( e ) {
			e.preventDefault();
			var q = ( input.value || '' ).trim();
			if ( ! q || ! restUrl ) {
				return;
			}
			out.textContent = i18n.loading || 'Thinking…';
			if ( list ) {
				list.innerHTML = '';
			}
			fetch( restUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'same-origin',
				body: JSON.stringify( { query: q, days: 14 } ),
			} )
				.then( function( res ) {
					if ( ! res.ok ) {
						throw new Error( 'rest' );
					}
					return res.json();
				} )
				.then( function( data ) {
					out.innerHTML = data && data.answer ? data.answer : '';
					if ( list && data && data.events && data.events.length ) {
						var ul = document.createElement( 'ul' );
						ul.className = 'twec-event-assistant__event-list';
						data.events.forEach( function( ev ) {
							var li = document.createElement( 'li' );
							var a = document.createElement( 'a' );
							a.href = ev.url || '#';
							a.textContent = ( ev.title || '' ) + ( ev.start_date ? ' — ' + ev.start_date : '' );
							li.appendChild( a );
							ul.appendChild( li );
						} );
						list.appendChild( ul );
					}
				} )
				.catch( function() {
					out.textContent = i18n.error || 'Could not get an answer.';
				} );
		} );
	}

	document.querySelectorAll( '.twec-event-assistant' ).forEach( init );
}() );
