/**
 * Block editor: PlanIt calendar + event list (free / org plugin).
 */
( function( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var RangeControl = wp.components.RangeControl;
	var ToggleControl = wp.components.ToggleControl;
	var TextControl = wp.components.TextControl;
	var Notice = wp.components.Notice;
	var cfg = typeof window.planitTwecBlocksCore === 'object' && window.planitTwecBlocksCore !== null ? window.planitTwecBlocksCore : {};

	var KW = [ 'planit', 'twec', 'events', 'calendar' ];

	function editorPreviewPlaceholder( message ) {
		return el(
			'div',
			{ className: 'twec-block-editor-preview-placeholder', 'aria-hidden': 'true' },
			el( 'p', null, message )
		);
	}

	function dynamicBlockPreview( props, blockName, fallbackMessage ) {
		var SSR = wp.serverSideRender;
		if ( SSR ) {
			return el(
				'div',
				{ className: 'twec-block-editor-ssr-wrap' },
				el( SSR, {
					block: blockName,
					attributes: props.attributes,
				} )
			);
		}
		return editorPreviewPlaceholder( fallbackMessage );
	}

	function calendarEditorPreview( props ) {
		var view = props.attributes.view === 'day' ? 'day' : 'month';
		return dynamicBlockPreview(
			props,
			'planit-event-manager/calendar',
			sprintf(
				/* translators: %s: calendar view slug (month or day). */
				__( 'PlanIt calendar preview (%s view). The interactive calendar renders on the front end.', 'planit-event-manager' ),
				view
			)
		);
	}

	function eventListEditorPreview( props ) {
		var per = props.attributes.perPage;
		if ( typeof per !== 'number' || isNaN( per ) || per < 1 ) {
			per = 10;
		}
		return dynamicBlockPreview(
			props,
			'planit-event-manager/event-list',
			sprintf(
				/* translators: %d: number of events per page. */
				__( 'PlanIt event list preview (%d per page). The list renders on the front end.', 'planit-event-manager' ),
				per
			)
		);
	}

	function compactListEditorPreview( props ) {
		var per = props.attributes.perPage;
		if ( typeof per !== 'number' || isNaN( per ) || per < 1 ) {
			per = 25;
		}
		return dynamicBlockPreview(
			props,
			'planit-event-manager/compact-event-list',
			sprintf(
				/* translators: %d: number of events per page. */
				__( 'PlanIt compact event list preview (%d per page). The table renders on the front end.', 'planit-event-manager' ),
				per
			)
		);
	}

	function ticketsOptions() {
		return [
			{ label: __( 'Default (follow global / shortcode rules)', 'planit-event-manager' ), value: '' },
			{ label: __( 'Show ticket CTAs (yes)', 'planit-event-manager' ), value: 'yes' },
			{ label: __( 'Hide ticket CTAs (no)', 'planit-event-manager' ), value: 'no' },
		];
	}

	function reqNotice( show, message ) {
		if ( ! show || ! Notice ) {
			return null;
		}
		return el(
			Notice,
			{ status: 'warning', isDismissible: false },
			message
		);
	}

	registerBlockType( 'planit-event-manager/calendar', {
		apiVersion: 2,
		title: __( 'PlanIt Calendar', 'planit-event-manager' ),
		icon: 'calendar-alt',
		category: 'widgets',
		keywords: KW,
		description: __( 'Embed the event calendar (twec_calendar).', 'planit-event-manager' ),
		attributes: {
			view: { type: 'string', default: 'month' },
			enableInteractivity: { type: 'boolean', default: true },
			categorySlug: { type: 'string', default: '' },
			tagSlug: { type: 'string', default: '' },
			ticketsMode: { type: 'string', default: '' },
		},
		supports: { html: false, align: true },
		edit: function( props ) {
			var blockProps = useBlockProps( {
				className: 'twec-block-calendar',
				style: { maxWidth: '100%' },
			} );
			var showTicketHint = false;
			if ( props.attributes.ticketsMode === 'yes' && ( ! cfg.hasWooCommerce || ! cfg.woocommerceTicketsOn ) ) {
				showTicketHint = true;
			}
			return el( 'div', blockProps,
				reqNotice(
					showTicketHint,
					__( 'Ticket CTAs need WooCommerce and WooCommerce ticket sales enabled under PlanIt settings, or the front end may not show buy buttons.', 'planit-event-manager' )
				),
				el( InspectorControls, {},
					el( PanelBody, { title: __( 'Calendar', 'planit-event-manager' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'View', 'planit-event-manager' ),
							value: props.attributes.view,
							onChange: function( v ) { props.setAttributes( { view: v } ); },
							options: [
								{ label: __( 'Month', 'planit-event-manager' ), value: 'month' },
								{ label: __( 'Day', 'planit-event-manager' ), value: 'day' },
							],
						} ),
						el( ToggleControl, {
							label: __( 'Interactivity API (month navigation)', 'planit-event-manager' ),
							checked: false !== props.attributes.enableInteractivity,
							onChange: function( v ) { props.setAttributes( { enableInteractivity: v } ); },
						} ),
						el( TextControl, {
							label: __( 'Category slug (optional)', 'planit-event-manager' ),
							value: props.attributes.categorySlug,
							onChange: function( v ) { props.setAttributes( { categorySlug: v } ); },
							help: __( 'Filter the calendar to one event category slug.', 'planit-event-manager' ),
						} ),
						el( TextControl, {
							label: __( 'Tag slug (optional)', 'planit-event-manager' ),
							value: props.attributes.tagSlug,
							onChange: function( v ) { props.setAttributes( { tagSlug: v } ); },
						} ),
						el( SelectControl, {
							label: __( 'WooCommerce ticket buttons', 'planit-event-manager' ),
							value: props.attributes.ticketsMode,
							options: ticketsOptions(),
							onChange: function( v ) { props.setAttributes( { ticketsMode: v } ); },
						} )
					),
				),
				calendarEditorPreview( props )
			);
		},
		save: function() {
			return null;
		},
	} );

	registerBlockType( 'planit-event-manager/event-list', {
		apiVersion: 2,
		title: __( 'PlanIt Event List', 'planit-event-manager' ),
		icon: 'list-view',
		category: 'widgets',
		keywords: [ 'planit', 'twec', 'events', 'list' ],
		description: __( 'Chronological list of events (twec_list).', 'planit-event-manager' ),
		attributes: {
			perPage: { type: 'number', default: 10 },
			pastEvents: { type: 'string', default: 'hide' },
			categorySlug: { type: 'string', default: '' },
			tagSlug: { type: 'string', default: '' },
			ticketsMode: { type: 'string', default: '' },
		},
		supports: { html: false, align: true },
		edit: function( props ) {
			var blockProps = useBlockProps( {
				className: 'twec-block-list',
				style: { maxWidth: '100%' },
			} );
			var showTicketHint = false;
			if ( props.attributes.ticketsMode === 'yes' && ( ! cfg.hasWooCommerce || ! cfg.woocommerceTicketsOn ) ) {
				showTicketHint = true;
			}
			return el( 'div', blockProps,
				reqNotice(
					showTicketHint,
					__( 'Ticket CTAs need WooCommerce and WooCommerce ticket sales enabled under PlanIt settings, or the front end may not show buy buttons.', 'planit-event-manager' )
				),
				el( InspectorControls, {},
					el( PanelBody, { title: __( 'List', 'planit-event-manager' ), initialOpen: true },
						el( RangeControl, {
							label: __( 'Events per page', 'planit-event-manager' ),
							value: props.attributes.perPage,
							onChange: function( v ) { props.setAttributes( { perPage: v } ); },
							min: 1,
							max: 50,
						} ),
						el( SelectControl, {
							label: __( 'Past events', 'planit-event-manager' ),
							value: props.attributes.pastEvents,
							onChange: function( v ) { props.setAttributes( { pastEvents: v } ); },
							options: [
								{ label: __( 'Hide', 'planit-event-manager' ), value: 'hide' },
								{ label: __( 'Show', 'planit-event-manager' ), value: 'show' },
							],
						} ),
						el( TextControl, {
							label: __( 'Category slug (optional)', 'planit-event-manager' ),
							value: props.attributes.categorySlug,
							onChange: function( v ) { props.setAttributes( { categorySlug: v } ); },
						} ),
						el( TextControl, {
							label: __( 'Tag slug (optional)', 'planit-event-manager' ),
							value: props.attributes.tagSlug,
							onChange: function( v ) { props.setAttributes( { tagSlug: v } ); },
						} ),
						el( SelectControl, {
							label: __( 'WooCommerce ticket buttons', 'planit-event-manager' ),
							value: props.attributes.ticketsMode,
							options: ticketsOptions(),
							onChange: function( v ) { props.setAttributes( { ticketsMode: v } ); },
						} )
					),
				),
				eventListEditorPreview( props )
			);
		},
		save: function() {
			return null;
		},
	} );

	registerBlockType( 'planit-event-manager/compact-event-list', {
		apiVersion: 2,
		title: __( 'PlanIt Compact Event List', 'planit-event-manager' ),
		icon: 'editor-table',
		category: 'widgets',
		keywords: [ 'planit', 'twec', 'events', 'list', 'table', 'compact' ],
		description: __( 'Dense table of events with date, title, and category (twec_compact_list).', 'planit-event-manager' ),
		attributes: {
			perPage: { type: 'number', default: 25 },
			pastEvents: { type: 'string', default: 'hide' },
			categorySlug: { type: 'string', default: '' },
			tagSlug: { type: 'string', default: '' },
			linkBehavior: { type: 'string', default: 'modal' },
			enableInteractivity: { type: 'boolean', default: true },
		},
		supports: { html: false, align: true },
		edit: function( props ) {
			var blockProps = useBlockProps( {
				className: 'twec-block-compact-list',
				style: { maxWidth: '100%' },
			} );
			return el( 'div', blockProps,
				el( InspectorControls, {},
					el( PanelBody, { title: __( 'Compact list', 'planit-event-manager' ), initialOpen: true },
						el( RangeControl, {
							label: __( 'Events per page', 'planit-event-manager' ),
							value: props.attributes.perPage,
							onChange: function( v ) { props.setAttributes( { perPage: v } ); },
							min: 1,
							max: 100,
						} ),
						el( SelectControl, {
							label: __( 'Past events', 'planit-event-manager' ),
							value: props.attributes.pastEvents,
							onChange: function( v ) { props.setAttributes( { pastEvents: v } ); },
							options: [
								{ label: __( 'Hide', 'planit-event-manager' ), value: 'hide' },
								{ label: __( 'Show', 'planit-event-manager' ), value: 'show' },
							],
						} ),
						el( SelectControl, {
							label: __( 'Click behavior', 'planit-event-manager' ),
							value: props.attributes.linkBehavior,
							onChange: function( v ) { props.setAttributes( { linkBehavior: v } ); },
							options: [
								{ label: __( 'Open preview popup', 'planit-event-manager' ), value: 'modal' },
								{ label: __( 'Go to event page', 'planit-event-manager' ), value: 'page' },
							],
							help: __( 'Popup shows a quick preview with a link to the full event page.', 'planit-event-manager' ),
						} ),
						el( TextControl, {
							label: __( 'Category slug (optional)', 'planit-event-manager' ),
							value: props.attributes.categorySlug,
							onChange: function( v ) { props.setAttributes( { categorySlug: v } ); },
						} ),
						el( TextControl, {
							label: __( 'Tag slug (optional)', 'planit-event-manager' ),
							value: props.attributes.tagSlug,
							onChange: function( v ) { props.setAttributes( { tagSlug: v } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Enhanced modal preview', 'planit-event-manager' ),
							checked: false !== props.attributes.enableInteractivity,
							onChange: function( v ) { props.setAttributes( { enableInteractivity: v } ); },
							help: __( 'Loads full event details in the compact list popup when enabled.', 'planit-event-manager' ),
						} )
					),
				),
				compactListEditorPreview( props )
			);
		},
		save: function() {
			return null;
		},
	} );

	registerBlockType( 'planit-event-manager/event-assistant', {
		apiVersion: 2,
		title: __( 'PlanIt Event Assistant', 'planit-event-manager' ),
		icon: 'format-chat',
		category: 'widgets',
		keywords: [ 'planit', 'twec', 'events', 'ai', 'assistant', 'chat' ],
		description: __( 'Visitor-facing event finder powered by site AI (opt-in in Events → Settings → AI).', 'planit-event-manager' ),
		attributes: {
			heading: { type: 'string', default: __( 'Ask about upcoming events', 'planit-event-manager' ) },
			days: { type: 'number', default: 14 },
		},
		supports: { html: false, align: true },
		edit: function( props ) {
			var blockProps = useBlockProps( {
				className: 'twec-block-event-assistant',
				style: { maxWidth: '100%' },
			} );
			return el( 'div', blockProps,
				el( InspectorControls, {},
					el( PanelBody, { title: __( 'Event assistant', 'planit-event-manager' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Heading', 'planit-event-manager' ),
							value: props.attributes.heading,
							onChange: function( v ) { props.setAttributes( { heading: v } ); },
						} ),
						el( RangeControl, {
							label: __( 'Days of context', 'planit-event-manager' ),
							value: props.attributes.days,
							onChange: function( v ) { props.setAttributes( { days: v } ); },
							min: 1,
							max: 90,
							help: __( 'How many upcoming days of events are sent to the AI for grounding.', 'planit-event-manager' ),
						} )
					),
				),
				el( 'div', { className: 'twec-event-assistant twec-event-assistant--editor-preview' },
					el( 'h3', { className: 'twec-event-assistant__heading' }, props.attributes.heading || __( 'Ask about upcoming events', 'planit-event-manager' ) ),
					el( 'p', { className: 'description' }, __( 'Preview on the front end when the public assistant is enabled in settings.', 'planit-event-manager' ) )
				)
			);
		},
		save: function() {
			return null;
		},
	} );
}( window.wp ) );
