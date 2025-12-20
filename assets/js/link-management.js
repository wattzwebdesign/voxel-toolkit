/**
 * Link Management - External Link Warning
 *
 * Intercepts external link clicks and shows a warning modal
 * before allowing users to navigate away from the site.
 */
(function() {
    'use strict';

    var config = window.vt_link_management || {};
    var modal = null;
    var pendingUrl = null;

    /**
     * Initialize link management
     */
    function init() {
        // Use event delegation for all link clicks
        document.addEventListener('click', handleLinkClick, true);

        // Handle keyboard navigation (Escape to close)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal) {
                closeModal();
            }
        });
    }

    /**
     * Handle link click events
     */
    function handleLinkClick(e) {
        // Find the closest anchor element
        var link = e.target.closest('a');
        if (!link || !link.href) {
            return;
        }

        // Parse the URL
        var url;
        try {
            url = new URL(link.href);
        } catch (err) {
            return; // Invalid URL, let it pass
        }

        // Skip non-http(s) links (mailto:, tel:, javascript:, etc.)
        if (url.protocol !== 'http:' && url.protocol !== 'https:') {
            return;
        }

        // Check if external
        if (!isExternalLink(url.hostname)) {
            return;
        }

        // Check whitelist
        if (isWhitelisted(url.hostname)) {
            return;
        }

        // Check exclusion selectors
        if (isExcluded(link)) {
            return;
        }

        // Prevent navigation and show modal
        e.preventDefault();
        e.stopPropagation();
        showWarningModal(link.href);
    }

    /**
     * Check if the hostname is external (different from current site)
     */
    function isExternalLink(hostname) {
        var currentHost = config.current_host || window.location.hostname;

        // Normalize hostnames (remove www. prefix for comparison)
        var normalizedCurrent = currentHost.replace(/^www\./, '');
        var normalizedTarget = hostname.replace(/^www\./, '');

        return normalizedCurrent !== normalizedTarget;
    }

    /**
     * Check if the hostname is in the whitelist
     */
    function isWhitelisted(hostname) {
        var whitelist = config.whitelist || [];

        // Normalize hostname
        var normalizedHostname = hostname.toLowerCase().replace(/^www\./, '');

        return whitelist.some(function(domain) {
            var normalizedDomain = domain.toLowerCase().replace(/^www\./, '');

            // Exact match or subdomain match
            return normalizedHostname === normalizedDomain ||
                   normalizedHostname.endsWith('.' + normalizedDomain);
        });
    }

    /**
     * Check if the link matches any exclusion selector
     */
    function isExcluded(link) {
        var selectors = config.exclusion_selectors || [];

        return selectors.some(function(selector) {
            try {
                return link.matches(selector);
            } catch (err) {
                // Invalid selector, ignore
                return false;
            }
        });
    }

    /**
     * Show the warning modal
     */
    function showWarningModal(url) {
        pendingUrl = url;

        // Create modal if it doesn't exist
        if (!modal) {
            createModal();
        }

        // Update URL display if enabled
        if (config.show_url) {
            var urlDisplay = modal.querySelector('.vt-link-modal-url');
            if (urlDisplay) {
                urlDisplay.textContent = truncateUrl(url, 60);
                urlDisplay.style.display = 'block';
            }
        }

        // Show modal
        modal.classList.add('vt-link-modal-visible');
        document.body.classList.add('vt-link-modal-open');

        // Focus the cancel button for accessibility
        var cancelBtn = modal.querySelector('.vt-link-modal-cancel');
        if (cancelBtn) {
            cancelBtn.focus();
        }
    }

    /**
     * Create the modal element
     */
    function createModal() {
        modal = document.createElement('div');
        modal.className = 'vt-link-modal-overlay';
        modal.innerHTML = '\
            <div class="vt-link-modal" role="dialog" aria-modal="true" aria-labelledby="vt-link-modal-title">\
                <div class="vt-link-modal-icon">\
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">\
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>\
                        <polyline points="15 3 21 3 21 9"></polyline>\
                        <line x1="10" y1="14" x2="21" y2="3"></line>\
                    </svg>\
                </div>\
                <h3 class="vt-link-modal-title" id="vt-link-modal-title">' + escapeHtml(config.title || "You're leaving this site") + '</h3>\
                <p class="vt-link-modal-message">' + escapeHtml(config.message || 'You are about to visit an external website.') + '</p>\
                <p class="vt-link-modal-url" style="display: none;"></p>\
                <div class="vt-link-modal-buttons">\
                    <button type="button" class="vt-link-modal-cancel">' + escapeHtml(config.cancel_text || 'Go Back') + '</button>\
                    <button type="button" class="vt-link-modal-continue">' + escapeHtml(config.continue_text || 'Continue') + '</button>\
                </div>\
            </div>\
        ';

        // Add event listeners
        modal.querySelector('.vt-link-modal-cancel').addEventListener('click', closeModal);
        modal.querySelector('.vt-link-modal-continue').addEventListener('click', continueToUrl);

        // Close on overlay click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });

        document.body.appendChild(modal);
    }

    /**
     * Close the modal
     */
    function closeModal() {
        if (modal) {
            modal.classList.remove('vt-link-modal-visible');
            document.body.classList.remove('vt-link-modal-open');
        }
        pendingUrl = null;
    }

    /**
     * Continue to the external URL
     */
    function continueToUrl() {
        if (pendingUrl) {
            window.open(pendingUrl, '_blank', 'noopener,noreferrer');
        }
        closeModal();
    }

    /**
     * Truncate URL for display
     */
    function truncateUrl(url, maxLength) {
        if (url.length <= maxLength) {
            return url;
        }
        return url.substring(0, maxLength - 3) + '...';
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
