/**
 * PlanIt Event Manager — Interactivity API store for calendar navigation (WordPress 6.5+).
 *
 * Loaded only when the `wp-interactivity` script is registered. Falls back to twec-public.js otherwise.
 * Compact payload v2: `fetchCalendar` hydrates from `grid` via `window.twecCalendarHtmlFromStructuredGrid`
 * (`twec-calendar-grid-client.js`), bound in place so behaviour matches non-Interactivity `twec-public.js`.
 *
 * @package PlanIt_Event_Manager
 */
import { store } from '@wordpress/interactivity';

/**
 * Format Y-m-d (aligns with classic TWEC.navigate).
 *
 * @param {Date} d Date.
 * @return {string} Y-m-d
 */
function formatYmd( d ) {
	const y = d.getFullYear();
	const m = String( d.getMonth() + 1 ).padStart( 2, '0' );
	const day = String( d.getDate() ).padStart( 2, '0' );
	return `${ y }-${ m }-${ day }`;
}

/**
 * Advance current date by one step for the active view (direction: -1 | 1).
 *
 * @param {string} view View slug.
 * @param {string} dateStr Y-m-d.
 * @param {number} direction -1 or 1.
 * @return {string} New Y-m-d.
 */
function shiftDate( view, dateStr, direction ) {
	const d = new Date( dateStr + 'T12:00:00' );
	const y = d.getFullYear();
	const mo = d.getMonth();
	const day = d.getDate();
	switch ( view ) {
		case 'day':
			d.setDate( day + direction );
			break;
		case 'week':
			d.setDate( day + direction * 7 );
			break;
		case 'month':
			d.setDate( 1 );
			d.setMonth( mo + direction );
			break;
		case 'year':
			d.setFullYear( y + direction, 0, 1 );
			break;
		default:
			d.setDate( 1 );
			d.setMonth( mo + direction );
	}
	return formatYmd( d );
}

/**
 * Sync .active on view buttons with state.view.
 *
 * @param {string} activeView Active view slug.
 */
function syncViewButtons( activeView ) {
	const root = document.querySelector(
		'.twec-calendar-wrapper[data-wp-interactive="planit/calendar"]'
	);
	if ( ! root ) {
		return;
	}
	root.querySelectorAll( '.twec-view-btn' ).forEach( ( btn ) => {
		const v = btn.getAttribute( 'data-view' );
		if ( v === activeView ) {
			btn.classList.add( 'active' );
		} else {
			btn.classList.remove( 'active' );
		}
	} );
}

/**
 * Fetch calendar markup via admin-ajax. Uses compact + calendar_payload_version 2; when payloadVersion >= 2
 * and `grid` is present, HTML is built with `twecCalendarHtmlFromStructuredGrid.bind( window )( grid )`.
 *
 * @param {Object} state Store state.
 */
async function fetchCalendar( state ) {
	state.isLoading = true;
	try {
		const body = new FormData();
		body.append( 'action', 'twec_get_calendar' );
		body.append( 'nonce', state.nonce );
		if ( state.calPub ) {
			body.append( 'cal_pub', state.calPub );
		}
		body.append( 'view', state.view );
		body.append( 'date', state.date );
		body.append( 'ticket_cta', state.ticketCta === '1' || state.ticketCta === 1 || state.ticketCta === true ? '1' : '0' );
		if ( state.category ) {
			body.append( 'category', String( state.category ) );
		}
		if ( state.tag ) {
			body.append( 'tag', String( state.tag ) );
		}
		body.append( 'response_format', 'compact' );
		body.append( 'calendar_payload_version', '2' );

		const res = await fetch( state.ajaxUrl, {
			method: 'POST',
			body,
			credentials: 'same-origin',
		} );
		const json = await res.json();
		if ( json.success && json.data ) {
			if ( json.data.title ) {
				state.title = json.data.title;
			}
			const pv = typeof json.data.payloadVersion !== 'undefined' ? Number( json.data.payloadVersion ) : 1;
			const htmlFromPayload = typeof json.data.html === 'string' ? json.data.html : '';
			let nextHtml = htmlFromPayload;
			if (
				pv >= 2 &&
				json.data.grid
			) {
				const build =
					typeof window !== 'undefined' &&
					typeof window.twecCalendarHtmlFromStructuredGrid === 'function'
						? window.twecCalendarHtmlFromStructuredGrid.bind( window )
						: null;
				const built = build ? build( json.data.grid ) : '';
				if ( built ) {
					nextHtml = built;
				}
			}
			if ( nextHtml ) {
				state.calendarHtml = nextHtml;
			}
			const root = document.querySelector(
				'.twec-calendar-wrapper[data-wp-interactive="planit/calendar"]'
			);
			if ( root ) {
				if ( state.view ) {
					root.setAttribute( 'data-view', String( state.view ) );
				}
				if ( state.date ) {
					root.setAttribute( 'data-current-date', String( state.date ) );
				}
			}
			syncViewButtons( state.view );
			if ( typeof window.twecAfterCalendarLoad === 'function' ) {
				window.twecAfterCalendarLoad( state.view );
			}
		}
	} catch ( e ) {
		if ( typeof console !== 'undefined' && console.error ) {
			console.error( 'PlanIt calendar load failed', e );
		}
	} finally {
		state.isLoading = false;
	}
}

