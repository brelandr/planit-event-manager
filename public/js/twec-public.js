/**
 * Public-facing JavaScript for The WordPress Event Calendar Premium
 */
(function($) {
    'use strict';

    var TWEC = {
        currentView: 'month',
        currentDate: new Date(),
        events: [],

        init: function() {
            this.bindEvents();
            this.loadCalendar();
            this.initMaps();
        },

        bindEvents: function() {
            var self = this;

            // View switcher
            $(document).on('click', '.twec-view-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                
                // Handle premium locked buttons
                if ($btn.hasClass('twec-premium-locked')) {
                    if (typeof twecData !== 'undefined' && twecData.upgradeUrl) {
                        window.open(twecData.upgradeUrl, '_blank');
                    }
                    return false;
                }
                
                self.currentView = $btn.data('view');
                $('.twec-view-btn').removeClass('active');
                $btn.addClass('active');
                self.loadCalendar();
            });

            // Navigation buttons
            $(document).on('click', '.twec-nav-btn', function(e) {
                e.preventDefault();
                var action = $(this).data('action');
                if (action === 'prev') {
                    self.navigate(-1);
                } else if (action === 'next') {
                    self.navigate(1);
                }
            });

            // Today button
            $(document).on('click', '.twec-today-btn', function(e) {
                e.preventDefault();
                self.currentDate = new Date();
                self.loadCalendar();
            });

            // Event click
            $(document).on('click', '.twec-calendar-event', function(e) {
                e.preventDefault();
                var eventUrl = $(this).data('url') || $(this).attr('href');
                if (eventUrl) {
                    window.location.href = eventUrl;
                }
            });
        },

        navigate: function(direction) {
            var year = this.currentDate.getFullYear();
            var month = this.currentDate.getMonth();
            var date = this.currentDate.getDate();

            switch(this.currentView) {
                case 'day':
                    this.currentDate = new Date(year, month, date + direction);
                    break;
                case 'week':
                    this.currentDate = new Date(year, month, date + (direction * 7));
                    break;
                case 'month':
                    this.currentDate = new Date(year, month + direction, 1);
                    break;
                case 'year':
                    this.currentDate = new Date(year + direction, 0, 1);
                    break;
            }

            this.loadCalendar();
        },

        loadCalendar: function() {
            var self = this;
            var dateStr = this.formatDate(this.currentDate);
            
            $('.twec-calendar-loading').show();
            $('.twec-calendar-view').empty();

            $.ajax({
                url: twecData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'twec_get_calendar',
                    nonce: twecData.nonce,
                    view: this.currentView,
                    date: dateStr
                },
                success: function(response) {
                    if (response.success) {
                        $('.twec-calendar-title').text(response.data.title);
                        $('.twec-calendar-view').html(response.data.html);
                        self.events = response.data.events || [];
                        $('.twec-calendar-loading').hide();
                        
                        // Re-initialize maps if needed
                        if (self.currentView === 'map') {
                            self.initMaps();
                        }
                    } else {
                        $('.twec-calendar-loading').hide();
                        $('.twec-calendar-view').html('<p class="twec-error">Error: ' + (response.data && response.data.message ? response.data.message : 'Failed to load calendar') + '</p>');
                        console.error('Calendar AJAX error:', response);
                    }
                },
                error: function(xhr, status, error) {
                    $('.twec-calendar-loading').hide();
                    $('.twec-calendar-view').html('<p class="twec-error">Error loading calendar. Please check your browser console for details.</p>');
                    console.error('Calendar AJAX request failed:', status, error);
                    console.error('Response:', xhr.responseText);
                }
            });
        },

        formatDate: function(date) {
            var year = date.getFullYear();
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        },
        
        getCalendarTitle: function(view, date) {
            var year = date.getFullYear();
            var month = date.getMonth();
            var day = date.getDate();
            
            switch(view) {
                case 'day':
                    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                case 'week':
                    var start = new Date(date);
                    start.setDate(date.getDate() - date.getDay() + 1); // Monday
                    var end = new Date(start);
                    end.setDate(start.getDate() + 6);
                    return start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ' - ' + 
                           end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                case 'month':
                    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
                case 'year':
                    return year.toString();
                default:
                    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
            }
        },

        initMaps: function() {
            if (typeof google !== 'undefined' && google.maps) {
                // Single venue maps
                $('.twec-venue-map').each(function() {
                    var $map = $(this);
                    var lat = parseFloat($map.data('lat'));
                    var lng = parseFloat($map.data('lng'));
                    
                    if (lat && lng) {
                        var map = new google.maps.Map($map[0], {
                            zoom: 15,
                            center: { lat: lat, lng: lng },
                            mapTypeId: 'roadmap'
                        });
                        
                        new google.maps.Marker({
                            position: { lat: lat, lng: lng },
                            map: map
                        });
                    }
                });
                
                // Map view
                if (typeof twecMapMarkers !== 'undefined' && twecMapMarkers.length > 0) {
                    var mapContainer = document.getElementById('twec-map-container');
                    if (mapContainer) {
                        var bounds = new google.maps.LatLngBounds();
                        var map = new google.maps.Map(mapContainer, {
                            mapTypeId: 'roadmap'
                        });
                        
                        twecMapMarkers.forEach(function(marker) {
                            var position = { lat: marker.lat, lng: marker.lng };
                            bounds.extend(position);
                            
                            var mapMarker = new google.maps.Marker({
                                position: position,
                                map: map,
                                title: marker.title
                            });
                            
                            var infoWindow = new google.maps.InfoWindow({
                                content: '<div><h3><a href="' + marker.url + '">' + marker.title + '</a></h3>' +
                                         (marker.venue ? '<p>' + marker.venue + '</p>' : '') +
                                         (marker.date ? '<p>' + marker.date + '</p>' : '') +
                                         '</div>'
                            });
                            
                            mapMarker.addListener('click', function() {
                                infoWindow.open(map, mapMarker);
                            });
                        });
                        
                        map.fitBounds(bounds);
                    }
                }
            }
        },
        
        initCountdown: function() {
            $('.twec-countdown').each(function() {
                var $countdown = $(this);
                var eventDate = new Date($countdown.data('event-date')).getTime();
                
                function updateCountdown() {
                    var now = new Date().getTime();
                    var distance = eventDate - now;
                    
                    if (distance < 0) {
                        $countdown.html('<p>' + TWEC.countdownExpired || 'Event has started' + '</p>');
                        return;
                    }
                    
                    var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    var seconds = Math.floor((distance % (1000 * 60)) / 1000);
                    
                    $countdown.find('[data-days]').text(days);
                    $countdown.find('[data-hours]').text(hours);
                    $countdown.find('[data-minutes]').text(minutes);
                    $countdown.find('[data-seconds]').text(seconds);
                }
                
                updateCountdown();
                setInterval(updateCountdown, 1000);
            });
        }
    };

    $(document).ready(function() {
        // Initialize if calendar wrapper exists
        if ($('.twec-calendar-wrapper').length) {
            var $wrapper = $('.twec-calendar-wrapper');
            TWEC.currentView = $wrapper.data('view') || 'month';
            var currentDateStr = $wrapper.data('current-date');
            if (currentDateStr) {
                TWEC.currentDate = new Date(currentDateStr);
            }
            TWEC.init();
        }
        
        // Initialize maps on single event pages
        if (typeof google !== 'undefined' && google.maps) {
            TWEC.initMaps();
        }
        
        // Initialize countdown timers
        TWEC.initCountdown();
    });

})(jQuery);

