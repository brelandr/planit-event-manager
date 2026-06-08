/**
 * Calendar and single-event maps: Google Maps init (markers from JSON blob or AJAX payload).
 *
 * @package PlanIt_Event_Manager
 */

(function ($) {
	'use strict';

	/**
	 * Escape text for inserting into InfoWindow HTML.
	 *
	 * @param {*} text Raw value.
	 * @return {string}
	 */
	function escapeHtml(text) {
		if (!text) {
			return '';
		}
		var mapEscape = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, function (m) {
			return mapEscape[m];
		});
	}

	/**
	 * Single-venue previews on single event templates.
	 */
	function initVenueMaps() {
		$('.twec-venue-map').each(function () {
			var el = $(this)[0];
			var lat = parseFloat(el.getAttribute('data-lat'), 10);
			var lng = parseFloat(el.getAttribute('data-lng'), 10);
			if (!lat || !lng) {
				return;
			}
			var mapObj = new google.maps.Map(el, {
				zoom: 15,
				center: { lat: lat, lng: lng },
				mapTypeId: 'roadmap'
			});
			new google.maps.Marker({
				position: { lat: lat, lng: lng },
				map: mapObj
			});
		});
	}

	/**
	 * Premium map view with multiple markers.
	 *
	 * @param {{lat:number,lng:number,title?:string,url?:string,venue?:string,date?:string}[]} markers Marker list.
	 * @param {HTMLElement|null} mapContainer Container element.
	 */
	function initCalendarMultiMap(markers, mapContainer) {
		if (!mapContainer || !markers || markers.length === 0) {
			return;
		}

		var bounds = new google.maps.LatLngBounds();
		var mapObj = new google.maps.Map(mapContainer, {
			mapTypeId: 'roadmap'
		});

		markers.forEach(function (marker) {
			var position = {
				lat: parseFloat(marker.lat, 10),
				lng: parseFloat(marker.lng, 10)
			};
			bounds.extend(position);

			var mapMarker = new google.maps.Marker({
				position: position,
				map: mapObj,
				title: marker.title || ''
			});

			var infoWindow = new google.maps.InfoWindow({
				content:
					'<div><h3><a href="' +
					escapeHtml(marker.url || '') +
					'">' +
					escapeHtml(marker.title || '') +
					'</a></h3>' +
					(marker.venue ? '<p>' + escapeHtml(marker.venue) + '</p>' : '') +
					(marker.date ? '<p>' + escapeHtml(marker.date) + '</p>' : '') +
					'</div>'
			});

			mapMarker.addListener('click', function () {
				infoWindow.open(mapObj, mapMarker);
			});
		});

		mapObj.fitBounds(bounds);
	}

	/**
	 * Read markers from DOM: sibling JSON script after #twec-map-container (SSR / non-jQuery injection).
	 *
	 * @return {Array|null}
	 */
	function readMarkersFromDom() {
		var container = document.getElementById('twec-map-container');
		if (!container) {
			return null;
		}
		var jsonEl = document.getElementById('twec-calendar-map-markers-json');
		if (!jsonEl || typeof jsonEl.textContent !== 'string') {
			return null;
		}
		var raw = jsonEl.textContent.trim();
		if (!raw.length) {
			return [];
		}
		try {
			return JSON.parse(raw);
		} catch (err) {
			if (typeof console !== 'undefined' && console.error) {
				console.error('PlanIt: invalid map markers JSON', err);
			}
			return [];
		}
	}

	/**
	 * Runs venue maps + calendar map when google.maps is ready.
	 */
	window.twecMapInitAll = function () {
		if (typeof google === 'undefined' || !google.maps) {
			return;
		}
		initVenueMaps();

		var mapContainer = document.getElementById('twec-map-container');
		if (!mapContainer) {
			return;
		}
		var markers = readMarkersFromDom();
		if (null === markers) {
			markers = [];
		}
		if (markers.length > 0) {
			initCalendarMultiMap(markers, mapContainer);
		}
	};

	/**
	 * Called after calendar AJAX inserts HTML — jQuery may strip embedded JSON scripts; use payload from response.data.mapMarkers instead.
	 *
	 * @param {{lat:number,lng:number,title?:string,url?:string,venue?:string,date?:string}[]|null|undefined} markers Markers array.
	 */
	window.twecMapHydrateCalendarFromAjax = function (markers) {
		if (typeof google === 'undefined' || !google.maps) {
			return;
		}
		var mapContainer = document.getElementById('twec-map-container');
		if (!mapContainer || !markers || !markers.length) {
			return;
		}
		initCalendarMultiMap(markers, mapContainer);
	};
})(jQuery);
