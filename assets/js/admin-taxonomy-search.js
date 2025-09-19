/**
 * Admin Taxonomy Search functionality
 */
(function($) {
    'use strict';
    
    window.VoxelTaxonomySearch = {
        
        init: function() {
            this.addSearchBoxes();
            this.bindEvents();
        },
        
        addSearchBoxes: function() {
            const enabledTaxonomies = voxelTaxonomySearch.enabledTaxonomies || [];
            
            // Add search boxes to enabled taxonomy metaboxes
            enabledTaxonomies.forEach(function(taxonomy) {
                const $metabox = $('#' + taxonomy + 'div');
                
                if ($metabox.length === 0) {
                    return;
                }
                
                // Check if search box already exists
                if ($metabox.find('.voxel-taxonomy-search').length > 0) {
                    return;
                }
                
                // Create search box
                const $searchBox = $(`
                    <div class="voxel-taxonomy-search">
                        <input type="text" 
                               class="voxel-taxonomy-search-input" 
                               placeholder="${voxelTaxonomySearch.searchPlaceholder}"
                               data-taxonomy="${taxonomy}">
                        <span class="voxel-taxonomy-search-clear" title="Clear search">&times;</span>
                    </div>
                `);
                
                // Insert search box at the top of the taxonomy metabox
                const $tabs = $metabox.find('.tabs-panel');
                if ($tabs.length > 0) {
                    // For hierarchical taxonomies (categories)
                    $tabs.each(function() {
                        const $tab = $(this);
                        const $list = $tab.find('ul.categorychecklist, ul.children');
                        if ($list.length > 0) {
                            $list.before($searchBox.clone());
                        }
                    });
                } else {
                    // For non-hierarchical taxonomies (tags)
                    const $tagsList = $metabox.find('.tagchecklist');
                    if ($tagsList.length > 0) {
                        $tagsList.before($searchBox.clone());
                    } else {
                        // Fallback: add to the beginning of the metabox content
                        $metabox.find('.inside').prepend($searchBox.clone());
                    }
                }
            });
        },
        
        bindEvents: function() {
            // Search input event
            $(document).on('input', '.voxel-taxonomy-search-input', this.handleSearch.bind(this));
            
            // Clear search event
            $(document).on('click', '.voxel-taxonomy-search-clear', this.clearSearch.bind(this));
            
            // Handle tab switching for hierarchical taxonomies
            $(document).on('click', '.category-tabs a', function() {
                setTimeout(function() {
                    VoxelTaxonomySearch.addSearchBoxes();
                }, 100);
            });
        },
        
        handleSearch: function(e) {
            const $input = $(e.target);
            const searchTerm = $input.val().toLowerCase();
            const taxonomy = $input.data('taxonomy');
            
            // Find the parent container
            const $container = $input.closest('.tabs-panel, #' + taxonomy + 'div');
            
            if (searchTerm === '') {
                this.showAllTerms($container);
                return;
            }
            
            this.filterTerms($container, searchTerm);
        },
        
        filterTerms: function($container, searchTerm) {
            let visibleCount = 0;
            
            // Handle hierarchical taxonomies (categories)
            $container.find('li').each(function() {
                const $li = $(this);
                const $label = $li.find('label');
                const termText = $label.text().toLowerCase();
                
                if (termText.includes(searchTerm)) {
                    $li.show();
                    // Show parent items as well
                    $li.parents('li').show();
                    visibleCount++;
                } else {
                    // Check if any children match
                    const hasMatchingChild = $li.find('li').filter(':visible').length > 0;
                    if (!hasMatchingChild) {
                        $li.hide();
                    } else {
                        $li.show();
                    }
                }
            });
            
            // Handle non-hierarchical taxonomies (tags)
            $container.find('.tagchecklist span').each(function() {
                const $span = $(this);
                const termText = $span.text().toLowerCase();
                
                if (termText.includes(searchTerm)) {
                    $span.show();
                    visibleCount++;
                } else {
                    $span.hide();
                }
            });
            
            this.showNoResultsMessage($container, visibleCount === 0);
        },
        
        showAllTerms: function($container) {
            $container.find('li, .tagchecklist span').show();
            this.showNoResultsMessage($container, false);
        },
        
        showNoResultsMessage: function($container, show) {
            $container.find('.voxel-no-results').remove();
            
            if (show) {
                const $message = $(`
                    <div class="voxel-no-results">
                        <p>${voxelTaxonomySearch.noResultsText}</p>
                    </div>
                `);
                
                $container.find('.voxel-taxonomy-search').after($message);
            }
        },
        
        clearSearch: function(e) {
            const $clear = $(e.target);
            const $input = $clear.siblings('.voxel-taxonomy-search-input');
            const $container = $clear.closest('.tabs-panel, [id$="div"]');
            
            $input.val('');
            this.showAllTerms($container);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        VoxelTaxonomySearch.init();
    });
    
})(jQuery);