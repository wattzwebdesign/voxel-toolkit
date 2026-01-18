/**
 * Pending Suggestions Widget JS
 *
 * @package Voxel_Toolkit
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        // Status filter functionality
        $(document).on('change', '.vt-filter-select', function() {
            var selectedStatus = $(this).val();
            var $wrapper = $(this).closest('.vt-pending-suggestions-wrapper');
            var $items = $wrapper.find('.vt-suggestion-item');

            $items.each(function() {
                var $item = $(this);
                var itemStatus = $item.data('status');

                if (selectedStatus === 'all' || itemStatus === selectedStatus) {
                    $item.show();
                } else {
                    $item.hide();
                }
            });
        });
    });

})(jQuery);
