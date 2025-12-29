/**
 * Voxel Toolkit - Recurring Events
 *
 * Handles displaying only the specific occurrence date on cards
 * that have been expanded for recurring events.
 *
 * Two modes of operation:
 * 1. AJAX results: Cards already have data-vt-occurrence-index from PHP
 * 2. Initial page load: Fetch occurrence data via AJAX and duplicate cards
 */

(function() {
    'use strict';

    /**
     * Fetch occurrence data for visible cards and expand them
     */
    function fetchAndExpandCards() {
        // Find all event cards that don't have occurrence data yet
        const cards = document.querySelectorAll('.ts-preview[data-post-id]:not([data-vt-expanded])');

        if (cards.length === 0) {
            return;
        }

        // Collect unique post IDs
        const postIds = [...new Set(Array.from(cards).map(card => card.dataset.postId))];

        if (postIds.length === 0) {
            return;
        }

        // Check if we have config for AJAX
        if (typeof vtRecurringEventsConfig === 'undefined') {
            console.log('VT Recurring Events: No AJAX config available');
            return;
        }

        console.log('VT Recurring Events: Fetching occurrences for', postIds.length, 'posts');

        // Make AJAX request to get occurrence data
        const formData = new FormData();
        formData.append('action', 'vt_get_occurrences');
        postIds.forEach(id => formData.append('post_ids[]', id));

        fetch(vtRecurringEventsConfig.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(response => {
            if (response.success && response.data && response.data.data) {
                const occurrenceData = response.data.data;
                console.log('VT Recurring Events: Received occurrence data', occurrenceData);

                // Store in global for expandInitialLoadCards to use
                window.vtRecurringEventsData = occurrenceData;

                // Expand cards with occurrence data
                expandInitialLoadCards();

                // Process date display
                processOccurrenceCards();

                // Sort by occurrence date
                sortCardsByOccurrenceDate();
            }
        })
        .catch(error => {
            console.error('VT Recurring Events: Error fetching occurrences', error);
        });
    }

    /**
     * Get the posts per page limit from the post feed widget
     */
    function getPostsPerPageLimit() {
        // Look for the post feed widget with data-per-page attribute
        const postFeed = document.querySelector('[data-per-page]');
        if (postFeed) {
            const limit = parseInt(postFeed.dataset.perPage, 10);
            if (!isNaN(limit) && limit > 0) {
                return limit;
            }
        }
        return 0; // No limit
    }

    /**
     * Expand cards on initial page load based on occurrence data
     * This duplicates cards for recurring events with multiple occurrences
     */
    function expandInitialLoadCards() {
        const data = window.vtRecurringEventsData;

        if (!data || Object.keys(data).length === 0) {
            return;
        }

        console.log('VT Recurring Events: Expanding cards for initial load', data);

        // Get posts per page limit
        const postsPerPage = getPostsPerPageLimit();
        let totalCardsCount = document.querySelectorAll('.ts-preview[data-post-id]').length;

        console.log('VT Recurring Events: Posts per page limit:', postsPerPage, 'Current cards:', totalCardsCount);

        // Collect all occurrences with their post data for sorting
        let allOccurrences = [];

        Object.keys(data).forEach(postId => {
            const postData = data[postId];
            const occurrences = postData.occurrences || [];

            if (occurrences.length <= 1) {
                return;
            }

            // Find the original card for this post
            const originalCard = document.querySelector(`.ts-preview[data-post-id="${postId}"]:not([data-vt-expanded])`);

            if (!originalCard) {
                return;
            }

            // Add all occurrences (including first) to the list
            occurrences.forEach((occ, index) => {
                allOccurrences.push({
                    postId: postId,
                    index: index,
                    start: occ.start,
                    end: occ.end,
                    originalCard: originalCard,
                    isOriginal: index === 0
                });
            });
        });

        // Sort all occurrences by start date
        allOccurrences.sort((a, b) => {
            const dateA = a.start || '9999-12-31';
            const dateB = b.start || '9999-12-31';
            return dateA.localeCompare(dateB);
        });

        // Apply posts per page limit
        if (postsPerPage > 0) {
            // We need to account for cards that don't have recurring dates
            const nonRecurringCards = document.querySelectorAll('.ts-preview[data-post-id]:not([data-vt-expanded])').length -
                                      Object.keys(data).length;
            const availableSlots = postsPerPage - nonRecurringCards;

            if (availableSlots > 0 && allOccurrences.length > availableSlots) {
                allOccurrences = allOccurrences.slice(0, availableSlots);
                console.log('VT Recurring Events: Limited to', availableSlots, 'occurrences due to posts per page setting');
            }
        }

        // Group occurrences by postId for processing
        const occurrencesByPost = {};
        allOccurrences.forEach(occ => {
            if (!occurrencesByPost[occ.postId]) {
                occurrencesByPost[occ.postId] = [];
            }
            occurrencesByPost[occ.postId].push(occ);
        });

        // Process each post
        Object.keys(occurrencesByPost).forEach(postId => {
            const postOccurrences = occurrencesByPost[postId];
            const originalCard = postOccurrences[0].originalCard;
            const fullOccurrences = data[postId].occurrences;

            // Mark original as the first occurrence in the filtered list
            const firstOcc = postOccurrences[0];
            originalCard.dataset.vtOccurrenceIndex = firstOcc.index.toString();
            originalCard.dataset.vtOccurrenceStart = firstOcc.start || '';
            originalCard.dataset.vtOccurrenceEnd = firstOcc.end || '';
            originalCard.dataset.vtExpanded = 'true';

            // If the first occurrence in filtered list isn't index 0, update the date
            if (firstOcc.index > 0) {
                replaceDateTextInCard(originalCard, fullOccurrences[0], fullOccurrences[firstOcc.index]);
            }

            // Clone for additional occurrences in the filtered list
            let insertAfter = originalCard;
            for (let i = 1; i < postOccurrences.length; i++) {
                const occ = postOccurrences[i];
                const clone = originalCard.cloneNode(true);

                // Update occurrence data on clone
                clone.dataset.vtOccurrenceIndex = occ.index.toString();
                clone.dataset.vtOccurrenceStart = occ.start || '';
                clone.dataset.vtOccurrenceEnd = occ.end || '';
                clone.dataset.vtExpanded = 'true';

                // Remove processed flag so it gets processed
                delete clone.dataset.vtOccurrenceProcessed;

                // Replace date text in the clone (from first occurrence to this occurrence)
                replaceDateTextInCard(clone, fullOccurrences[firstOcc.index], fullOccurrences[occ.index]);

                // Insert after the previous card
                insertAfter.parentNode.insertBefore(clone, insertAfter.nextSibling);
                insertAfter = clone;
            }

            console.log('VT Recurring Events: Expanded post', postId, 'with', postOccurrences.length, 'of', fullOccurrences.length, 'occurrences');
        });

        // Clear the data to prevent re-processing
        window.vtRecurringEventsData = {};

        // Update the results count to include expanded cards
        updateResultsCount();
    }

    /**
     * Update the "X results" count to reflect expanded cards
     */
    function updateResultsCount() {
        // Count all visible cards (including expanded ones)
        const allCards = document.querySelectorAll('.ts-preview[data-post-id]');
        const totalCards = allCards.length;

        if (totalCards === 0) return;

        // Find and update Voxel's result count element
        // Voxel uses .result-count class in post-feed.php template
        const resultCountElements = document.querySelectorAll('.result-count');

        resultCountElements.forEach(el => {
            const currentText = el.textContent.trim();
            // Pattern: "X of Y" or just "X results" etc.
            // Replace all numbers with the new count, preserving format
            // e.g., "5 of 10" -> "15 of 15", "5 results" -> "15 results"

            // Check for "X of Y" pattern
            const ofMatch = currentText.match(/^(\d+)\s+of\s+(\d+)$/i);
            if (ofMatch) {
                el.textContent = `${totalCards} of ${totalCards}`;
            } else {
                // Replace just the first number
                const newText = currentText.replace(/\d+/, totalCards.toString());
                if (newText !== currentText) {
                    el.textContent = newText;
                }
            }
            el.dataset.vtCountUpdated = 'true';
            el.classList.remove('hidden'); // Make sure it's visible
        });

        console.log('VT Recurring Events: Updated results count to', totalCards);
    }

    /**
     * Format date for searching (multiple common formats)
     */
    function formatOccurrenceDateForSearch(dateString) {
        if (!dateString) return [];

        try {
            const date = new Date(dateString.replace(' ', 'T'));
            if (isNaN(date.getTime())) return [dateString];

            const formats = [];

            // Add various date format representations that Voxel might use
            // Format: December 30, 2025
            formats.push(date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }));
            // Format: Dec 30, 2025
            formats.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }));
            // Format: 12/30/2025
            formats.push(date.toLocaleDateString('en-US'));
            // Format: 30/12/2025
            formats.push(date.toLocaleDateString('en-GB'));
            // Format: 2025-12-30
            formats.push(dateString.split(' ')[0]);
            // Format: Monday, December 30
            formats.push(date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' }));
            // Format: Mon, Dec 30
            formats.push(date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }));

            return formats;
        } catch (e) {
            return [dateString];
        }
    }

    /**
     * Replace date text in a cloned card
     */
    function replaceDateTextInCard(card, originalOcc, newOcc) {
        const originalFormats = formatOccurrenceDateForSearch(originalOcc.start);
        const newFormats = formatOccurrenceDateForSearch(newOcc.start);

        // Walk through all text nodes in the card
        const walker = document.createTreeWalker(
            card,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );

        const textNodes = [];
        let node;
        while (node = walker.nextNode()) {
            textNodes.push(node);
        }

        // Replace date text in each text node
        textNodes.forEach(textNode => {
            let text = textNode.textContent;
            let replaced = false;

            // Try each original format
            for (let i = 0; i < originalFormats.length; i++) {
                const originalText = originalFormats[i];
                if (originalText && text.includes(originalText)) {
                    // Use the same format index for the new date, or fall back to first format
                    const newText = newFormats[i] || newFormats[0];
                    text = text.replace(originalText, newText);
                    replaced = true;
                    break;
                }
            }

            if (replaced) {
                textNode.textContent = text;
            }
        });

        // Also handle end dates if present
        if (originalOcc.end && newOcc.end) {
            const originalEndFormats = formatOccurrenceDateForSearch(originalOcc.end);
            const newEndFormats = formatOccurrenceDateForSearch(newOcc.end);

            const walker2 = document.createTreeWalker(card, NodeFilter.SHOW_TEXT, null, false);
            const textNodes2 = [];
            let node2;
            while (node2 = walker2.nextNode()) {
                textNodes2.push(node2);
            }

            textNodes2.forEach(textNode => {
                let text = textNode.textContent;
                for (let i = 0; i < originalEndFormats.length; i++) {
                    const originalText = originalEndFormats[i];
                    if (originalText && text.includes(originalText)) {
                        const newText = newEndFormats[i] || newEndFormats[0];
                        text = text.replace(originalText, newText);
                        textNode.textContent = text;
                        break;
                    }
                }
            });
        }
    }

    /**
     * Format a date string for display
     */
    function formatOccurrenceDate(dateString) {
        if (!dateString) return '';

        try {
            const date = new Date(dateString.replace(' ', 'T'));
            if (isNaN(date.getTime())) return dateString;

            // Format: "Mon, Jan 6, 2026 at 9:00 AM"
            const options = {
                weekday: 'short',
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            };

            return date.toLocaleDateString('en-US', options);
        } catch (e) {
            return dateString;
        }
    }

    /**
     * Process cards with occurrence data to show only the relevant date
     */
    function processOccurrenceCards() {
        const cards = document.querySelectorAll('[data-vt-occurrence-index]');

        cards.forEach(card => {
            // Skip if already processed
            if (card.dataset.vtOccurrenceProcessed === 'true') {
                return;
            }

            const occurrenceIndex = parseInt(card.dataset.vtOccurrenceIndex, 10);
            const occurrenceStart = card.dataset.vtOccurrenceStart;
            const occurrenceEnd = card.dataset.vtOccurrenceEnd;

            if (isNaN(occurrenceIndex)) {
                return;
            }

            // Method 1: Find date list items and show only the correct one
            const dateSelectors = [
                '.ts-object-list-item',           // Object list items
                '[class*="recurring-date"] li',   // List items in recurring date containers
                '.event-date-item',               // Common event date class
                '.ts-repeater-item',              // Repeater items
            ];

            let dateItems = null;

            for (const selector of dateSelectors) {
                const items = card.querySelectorAll(selector);
                if (items.length > 1) {
                    dateItems = items;
                    break;
                }
            }

            if (dateItems && dateItems.length > 1) {
                // Hide all items except the one at the occurrence index
                dateItems.forEach((item, index) => {
                    if (index === occurrenceIndex) {
                        item.style.display = '';
                        item.classList.add('vt-occurrence-active');
                    } else {
                        item.style.display = 'none';
                        item.classList.add('vt-occurrence-hidden');
                    }
                });
            }

            // Method 2: Update any element with data-vt-occurrence-date attribute
            // Users can add this to their template: <span data-vt-occurrence-date>@post(recurring-date.upcoming.start)</span>
            const dateElements = card.querySelectorAll('[data-vt-occurrence-date]');
            dateElements.forEach(el => {
                const type = el.dataset.vtOccurrenceDate || 'start';
                const dateValue = type === 'end' ? occurrenceEnd : occurrenceStart;
                if (dateValue) {
                    el.textContent = formatOccurrenceDate(dateValue);
                }
            });

            // Method 3: For cloned cards (index > 0), try to find and update date text
            // Look for common date patterns in the card
            if (occurrenceIndex > 0 && occurrenceStart) {
                updateDateInCard(card, occurrenceStart, occurrenceEnd);
            }

            // Mark as processed
            card.dataset.vtOccurrenceProcessed = 'true';
        });
    }

    /**
     * Try to find and update date text in a cloned card
     */
    function updateDateInCard(card, startDate, endDate) {
        // Look for elements that might contain dates
        // Common selectors for date display in Voxel cards
        const possibleDateSelectors = [
            '.ts-icon-date + span',
            '.ts-icon-date ~ span',
            '[class*="date"]',
            '.elementor-icon-list-text',
            '.ts-term-icon + span',
            'time',
        ];

        for (const selector of possibleDateSelectors) {
            const elements = card.querySelectorAll(selector);
            elements.forEach(el => {
                // Check if this looks like a date element (has date-like content)
                const text = el.textContent.trim();
                if (looksLikeDate(text)) {
                    el.textContent = formatOccurrenceDate(startDate);
                    el.dataset.vtDateUpdated = 'true';
                }
            });
        }
    }

    /**
     * Check if text looks like a date
     */
    function looksLikeDate(text) {
        if (!text || text.length < 3 || text.length > 100) return false;

        // Common date patterns
        const datePatterns = [
            /\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}/,  // 12/25/2024, 25-12-2024
            /\w{3,9}\s+\d{1,2},?\s*\d{4}/,            // December 25, 2024
            /\d{1,2}\s+\w{3,9}\s+\d{4}/,              // 25 December 2024
            /\w{3},?\s+\w{3}\s+\d{1,2}/,              // Mon, Dec 25
            /\d{4}-\d{2}-\d{2}/,                       // 2024-12-25
        ];

        return datePatterns.some(pattern => pattern.test(text));
    }

    /**
     * Sort cards by occurrence date within their container
     */
    function sortCardsByOccurrenceDate() {
        // Find containers with recurring event cards
        const containers = new Set();
        document.querySelectorAll('[data-vt-occurrence-start]').forEach(card => {
            if (card.parentElement) {
                containers.add(card.parentElement);
            }
        });

        containers.forEach(container => {
            const cards = Array.from(container.querySelectorAll('.ts-preview[data-vt-occurrence-start]'));

            if (cards.length < 2) {
                return;
            }

            // Sort by occurrence start date
            cards.sort((a, b) => {
                const startA = a.dataset.vtOccurrenceStart || '9999-12-31';
                const startB = b.dataset.vtOccurrenceStart || '9999-12-31';
                return startA.localeCompare(startB);
            });

            // Re-append in sorted order
            cards.forEach(card => {
                container.appendChild(card);
            });
        });
    }

    /**
     * Initialize on page load
     */
    function init() {
        // Check if we have pre-loaded data from PHP
        if (window.vtRecurringEventsData && Object.keys(window.vtRecurringEventsData).length > 0) {
            // Data already available from PHP hook
            expandInitialLoadCards();
            processOccurrenceCards();
            sortCardsByOccurrenceDate();
        } else {
            // Need to fetch via AJAX
            fetchAndExpandCards();
        }

        // Process after Voxel AJAX loads new results
        // Voxel triggers custom events after loading search results
        document.addEventListener('voxel:search-results-loaded', function() {
            processOccurrenceCards();
        });

        // Also listen for generic content loaded events
        document.addEventListener('voxel:content-loaded', function() {
            processOccurrenceCards();
        });

        // Use MutationObserver to catch dynamically added cards
        const observer = new MutationObserver((mutations) => {
            let hasNewCards = false;
            let needsExpansion = false;

            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) {
                        // Check if occurrence data was added
                        if (window.vtRecurringEventsData && Object.keys(window.vtRecurringEventsData).length > 0) {
                            needsExpansion = true;
                        }
                        // Check if the node or its children have occurrence data
                        if (node.hasAttribute && node.hasAttribute('data-vt-occurrence-index')) {
                            hasNewCards = true;
                        } else if (node.querySelectorAll) {
                            const cards = node.querySelectorAll('[data-vt-occurrence-index]');
                            if (cards.length > 0) {
                                hasNewCards = true;
                            }
                            // Check for new cards that need AJAX fetch
                            const newCards = node.querySelectorAll('.ts-preview[data-post-id]:not([data-vt-expanded])');
                            if (newCards.length > 0) {
                                needsExpansion = true;
                            }
                        }
                    }
                });
            });

            if (needsExpansion || hasNewCards) {
                // Debounce the processing
                clearTimeout(observer._timeout);
                observer._timeout = setTimeout(function() {
                    if (needsExpansion) {
                        // Check if we need to fetch new data
                        if (!window.vtRecurringEventsData || Object.keys(window.vtRecurringEventsData).length === 0) {
                            fetchAndExpandCards();
                        } else {
                            expandInitialLoadCards();
                        }
                    }
                    processOccurrenceCards();
                }, 100);
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose for manual triggering if needed
    window.vtProcessOccurrenceCards = processOccurrenceCards;
    window.vtExpandRecurringCards = expandInitialLoadCards;
    window.vtFetchAndExpandCards = fetchAndExpandCards;

})();
