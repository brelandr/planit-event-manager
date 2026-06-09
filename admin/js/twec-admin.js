/**
 * Admin JavaScript for The Event Calendar Premium
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Keep Event Data open; cap body height so title/editor stay visible (classic screens).
        var metaBoxMaxHeightVh = '50vh';
        var $eventMetaBox = $('#twec_event_details');
        if ($eventMetaBox.length) {
            document.documentElement.style.setProperty('--twec-event-metabox-max-height', metaBoxMaxHeightVh);
            $eventMetaBox.removeClass('closed').addClass('twec-event-details-sized');
            $eventMetaBox.find('.handlediv').attr('aria-expanded', 'true');
            $eventMetaBox.find('.inside').show();
        }

        // Handle all-day event checkbox
        $('#twec_all_day').on('change', function() {
            if ($(this).is(':checked')) {
                $('#twec_start_time, #twec_end_time').prop('disabled', true).addClass('disabled');
            } else {
                $('#twec_start_time, #twec_end_time').prop('disabled', false).removeClass('disabled');
            }
        });

        // Trigger on page load
        $('#twec_all_day').trigger('change');
        
        // Prevent meta box from being closed on initial load
        setTimeout(function() {
            if ($eventMetaBox.hasClass('closed')) {
                $eventMetaBox.removeClass('closed');
                $eventMetaBox.find('.inside').show();
            }
        }, 100);

        // Handle recurring event checkbox (shows Repeat / Every / end controls in the sidebar meta box).
        $('#twec_is_recurring').on('change', function() {
            $('#twec-recurring-options').toggle($(this).is(':checked'));
        }).trigger('change');

        // Handle recurrence type change
        $('#twec_recurrence_type').on('change', function() {
            var type = $(this).val();
            var text = type === 'daily' ? 'day(s)' : (type === 'weekly' ? 'week(s)' : (type === 'monthly' ? 'month(s)' : 'year(s)'));
            $('#twec-recurrence-interval-text').text(text);
        }).trigger('change');

        // Handle delete test events form confirmation
        $('#twec-delete-test-events-form').on('submit', function(e) {
            if (!confirm(twecAdminData.deleteTestEventsConfirm || 'Are you sure you want to delete all test events? This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
        });
    });

})(jQuery);

