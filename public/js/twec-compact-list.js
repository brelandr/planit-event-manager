/**
 * Compact event list: accessible modal preview (optional).
 */
( function() {
	'use strict';

	var cfg = typeof window.twecCompactList === 'object' && window.twecCompactList !== null ? window.twecCompactList : {};
	var i18n = cfg.i18n || {};
	var restRoot = typeof cfg.restRoot === 'string' ? cfg.restRoot : '';

	function qs( el, sel ) {
		return el ? el.querySelector( sel ) : null;
	}

	function openDialog( wrapper, trigger ) {
		var dialogId = wrapper.getAttribute( 'data-dialog-id' );
		if ( ! dialogId ) {
			return;
		}
		var dialog = document.getElementById( dialogId );
		if ( ! dialog ) {
			return;
		}

		var title = trigger.getAttribute( 'data-event-title' ) || '';
		var date = trigger.getAttribute( 'data-event-date' ) || '';
		var category = trigger.getAttribute( 'data-event-category' ) || '';
		var excerpt = trigger.getAttribute( 'data-event-excerpt' ) || '';
		var url = trigger.getAttribute( 'data-event-url' ) || '#';
		var eventId = trigger.getAttribute( 'data-event-id' ) || '';

		var titleEl = qs( dialog, '.twec-compact-list-dialog__title' );
		var metaEl = qs( dialog, '.twec-compact-list-dialog__meta' );
		var bodyEl = qs( dialog, '.twec-compact-list-dialog__body' );
		var linkEl = qs( dialog, '.twec-compact-list-dialog__link' );

		if ( titleEl ) {
			titleEl.textContent = title;
		}
		if ( metaEl ) {
			var parts = [];
			if ( date ) {
				parts.push( date );
			}
			if ( category && category !== '—' ) {
				parts.push( category );
			}
			metaEl.textContent = parts.join( ' · ' );
		}
		if ( bodyEl ) {
			bodyEl.innerHTML = excerpt ? '<p>' + excerpt + '</p>' : '<p class="twec-compact-list-dialog__loading">' + ( i18n.loading || 'Loading…' ) + '</p>';
		}
		if ( linkEl ) {
			linkEl.setAttribute( 'href', url );
		}

		dialog.removeAttribute( 'hidden' );
		dialog.setAttribute( 'aria-hidden', 'false' );
		document.body.classList.add( 'twec-compact-list-dialog-open' );

		var closeBtn = qs( dialog, '.twec-compact-list-dialog__close' );
		if ( closeBtn ) {
			closeBtn.focus();
		}

		if ( eventId && restRoot && bodyEl ) {
			var fetchUrl = restRoot + 'twec_event/' + encodeURIComponent( eventId ) + '?_fields=excerpt,content';
			fetch( fetchUrl, { credentials: 'same-origin' } )
				.then( function( res ) {
					if ( ! res.ok ) {
						throw new Error( 'rest' );
					}
					return res.json();
				} )
				.then( function( data ) {
					if ( ! bodyEl || dialog.getAttribute( 'aria-hidden' ) === 'true' ) {
						return;
					}
					var html = '';
					if ( data && data.excerpt && data.excerpt.rendered ) {
						html += data.excerpt.rendered;
					}
					if ( data && data.content && data.content.rendered ) {
						html += data.content.rendered;
					}
					bodyEl.innerHTML = html || '<p>' + ( excerpt || '' ) + '</p>';
				} )
				.catch( function() {
					if ( bodyEl && dialog.getAttribute( 'aria-hidden' ) !== 'true' ) {
						if ( ! excerpt ) {
							bodyEl.innerHTML = '<p>' + ( i18n.error || 'Could not load this event.' ) + '</p>';
						}
					}
				} );
		}
	}

	function closeDialog( dialog ) {
		if ( ! dialog ) {
			return;
		}
		dialog.setAttribute( 'hidden', 'hidden' );
		dialog.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( 'twec-compact-list-dialog-open' );
	}

	function onClick( e ) {
		var target = e.target;
		if ( ! target || ! target.closest ) {
			return;
		}
		var closeEl = target.closest( '[data-twec-compact-close]' );
		if ( closeEl ) {
			var dlg = closeEl.closest( '.twec-compact-list-dialog' );
			closeDialog( dlg );
			return;
		}
		var trigger = target.closest( '.twec-compact-list-trigger' );
		if ( trigger ) {
			e.preventDefault();
			var wrap = trigger.closest( '.twec-compact-list-wrapper' );
			if ( wrap ) {
				openDialog( wrap, trigger );
			}
		}
	}

	function onKeydown( e ) {
		if ( 'Escape' !== e.key ) {
			return;
		}
		var open = document.querySelector( '.twec-compact-list-dialog[aria-hidden="false"]' );
		if ( open ) {
			e.preventDefault();
			closeDialog( open );
		}
	}

	document.addEventListener( 'click', onClick );
	document.addEventListener( 'keydown', onKeydown );
}() );
