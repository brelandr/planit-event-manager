/**
 * Public-facing JavaScript for The Event Calendar Premium
 */
(function($) {
    'use strict';

    var TWEC = {
        currentView: 'month',
        currentDate: new Date(),
        events: [],

        init: function() {
            this.bindEvents();
            // Non-interactivity calendars SSR markup in .twec-calendar-view; avoid AJAX on load so stale cached nonces do not flash an error.
            var $wrap = $('.twec-calendar-wrapper:not([data-wp-interactive])').first();
            var $calendarView = $wrap.find('.twec-calendar-view').first();
            var hasSsr = $wrap.length && $calendarView.length && $.trim($calendarView.html()).length > 0;
            if (hasSsr) {
                $('.twec-calendar-loading').hide();
            } else {
                this.loadCalendar();
            }
            this.initMaps();
        },

        bindEvents: function() {
            var self = this;

            // View switcher
            $(document).on('click', '.twec-view-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                
                // Premium features are simply not shown on frontend, so no need to handle locked buttons
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

            // Inline "Tickets" (WooCommerce): themes often neutralize :hover on links; toggle a class so rollover still shows (not a tooltip / modal).
            $(document).on(
                'mouseenter.twecTicketsRollover focusin.twecTicketsRollover',
                'a.twec-wc-calendar-tickets.twec-woo-tickets-button',
                function() {
                    $(this).addClass('twec-tickets-link--rollover');
                }
            );
            $(document).on(
                'mouseleave.twecTicketsRollover focusout.twecTicketsRollover',
                'a.twec-wc-calendar-tickets.twec-woo-tickets-button',
                function() {
                    $(this).removeClass('twec-tickets-link--rollover');
                }
            );
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

            var ticketCta = '0';
            var $wrap = $('.twec-calendar-wrapper').first();
            if ($wrap.length && typeof $wrap.attr('data-twec-ticket-cta') !== 'undefined') {
                ticketCta = $wrap.attr('data-twec-ticket-cta') === '1' ? '1' : '0';
            }

            // Non-Interactivity (jQuery) path only: omit payload v2 so PHP sends full calendar HTML without
            // relying on twec-calendar-grid-client hydration. Interactivity continues to fetch v2/grid from twec-calendar-view.js.
            var ajaxData = {
                action: 'twec_get_calendar',
                nonce: twecData.nonce,
                view: this.currentView,
                date: dateStr,
                ticket_cta: ticketCta,
                response_format: 'compact',
            };
            if (typeof twecData.calPub !== 'undefined' && twecData.calPub !== '') {
                ajaxData.cal_pub = twecData.calPub;
            }
            if ($wrap.length) {
                var twecCat = $wrap.attr('data-twec-category');
                var twecTag = $wrap.attr('data-twec-tag');
                if (twecCat) {
                    ajaxData.category = twecCat;
                }
                if (twecTag) {
                    ajaxData.tag = twecTag;
                }
            }

            $.ajax({
                url: twecData.ajaxUrl,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    if (response.success) {
                        $('.twec-calendar-title').text(response.data.title);
                        var viewHtml =
                            typeof response.data.html === 'string' ? response.data.html : '';
                        var pv =
                            typeof response.data.payloadVersion !== 'undefined'
                                ? Number(response.data.payloadVersion)
                                : 1;
                        if (
                            pv >= 2 &&
                            response.data.grid &&
                            typeof window.twecCalendarHtmlFromStructuredGrid === 'function'
                        ) {
                            var built = window.twecCalendarHtmlFromStructuredGrid(response.data.grid);
                            if (built) {
                                viewHtml = built;
                            }
                        }
                        // Server-rendered markup (escaped in PHP) or hydrated from structured grid payload.
                        $('.twec-calendar-view').html(viewHtml);
                        self.events = response.data.events || [];
                        $('.twec-calendar-loading').hide();

                        if ('map' === self.currentView) {
                            if (response.data.mapMarkers && response.data.mapMarkers.length && typeof window.twecMapHydrateCalendarFromAjax === 'function') {
                                window.twecMapHydrateCalendarFromAjax(response.data.mapMarkers);
                            } else if (typeof window.twecMapInitAll === 'function') {
                                window.twecMapInitAll();
                            }
                        }
                    } else {
                        $('.twec-calendar-loading').hide();
                        // Escape error message to prevent XSS
                        var errorMessage = response.data && response.data.message ? 
                            self.escapeHtml(response.data.message) : 
                            'Failed to load calendar';
                        $('.twec-calendar-view').html('<p class="twec-error">' + self.escapeHtml('Error: ') + errorMessage + '</p>');
                        console.error('Calendar AJAX error:', response);
                    }
                },
                error: function(xhr, status, error) {
                    $('.twec-calendar-loading').hide();
                    // Escape static error message (defense in depth)
                    var errorMsg = self.escapeHtml('Error loading calendar. Please check your browser console for details.');
                    $('.twec-calendar-view').html('<p class="twec-error">' + errorMsg + '</p>');
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

        /**
         * Escape HTML to prevent XSS attacks.
         *
         * @param {string} text Text to escape.
         * @return {string} Escaped text.
         */
        escapeHtml: function(text) {
            if (!text) {
                return '';
            }
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
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
            if (typeof window.twecMapInitAll === 'function') {
                window.twecMapInitAll();
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
                        var expiredText = TWEC.countdownExpired || 'Event has started';
                        $countdown.html('<p>' + TWEC.escapeHtml(expiredText) + '</p>');
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
        // jQuery fallback: first non-Interactivity calendar on the page (skip wrappers with data-wp-interactive).
        var $wrapper = $( '.twec-calendar-wrapper:not([data-wp-interactive])' ).first();
        if ( $wrapper.length ) {
            TWEC.currentView = $wrapper.data( 'view' ) || 'month';
            var currentDateStr = $wrapper.data( 'current-date' );
            if ( currentDateStr ) {
                TWEC.currentDate = new Date( currentDateStr );
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

    window.TWEC = TWEC;
    window.twecAfterCalendarLoad = function (view) {
        if (typeof TWEC === 'undefined' || !TWEC.initMaps) {
            return;
        }
        TWEC.currentView = view || TWEC.currentView;
        if (typeof google !== 'undefined' && google.maps) {
            TWEC.initMaps();
        }
    };

})(jQuery);

