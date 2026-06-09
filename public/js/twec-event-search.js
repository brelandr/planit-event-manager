/**
 * PlanIt natural-language event search block.
 */
( function() {
	'use strict';

	var cfg = typeof window.twecEventSearch === 'object' && window.twecEventSearch !== null ? window.twecEventSearch : {};
	var restUrl = typeof cfg.restUrl === 'string' ? cfg.restUrl : '';
	var i18n = cfg.i18n || {};

	function qs( root, sel ) {
		return root ? root.querySelector( sel ) : null;
	}

	function renderResults( container, events ) {
		if ( ! container ) {
			return;
		}
		container.innerHTML = '';
		if ( ! events || ! events.length ) {
			return;
		}

		var list = document.createElement( 'ul' );
		list.className = 'twec-event-search__list';

		events.forEach( function( ev ) {
			var li = document.createElement( 'li' );
			li.className = 'twec-event-search__item';

			var link = document.createElement( 'a' );
			link.className = 'twec-event-search__title';
			link.href = ev.url || '#';
			link.textContent = ev.title || '';

			li.appendChild( link );

			if ( ev.date_label ) {
				var date = document.createElement( 'span' );
				date.className = 'twec-event-search__date';
				date.textContent = ev.date_label;
				li.appendChild( date );
			}

			if ( ev.venue ) {
				var venue = document.createElement( 'span' );
				venue.className = 'twec-event-search__venue';
				venue.textContent = ev.venue;
				li.appendChild( venue );
			}

			if ( ev.categories && ev.categories.length ) {
				var cats = document.createElement( 'span' );
				cats.className = 'twec-event-search__categories';
				cats.textContent = ev.categories.join( ', ' );
				li.appendChild( cats );
			}

			if ( ev.excerpt ) {
				var excerpt = document.createElement( 'p' );
				excerpt.className = 'twec-event-search__excerpt';
				excerpt.textContent = ev.excerpt;
				li.appendChild( excerpt );
			}

			list.appendChild( li );
		} );

		container.appendChild( list );
	}

	function init( wrap ) {
		if ( ! wrap || wrap.getAttribute( 'data-twec-search-init' ) ) {
			return;
		}
		wrap.setAttribute( 'data-twec-search-init', '1' );

		var form = qs( wrap, '.twec-event-search__form' );
		var input = qs( wrap, '.twec-event-search__input' );
		var summary = qs( wrap, '.twec-event-search__summary' );
		var results = qs( wrap, '.twec-event-search__results' );
		if ( ! form || ! input || ! results ) {
			return;
		}

		form.addEventListener( 'submit', function( e ) {
			e.preventDefault();
			var q = ( input.value || '' ).trim();
			if ( ! q || ! restUrl ) {
				return;
			}

			var days = parseInt( wrap.getAttribute( 'data-days' ) || '60', 10 );
			var limit = parseInt( wrap.getAttribute( 'data-limit' ) || '20', 10 );
			var category = wrap.getAttribute( 'data-category' ) || '';

			if ( summary ) {
				summary.textContent = i18n.loading || 'Searching events…';
			}
			results.innerHTML = '';

			var body = { query: q, days: days, limit: limit };
			if ( category ) {
				body.category = category;
			}

			fetch( restUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'same-origin',
				body: JSON.stringify( body ),
			} )
				.then( function( res ) {
					if ( ! res.ok ) {
						throw new Error( 'rest' );
					}
					return res.json();
				} )
				.then( function( data ) {
					var total = data && typeof data.total === 'number' ? data.total : ( data && data.events ? data.events.length : 0 );
					var summaryText = '';

					if ( data && data.summary ) {
						summaryText = data.summary;
					} else if ( total === 1 ) {
						summaryText = i18n.oneResult || '1 event found';
					} else if ( total > 1 ) {
						var tpl = i18n.results || '%d events found';
						summaryText = tpl.replace( '%d', String( total ) );
					} else {
						summaryText = i18n.empty || 'No matching events found. Try different words.';
					}

					if ( summary ) {
						summary.textContent = summaryText;
					}

					if ( data && data.events && data.events.length ) {
						renderResults( results, data.events );
					}
				} )
				.catch( function() {
					if ( summary ) {
						summary.textContent = i18n.error || 'Search failed. Please try again.';
					}
					results.innerHTML = '';
				} );
		} );
	}

	document.querySelectorAll( '.twec-event-search' ).forEach( init );
}() );
