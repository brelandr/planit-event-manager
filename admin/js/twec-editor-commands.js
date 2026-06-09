/**
 * Block editor command palette + client-side abilities (WordPress 7.0).
 * Prefers @wordpress/core-abilities when registered; falls back to wp.commands.
 */
( function( wp, cfg ) {
	'use strict';
	if ( ! cfg || ! cfg.newEventUrl ) {
		return;
	}
	if ( ! wp ) {
		return;
	}

	var closePalette = function( args ) {
		return ( args && args.close ) ? args.close : function() {};
	};

	var weekBounds = function() {
		var now = new Date();
		var day = now.getDay();
		var monday = new Date( now );
		monday.setDate( now.getDate() - ( ( day + 6 ) % 7 ) );
		monday.setHours( 0, 0, 0, 0 );
		var sunday = new Date( monday );
		sunday.setDate( monday.getDate() + 6 );
		var pad = function( n ) {
			return n < 10 ? '0' + n : String( n );
		};
		var fmt = function( d ) {
			return d.getFullYear() + '-' + pad( d.getMonth() + 1 ) + '-' + pad( d.getDate() );
		};
		return { after: fmt( monday ), before: fmt( sunday ) };
	};

	var listEventsThisWeek = function( closeFn ) {
		if ( ! wp.apiFetch || ! cfg.restRoot ) {
			if ( cfg.eventsArchiveUrl ) {
				window.location.assign( cfg.eventsArchiveUrl );
			}
			closeFn();
			return;
		}
		var bounds = weekBounds();
		var path = '/wp/v2/twec_event?per_page=20&twec_after=' + encodeURIComponent( bounds.after ) + '&twec_before=' + encodeURIComponent( bounds.before );
		wp.apiFetch( { path: path } ).then( function( posts ) {
			if ( ! posts || ! posts.length ) {
				window.alert( cfg.weekEmptyLabel || 'No events this week.' );
				closeFn();
				return;
			}
			var lines = posts.map( function( p ) {
				return ( p.title && p.title.rendered ) ? p.title.rendered : ( '#' + p.id );
			} );
			window.alert( ( cfg.weekListLabel || 'Events this week:' ) + '\n\n' + lines.join( '\n' ) );
			closeFn();
		} ).catch( function() {
			if ( cfg.eventsArchiveUrl ) {
				window.location.assign( cfg.eventsArchiveUrl );
			}
			closeFn();
		} );
	};

	var openNewEvent = function( closeFn ) {
		window.location.assign( cfg.newEventUrl );
		closeFn();
	};

	var aiDraftFromPrompt = function( closeFn ) {
		var promptText = window.prompt( cfg.aiPromptLabel || 'Describe your event (title, date, venue):' );
		if ( ! promptText || ! String( promptText ).trim() ) {
			closeFn();
			return;
		}
		if ( wp.apiFetch ) {
			wp.apiFetch( {
				path: '/planit/v1/ai/create-from-text',
				method: 'POST',
				data: { text: String( promptText ).trim() },
			} ).then( function( res ) {
				if ( res && res.edit_link ) {
					window.location.assign( res.edit_link );
				} else {
					window.location.assign( cfg.newEventUrl );
				}
				closeFn();
			} ).catch( function() {
				var url = cfg.newEventUrl + ( cfg.newEventUrl.indexOf( '?' ) >= 0 ? '&' : '?' ) + 'twec_ai_seed=' + encodeURIComponent( String( promptText ).trim() );
				window.location.assign( url );
				closeFn();
			} );
			return;
		}
		var url = cfg.newEventUrl + ( cfg.newEventUrl.indexOf( '?' ) >= 0 ? '&' : '?' ) + 'twec_ai_seed=' + encodeURIComponent( String( promptText ).trim() );
		window.location.assign( url );
		closeFn();
	};

	var registerCommands = function() {
		if ( ! wp.data || typeof wp.data.dispatch !== 'function' ) {
			return;
		}
		var d = null;
		if ( wp.commands && wp.commands.store ) {
			d = wp.data.dispatch( wp.commands.store );
		}
		if ( ! d ) {
			try {
				d = wp.data.dispatch( 'core/commands' );
			} catch ( e ) {
				d = null;
			}
		}
		if ( ! d || typeof d.registerCommand !== 'function' ) {
			return;
		}

		d.registerCommand( {
			name: 'planit/open-new-event',
			label: cfg.addLabel,
			searchLabel: cfg.addLabel,
			category: 'view',
			callback: function( args ) {
				openNewEvent( closePalette( args ) );
			}
		} );

		d.registerCommand( {
			name: 'planit/list-events-this-week',
			label: cfg.weekCommandLabel,
			searchLabel: cfg.weekCommandLabel,
			category: 'view',
			callback: function( args ) {
				listEventsThisWeek( closePalette( args ) );
			}
		} );

		if ( cfg.aiAssistEnabled ) {
			d.registerCommand( {
				name: 'planit/ai-draft-from-prompt',
				label: cfg.aiDraftLabel,
				searchLabel: cfg.aiDraftLabel,
				category: 'view',
				callback: function( args ) {
					aiDraftFromPrompt( closePalette( args ) );
				}
			} );
		}

		if ( cfg.canManage && cfg.settingsUrl ) {
			d.registerCommand( {
				name: 'planit/twec-settings',
				label: cfg.settingsLabel,
				searchLabel: cfg.settingsLabel,
				category: 'view',
				callback: function( args ) {
					var c2 = closePalette( args );
					window.location.assign( cfg.settingsUrl );
					c2();
				}
			} );
		}
	};

	var registerClientAbilities = function() {
		var api = wp.coreAbilities;
		if ( ! api || typeof api.registerAbility !== 'function' ) {
			registerCommands();
			return;
		}

		api.registerAbility( 'planit/open-new-event', {
			label: cfg.addLabel,
			callback: function() {
				openNewEvent( function() {} );
			}
		} );

		api.registerAbility( 'planit/list-events-this-week', {
			label: cfg.weekCommandLabel,
			callback: function() {
				listEventsThisWeek( function() {} );
			}
		} );

		if ( cfg.aiAssistEnabled ) {
			api.registerAbility( 'planit/ai-draft-from-prompt', {
				label: cfg.aiDraftLabel,
				callback: function() {
					aiDraftFromPrompt( function() {} );
				}
			} );
		}
	};

	registerClientAbilities();
} )( window.wp, window.twecEditorCommands );
