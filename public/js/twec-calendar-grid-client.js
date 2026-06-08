/**
 * Shared DOM builders for PlanIt calendar AJAX `grid` payloads (payload v2).
 * Used by `twec-public.js` and Interactivity `twec-calendar-view.js` (`window.twecCalendarHtmlFromStructuredGrid`).
 *
 * @package PlanIt_Event_Manager
 */
( function() {
	'use strict';

	/**
	 * @param {{weekdayLabels: string[], weeks: Array} | null} grid
	 * @returns {string}
	 */
	function buildMonthTableFromGrid( grid ) {
		if ( typeof document === 'undefined' || ! grid ) {
			return '';
		}
		var labels = grid.weekdayLabels;
		var rows = grid.weeks;
		if ( ! Array.isArray( labels ) || ! Array.isArray( rows ) ) {
			return '';
		}
		var table = document.createElement( 'table' );
		table.className = 'twec-calendar-month';
		var thead = document.createElement( 'thead' );
		var theadRow = document.createElement( 'tr' );
		labels.forEach( function( dayLabel ) {
			var th = document.createElement( 'th' );
			th.textContent = String( dayLabel );
			theadRow.appendChild( th );
		} );
		thead.appendChild( theadRow );
		table.appendChild( thead );

		var tbody = document.createElement( 'tbody' );
		rows.forEach( function( week ) {
			if ( ! Array.isArray( week ) ) {
				return;
			}
			var tr = document.createElement( 'tr' );
			week.forEach( function( cell ) {
				if ( ! cell || typeof cell !== 'object' ) {
					return;
				}
				var td = document.createElement( 'td' );
				td.className = typeof cell.tdClass === 'string' ? cell.tdClass : '';
				var dayWrap = document.createElement( 'div' );
				dayWrap.className = 'twec-calendar-day';
				var num = document.createElement( 'div' );
				num.className = 'twec-calendar-day-number';
				num.textContent = typeof cell.dayNum !== 'undefined' ? String( cell.dayNum ) : '';
				dayWrap.appendChild( num );
				var evs = Array.isArray( cell.events ) ? cell.events : [];
				evs.forEach( function( ev ) {
					if ( ! ev || typeof ev !== 'object' ) {
						return;
					}
					var url = typeof ev.url === 'string' ? ev.url : '';
					var anchor = document.createElement( 'a' );
					anchor.className = 'twec-calendar-event';
					anchor.setAttribute( 'href', url );
					anchor.setAttribute( 'data-url', url );
					if ( typeof ev.titleFull === 'string' && ev.titleFull !== '' ) {
						anchor.setAttribute( 'title', ev.titleFull );
					}
					anchor.textContent = typeof ev.titleShort === 'string' ? ev.titleShort : '';
					dayWrap.appendChild( anchor );
					if ( ev.ticketHtml && String( ev.ticketHtml ).trim() !== '' ) {
						var ticketWrap = document.createElement( 'span' );
						ticketWrap.innerHTML = String( ev.ticketHtml );
						dayWrap.appendChild( ticketWrap );
					}
				} );
				td.appendChild( dayWrap );
				tr.appendChild( td );
			} );
			tbody.appendChild( tr );
		} );
		table.appendChild( tbody );
		return typeof table.outerHTML === 'string' ? table.outerHTML : '';
	}

	/**
	 * @param {*} grid
	 * @returns {string}
	 */
	function buildDayViewFromGrid( grid ) {
		if ( typeof document === 'undefined' || ! grid || ! Array.isArray( grid.hours ) ) {
			return '';
		}
		var wrap = document.createElement( 'div' );
		wrap.className = 'twec-calendar-day-view';
		grid.hours.forEach( function( hourRow ) {
			if ( ! hourRow || typeof hourRow !== 'object' ) {
				return;
			}
			var hLabel = document.createElement( 'div' );
			hLabel.className = 'twec-day-hour';
			hLabel.textContent = typeof hourRow.label === 'string' ? hourRow.label : '';
			var evWrap = document.createElement( 'div' );
			evWrap.className = 'twec-day-events';
			var hourEvents = Array.isArray( hourRow.events ) ? hourRow.events : [];
			hourEvents.forEach( function( ev ) {
				if ( ! ev || typeof ev !== 'object' ) {
					return;
				}
				var row = document.createElement( 'div' );
				row.className = 'twec-week-event';
				var anchor = document.createElement( 'a' );
				var url = typeof ev.url === 'string' ? ev.url : '';
				anchor.setAttribute( 'href', url );
				anchor.textContent = typeof ev.linkText === 'string' ? ev.linkText : '';
				row.appendChild( anchor );
				if ( ev.ticketHtml && String( ev.ticketHtml ).trim() !== '' ) {
					var tickets = document.createElement( 'span' );
					tickets.innerHTML = String( ev.ticketHtml );
					row.appendChild( tickets );
				}
				evWrap.appendChild( row );
			} );
			wrap.appendChild( hLabel );
			wrap.appendChild( evWrap );
		} );
		return typeof wrap.outerHTML === 'string' ? wrap.outerHTML : '';
	}

	/**
	 * @param {*} grid
	 * @returns {string}
	 */
	function buildWeekViewFromGrid( grid ) {
		if ( typeof document === 'undefined' || ! grid || ! Array.isArray( grid.rows ) ) {
			return '';
		}
		var root = document.createElement( 'div' );
		root.className = 'twec-calendar-week';
		var corner = document.createElement( 'div' );
		corner.className = 'twec-week-hour';
		root.appendChild( corner );
		var dayLbls = Array.isArray( grid.dayLabels ) ? grid.dayLabels : [];
		dayLbls.forEach( function( lbl ) {
			var dh = document.createElement( 'div' );
			dh.className = 'twec-week-day-header';
			dh.textContent = String( lbl );
			root.appendChild( dh );
		} );
		grid.rows.forEach( function( row ) {
			if ( ! row || typeof row !== 'object' ) {
				return;
			}
			var rLabel = document.createElement( 'div' );
			rLabel.className = 'twec-week-hour';
			rLabel.textContent = typeof row.label === 'string' ? row.label : '';
			root.appendChild( rLabel );
			var cells = Array.isArray( row.cells ) ? row.cells : [];
			cells.forEach( function( cell ) {
				if ( ! cell || typeof cell !== 'object' ) {
					return;
				}
				var dayCol = document.createElement( 'div' );
				dayCol.className = 'twec-week-day';
				var inner = Array.isArray( cell.events ) ? cell.events : [];
				inner.forEach( function( ev ) {
					if ( ! ev || typeof ev !== 'object' ) {
						return;
					}
					var ew = document.createElement( 'div' );
					ew.className = 'twec-week-event';
					var a = document.createElement( 'a' );
					var url = typeof ev.url === 'string' ? ev.url : '';
					a.setAttribute( 'href', url );
					a.textContent = typeof ev.titleShort === 'string' ? ev.titleShort : '';
					ew.appendChild( a );
					if ( ev.ticketHtml && String( ev.ticketHtml ).trim() !== '' ) {
						var t = document.createElement( 'span' );
						t.innerHTML = String( ev.ticketHtml );
						ew.appendChild( t );
					}
					dayCol.appendChild( ew );
				} );
				root.appendChild( dayCol );
			} );
		} );
		return typeof root.outerHTML === 'string' ? root.outerHTML : '';
	}

	/**
	 * @param {*} grid
	 * @returns {string}
	 */
	function buildYearViewFromGrid( grid ) {
		if ( typeof document === 'undefined' || ! grid || ! Array.isArray( grid.months ) ) {
			return '';
		}
		var root = document.createElement( 'div' );
		root.className = 'twec-calendar-year';
		grid.months.forEach( function( m ) {
			if ( ! m || typeof m !== 'object' ) {
				return;
			}
			var blk = document.createElement( 'div' );
			blk.className = 'twec-year-month';
			var ttl = document.createElement( 'div' );
			ttl.className = 'twec-year-month-title';
			ttl.textContent = typeof m.title === 'string' ? m.title : '';
			blk.appendChild( ttl );
			var g = document.createElement( 'div' );
			g.className = 'twec-year-month-grid';
			var wHead = Array.isArray( m.weekdayLabels ) ? m.weekdayLabels : [];
			wHead.forEach( function( w ) {
				var wd = document.createElement( 'div' );
				wd.className = 'twec-year-day twec-year-day-header';
				wd.textContent = String( w );
				g.appendChild( wd );
			} );
			var rowWeeks = Array.isArray( m.weeks ) ? m.weeks : [];
			rowWeeks.forEach( function( week ) {
				if ( ! Array.isArray( week ) ) {
					return;
				}
				week.forEach( function( cell ) {
					if ( ! cell || typeof cell !== 'object' ) {
						return;
					}
					var cel = document.createElement( 'div' );
					cel.className = typeof cell.divClass === 'string' ? cell.divClass : 'twec-year-day';
					if ( typeof cell.dayNum === 'string' && cell.dayNum !== '' ) {
						cel.textContent = cell.dayNum;
					}
					g.appendChild( cel );
				} );
			} );
			blk.appendChild( g );
			root.appendChild( blk );
		} );
		return typeof root.outerHTML === 'string' ? root.outerHTML : '';
	}

	/**
	 * Turn structured `grid` JSON into HTML for `.twec-calendar-view`.
	 *
	 * @param {*} grid
	 * @returns {string}
	 */
	function calendarHtmlFromStructuredGrid( grid ) {
		if ( ! grid || typeof grid !== 'object' ) {
			return '';
		}
		var inferredMonth =
			! grid.layout &&
			Array.isArray( grid.weekdayLabels ) &&
			Array.isArray( grid.weeks );
		var layout =
			typeof grid.layout === 'string' && grid.layout !== ''
				? grid.layout
				: inferredMonth ? 'month' : '';
		switch ( layout ) {
			case 'month':
				return buildMonthTableFromGrid( grid );
			case 'day':
				return buildDayViewFromGrid( grid );
			case 'week':
				return buildWeekViewFromGrid( grid );
			case 'year':
				return buildYearViewFromGrid( grid );
			default:
				return '';
		}
	}

	if ( typeof window !== 'undefined' ) {
		window.twecCalendarHtmlFromStructuredGrid = calendarHtmlFromStructuredGrid;
	}
} )();
