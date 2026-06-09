/**
 * Block editor: push classic metabox values into REST `meta` so updates persist.
 * Classic `save_post` handlers that rely on $_POST/nonces do not run on REST saves.
 *
 * Covers: Event Data, Custom Fields, Featured, RSVP capacity, and Recurring options.
 */
( function () {
	'use strict';

	if ( typeof window.wp === 'undefined' || ! window.wp.data || ! window.wp.domReady ) {
		return;
	}

	var domReady = window.wp.domReady;
	var dispatch = window.wp.data.dispatch;
	var select = window.wp.data.select;

	function isDebug() {
		var c = window.planitTwecMetaSync;
		return !!( c && c.debug );
	}

	/**
	 * Normalize time input (type=time often returns HH:MM) to HH:MM:SS like PHP save.
	 *
	 * @param {string} t Raw time.
	 * @return {string}
	 */
	function normalizeTime( t ) {
		t = ( t || '' ).trim();
		if ( ! t ) {
			return '';
		}
		if ( /^\d{2}:\d{2}$/.test( t ) ) {
			return t + ':00';
		}
		return t;
	}

	function validationMsg( key ) {
		var cfg = window.planitTwecMetaSync;
		var bag = cfg && cfg.validationI18n ? cfg.validationI18n : {};
		return bag && bag[ key ] ? String( bag[ key ] ) : '';
	}

	/**
	 * @return {{ ok: boolean, message?: string }}
	 */
	function validateEventRangeDom() {
		var sdEl = document.getElementById( 'twec_start_date' );
		var edEl = document.getElementById( 'twec_end_date' );
		if ( ! sdEl || ! edEl ) {
			return { ok: true };
		}
		var sd = ( sdEl.value || '' ).trim();
		var ed = ( edEl.value || '' ).trim();
		if ( ! sd || ! ed ) {
			return { ok: true };
		}
		if ( ! /^\d{4}-\d{2}-\d{2}$/.test( sd ) || ! /^\d{4}-\d{2}-\d{2}$/.test( ed ) ) {
			return { ok: false, message: validationMsg( 'invalidDates' ) || 'Invalid dates.' };
		}
		var allDay = !! ( document.getElementById( 'twec_all_day' ) && document.getElementById( 'twec_all_day' ).checked );
		var stEl = document.getElementById( 'twec_start_time' );
		var etEl = document.getElementById( 'twec_end_time' );
		var startT = allDay ? '00:00:00' : normalizeTime( stEl && stEl.value ? stEl.value : '' ) || '00:00:00';
		var endT = allDay ? '23:59:59' : normalizeTime( etEl && etEl.value ? etEl.value : '' ) || '23:59:59';
		var startDt = sd + ' ' + startT;
		var endDt = ed + ' ' + endT;
		if ( Date.parse( startDt.replace( ' ', 'T' ) ) > Date.parse( endDt.replace( ' ', 'T' ) ) ) {
			return { ok: false, message: validationMsg( 'invalidRange' ) || 'End must be on or after the start.' };
		}
		return { ok: true };
	}

	function updateBlockEditorNotice( message ) {
		if ( typeof window.wp === 'undefined' || ! window.wp.data || ! window.wp.data.dispatch ) {
			return;
		}
		try {
			var dn = window.wp.data.dispatch( 'core/notices' );
			dn.removeNotice( 'twec-event-datetime-validation' );
			if ( message ) {
				dn.createNotice( 'error', message, {
					id: 'twec-event-datetime-validation',
					isDismissible: true,
				} );
			}
		} catch ( e ) {
			/** ignore */
		}
	}

	/**
	 * @param {EventTarget|null} t Target.
	 * @return {boolean}
	 */
	function shouldWatchElement( t ) {
		if ( ! t || ! t.closest ) {
			return false;
		}
		return !! (
			t.closest( '.twec-event-details-meta-box' ) ||
			t.closest( '#twec_custom_fields' ) ||
			t.closest( '#twec_featured' ) ||
			t.closest( '#twec_recurring' ) ||
			t.closest( '#twec_capacity' )
		);
	}

	/**
	 * Featured event, RSVP capacity, recurrence (Premium / Pro sidebar boxes).
	 *
	 * @param {Object<string, *>} patch Meta patch object.
	 * @return {void}
	 */
	function applyFeaturedCapacityRecurring( patch ) {
		var featured = document.getElementById( 'twec_is_featured' );
		if ( featured ) {
			patch._twec_is_featured = featured.checked ? '1' : '0';
		}

		var cap = document.querySelector( '#twec_capacity input[name="twec_event_capacity"]' );
		if ( cap ) {
			var n = parseInt( cap.value, 10 );
			patch._twec_event_capacity = isNaN( n ) ? 0 : Math.max( 0, n );
		}

		var recCb = document.getElementById( 'twec_is_recurring' );
		if ( ! recCb ) {
			return;
		}

		if ( ! recCb.checked ) {
			patch._twec_is_recurring = '0';
			patch._twec_recurrence_advanced = '0';
			patch._twec_recurrence_type = '';
			patch._twec_recurrence_interval = 1;
			patch._twec_recurrence_end_date = '';
			patch._twec_recurrence_count = 0;
			patch._twec_recurrence_rrule = '';
			patch._twec_recurrence_exdates = '';
			return;
		}

		patch._twec_is_recurring = '1';

		var rtype = document.getElementById( 'twec_recurrence_type' );
		if ( rtype && rtype.value ) {
			var rt = String( rtype.value );
			if ( /^(daily|weekly|monthly|yearly)$/.test( rt ) ) {
				patch._twec_recurrence_type = rt;
			}
		}

		var ivalEl = document.getElementById( 'twec_recurrence_interval' );
		var iv = ivalEl && ivalEl.value ? parseInt( ivalEl.value, 10 ) : 1;
		patch._twec_recurrence_interval = isNaN( iv ) || iv < 1 ? 1 : iv;

		var endModeEl = document.querySelector( 'input[name="twec_recurrence_end"]:checked' );
		var endMode = endModeEl ? String( endModeEl.value ) : 'date';

		if ( endMode === 'count' ) {
			patch._twec_recurrence_end_date = '';
			var cnEl = document.getElementById( 'twec_recurrence_count' );
			var cnv = cnEl && cnEl.value ? parseInt( cnEl.value, 10 ) : 1;
			patch._twec_recurrence_count = isNaN( cnv ) || cnv < 1 ? 1 : cnv;
		} else {
			var edEl = document.getElementById( 'twec_recurrence_end_date' );
			patch._twec_recurrence_end_date = edEl && edEl.value ? String( edEl.value ) : '';
			patch._twec_recurrence_count = 0;
		}

		var advEl = document.getElementById( 'twec_recurrence_advanced_cb' );
		var advOn = !!( advEl && advEl.checked );
		patch._twec_recurrence_advanced = advOn ? '1' : '0';

		if ( advOn ) {
			var rr = document.getElementById( 'twec_recurrence_rrule' );
			var ex = document.getElementById( 'twec_recurrence_exdates' );
			patch._twec_recurrence_rrule = rr ? String( rr.value || '' ) : '';
			patch._twec_recurrence_exdates = ex ? String( ex.value || '' ) : '';
		} else {
			patch._twec_recurrence_rrule = '';
			patch._twec_recurrence_exdates = '';
		}
	}

	/**
	 * Collect _twec_* meta from Event Data + Custom Fields + sidebar metaboxes.
	 *
	 * @return {Object<string, *>}
	 */
	function collectMetaPatch() {
		var patch = {};

		var allDayEl = document.getElementById( 'twec_all_day' );
		patch._twec_event_all_day = allDayEl && allDayEl.checked ? '1' : '0';

		var sd = document.getElementById( 'twec_start_date' );
		var st = document.getElementById( 'twec_start_time' );
		var ed = document.getElementById( 'twec_end_date' );
		var et = document.getElementById( 'twec_end_time' );

		var startDate = sd && sd.value ? sd.value : '';
		var startTimeRaw = st && st.value ? st.value : '';
		var startTime = startTimeRaw ? normalizeTime( startTimeRaw ) : '00:00:00';
		patch._twec_event_start_date = startDate ? startDate + ' ' + startTime : '';
		patch._twec_event_start_time = startTime;

		var endDate = ed && ed.value ? ed.value : '';
		var endTimeRaw = et && et.value ? et.value : '';
		var endTime = endTimeRaw ? normalizeTime( endTimeRaw ) : '23:59:59';
		patch._twec_event_end_date = endDate ? endDate + ' ' + endTime : '';
		patch._twec_event_end_time = endTime;

		var venue = document.getElementById( 'twec_venue' );
		patch._twec_event_venue = venue && venue.value ? parseInt( venue.value, 10 ) || 0 : 0;

		var org = document.getElementById( 'twec_organizer' );
		patch._twec_event_organizer = org && org.value ? parseInt( org.value, 10 ) || 0 : 0;

		var att = document.getElementById( 'twec_event_attendance' );
		if ( att && att.value ) {
			patch._twec_event_attendance = att.value;
		}

		var vurl = document.getElementById( 'twec_event_virtual_url' );
		patch._twec_event_virtual_url = vurl && vurl.value ? vurl.value : '';

		var cost = document.getElementById( 'twec_event_cost' );
		if ( cost && ! cost.disabled ) {
			patch._twec_event_cost = cost.value || '';
		}

		var site = document.getElementById( 'twec_event_website' );
		if ( site && ! site.disabled ) {
			patch._twec_event_website = site.value || '';
		}

		var tz = document.getElementById( 'twec_event_timezone' );
		if ( tz && ! tz.disabled ) {
			patch._twec_event_timezone = tz.value || '';
		}

		applyFeaturedCapacityRecurring( patch );

		/* Custom Fields metabox: twec_custom_fields[slug] */
		var box = document.getElementById( 'twec_custom_fields' );
		if ( box ) {
			var prev = {};
			try {
				var store = select( 'core/editor' );
				if ( store && typeof store.getEditedPostAttribute === 'function' ) {
					var m = store.getEditedPostAttribute( 'meta' );
					if ( m && m._twec_custom_fields && typeof m._twec_custom_fields === 'object' ) {
						prev = Object.assign( {}, m._twec_custom_fields );
					}
				}
			} catch ( e ) {
				prev = {};
			}

			var cf = Object.assign( {}, prev );
			var inside = box.querySelector( '.inside' );
			if ( inside ) {
				var fields = inside.querySelectorAll( 'input, textarea, select' );
				fields.forEach( function ( el ) {
					if ( ! el.name ) {
						return;
					}
					var m = el.name.match( /^twec_custom_fields\[([^\]]+)\]$/ );
					if ( ! m ) {
						return;
					}
					var key = m[ 1 ];
					if ( el.type === 'checkbox' ) {
						cf[ key ] = el.checked ? '1' : '0';
					} else {
						cf[ key ] = el.value != null ? String( el.value ) : '';
					}
				} );
			}
			patch._twec_custom_fields = cf;
		}

		return patch;
	}

	function mergeEditedMeta( patch ) {
		var store = select( 'core/editor' );
		if ( ! store || typeof store.getEditedPostAttribute !== 'function' ) {
			return patch;
		}
		var prev = store.getEditedPostAttribute( 'meta' );
		if ( ! prev || typeof prev !== 'object' ) {
			return patch;
		}
		return Object.assign( {}, prev, patch );
	}

	var timer = null;
	var syncing = false;

	function syncFromDomToEditor() {
		if ( syncing ) {
			return;
		}
		if (
			! document.querySelector( '.twec-event-details-meta-box' ) &&
			! document.getElementById( 'twec_custom_fields' ) &&
			! document.getElementById( 'twec_featured' ) &&
			! document.getElementById( 'twec_recurring' ) &&
			! document.getElementById( 'twec_capacity' )
		) {
			return;
		}
		var vr = validateEventRangeDom();
		if ( ! vr.ok ) {
			updateBlockEditorNotice( vr.message || '' );
			return;
		}
		updateBlockEditorNotice( '' );
		syncing = true;
		try {
			var patch = collectMetaPatch();
			var merged = mergeEditedMeta( patch );
			if ( isDebug() && window.console && console.log ) {
				console.log( '[PlanIt twec] syncing metabox DOM -> editor meta keys:', Object.keys( merged || {} ) );
			}
			dispatch( 'core/editor' ).editPost( { meta: merged } );
		} catch ( e ) {
			if ( window.console && console.warn ) {
				console.warn( 'PlanIt: meta sync failed', e );
			}
		}
		syncing = false;
	}

	function scheduleSync() {
		if ( timer ) {
			window.clearTimeout( timer );
		}
		timer = window.setTimeout( function () {
			timer = null;
			syncFromDomToEditor();
		}, 320 );
	}

	function getMetaBoxMaxHeightVh() {
		var cfg = window.planitTwecMetaSync;
		var vh = cfg && cfg.metaBoxMaxHeightVh ? parseInt( cfg.metaBoxMaxHeightVh, 10 ) : 50;
		if ( isNaN( vh ) || vh < 25 ) {
			vh = 50;
		}
		return vh;
	}

	function eventMetaBoxHasContent( box ) {
		return !! (
			box.querySelector( '.twec-event-details-meta-box' ) ||
			box.querySelector( '#twec_start_date' )
		);
	}

	function ensureMetaBoxesFooterVisible() {
		var selectors = [
			'.edit-post-meta-boxes-area',
			'.edit-post-meta-boxes-main',
			'.edit-post-meta-boxes-main__presenter',
		];
		selectors.forEach( function ( sel ) {
			document.querySelectorAll( sel ).forEach( function ( el ) {
				el.classList.remove( 'is-hidden' );
				el.removeAttribute( 'hidden' );
			} );
		} );
	}

	function ensureEventMetaBoxOpen() {
		ensureMetaBoxesFooterVisible();

		var boxes = document.querySelectorAll( '#twec_event_details' );
		if ( ! boxes.length ) {
			return false;
		}

		var hasContent = false;
		boxes.forEach( function ( box ) {
			box.classList.remove( 'closed' );
			var toggle = box.querySelector( '.handlediv' );
			if ( toggle ) {
				toggle.setAttribute( 'aria-expanded', 'true' );
			}
			var inside = box.querySelector( '.inside' );
			if ( inside ) {
				inside.style.display = 'block';
			}
			if ( eventMetaBoxHasContent( box ) ) {
				hasContent = true;
				box.classList.add( 'twec-event-details-sized' );
			}
		} );

		if ( hasContent ) {
			document.documentElement.style.setProperty(
				'--twec-event-metabox-max-height',
				getMetaBoxMaxHeightVh() + 'vh'
			);
		}

		return hasContent;
	}

	function watchEventMetaBox() {
		var attempts = 0;
		var timer = window.setInterval( function () {
			attempts += 1;
			if ( ensureEventMetaBoxOpen() || attempts > 40 ) {
				window.clearInterval( timer );
			}
		}, 250 );
	}

	domReady( function () {
		watchEventMetaBox();
		if ( isDebug() && window.console && console.log ) {
			console.log( '[PlanIt twec] metabox REST sync initialized' );
		}
		if (
			! document.querySelector( '.twec-event-details-meta-box' ) &&
			! document.getElementById( 'twec_custom_fields' ) &&
			! document.getElementById( 'twec_featured' ) &&
			! document.getElementById( 'twec_recurring' ) &&
			! document.getElementById( 'twec_capacity' )
		) {
			return;
		}

		document.body.addEventListener(
			'input',
			function ( e ) {
				var t = e.target;
				if ( shouldWatchElement( t ) ) {
					scheduleSync();
				}
			},
			true
		);

		document.body.addEventListener(
			'change',
			function ( e ) {
				var t = e.target;
				if ( shouldWatchElement( t ) ) {
					scheduleSync();
				}
			},
			true
		);

		syncFromDomToEditor();

		/* Final flush when a save starts so REST gets the latest metabox values. */
		if ( typeof select === 'function' && typeof window.wp.data.subscribe === 'function' ) {
			var lastSaving = false;
			window.wp.data.subscribe( function () {
				var st = select( 'core/editor' );
				if ( ! st || typeof st.isSavingPost !== 'function' ) {
					return;
				}
				var saving = !! st.isSavingPost();
				if ( saving && ! lastSaving ) {
					syncFromDomToEditor();
				}
				lastSaving = saving;
			} );
		}
	} );
} )();
