/**
 * Block editor command palette: PlanIt R&D (see docs/WP-Collaboration-RD.md).
 * Requires: wp.data, core/commands (WordPress 6.4+ when Command Palette is available).
 */
( function( wp, cfg ) {
	'use strict';
	if ( ! cfg || ! cfg.newEventUrl ) {
		return;
	}
	if ( ! wp || ! wp.data || typeof wp.data.dispatch !== 'function' ) {
		return;
	}

	var getDispatch = function() {
		if ( wp.commands && wp.commands.store && typeof wp.data.dispatch === 'function' ) {
			return wp.data.dispatch( wp.commands.store );
		}
		try {
			return wp.data.dispatch( 'core/commands' );
		} catch ( e ) {
			return null;
		}
	};

	var d = getDispatch();
	if ( ! d || typeof d.registerCommand !== 'function' ) {
		return;
	}

	d.registerCommand( {
		name: 'planit/twec-add-event',
		label: cfg.addLabel,
		searchLabel: cfg.addLabel,
		category: 'view',
		callback: function( args ) {
			var c = ( args && args.close ) ? args.close : function() {};
			window.location.assign( cfg.newEventUrl );
			c();
		}
	} );

	if ( cfg.canManage && cfg.settingsUrl ) {
		d.registerCommand( {
			name: 'planit/twec-settings',
			label: cfg.settingsLabel,
			searchLabel: cfg.settingsLabel,
			category: 'view',
			callback: function( args ) {
				var c2 = ( args && args.close ) ? args.close : function() {};
				window.location.assign( cfg.settingsUrl );
				c2();
			}
		} );
	}
} )( window.wp, window.twecEditorCommands );