const twecCalendarStoreApis = store( 'planit/calendar', {
	state: {
		title: '',
		calendarHtml: '',
		isLoading: false,
		view: 'month',
		date: '',
		ajaxUrl: '',
		nonce: '',
		calPub: '',
		ticketCta: '0',
		category: '',
		tag: '',
	},
	actions: {
		async loadCalendar() {
			await fetchCalendar( this.state );
		},
		async navigate( event ) {
			event.preventDefault();
			const action = event.currentTarget.getAttribute( 'data-action' );
			let dir = 0;
			if ( action === 'prev' ) {
				dir = -1;
			} else if ( action === 'next' ) {
				dir = 1;
			}
			if ( ! dir ) {
				return;
			}
			this.state.date = shiftDate( this.state.view, this.state.date, dir );
			await fetchCalendar( this.state );
		},
		async setView( event ) {
			event.preventDefault();
			const v = event.currentTarget.getAttribute( 'data-view' );
			if ( ! v ) {
				return;
			}
			this.state.view = v;
			await fetchCalendar( this.state );
		},
		async today( event ) {
			event.preventDefault();
			this.state.date = formatYmd( new Date() );
			await fetchCalendar( this.state );
		},
	},
} );

if (
	typeof window !== 'undefined' &&
	twecCalendarStoreApis &&
	twecCalendarStoreApis.actions &&
	typeof twecCalendarStoreApis.actions.loadCalendar === 'function'
) {
	window.twecPlanitReloadCalendar = async function twecReloadPlanitEmbeddedCalendar() {
		await twecCalendarStoreApis.actions.loadCalendar();
	};
}

/**
 * If hydration leaves the interactive calendar empty (missing merged state, directive timing, or theme
 * stripping data attributes), fetch once so the month/grid is never stuck blank.
 */
function twecBootstrapInteractiveCalendarIfEmpty() {
	const root = document.querySelector(
		'.twec-calendar-wrapper[data-wp-interactive="planit/calendar"]'
	);
	if ( ! root ) {
		return;
	}
	const viewEl = root.querySelector( '.twec-calendar-view' );
	if ( ! viewEl ) {
		return;
	}
	const html = ( viewEl.innerHTML || '' ).trim();
	// Heuristic: month grid tables are substantial; avoid AJAX when SSR/state already populated.
	if ( html.length > 80 ) {
		return;
	}
	if (
		! twecCalendarStoreApis ||
		! twecCalendarStoreApis.actions ||
		typeof twecCalendarStoreApis.actions.loadCalendar !== 'function'
	) {
		return;
	}
	twecCalendarStoreApis.actions.loadCalendar();
}

if ( typeof document !== 'undefined' ) {
	const run = () => {
		twecBootstrapInteractiveCalendarIfEmpty();
		setTimeout( twecBootstrapInteractiveCalendarIfEmpty, 200 );
	};
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
}
