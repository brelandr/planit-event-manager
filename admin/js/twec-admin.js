/**
 * Admin JavaScript for The WordPress Event Calendar Premium
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Ensure Event Data meta box is expanded by default
        var $eventMetaBox = $('#twec_event_details');
        if ($eventMetaBox.length) {
            // Remove closed class if present
            $eventMetaBox.removeClass('closed');
            // Ensure inside is visible
            $eventMetaBox.find('.inside').show();
            // Update toggle button state
            $eventMetaBox.find('.handlediv').attr('aria-expanded', 'true');
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
    });

})(jQuery);

