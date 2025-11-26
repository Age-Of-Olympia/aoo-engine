/**
 * TutorialHighlighter - Element highlighting for tutorial
 *
 * Features:
 * - Highlights target elements with overlay
 * - Pulsating animation for validation steps
 * - Z-index management
 * - Multiple element support
 */
class TutorialHighlighter {
    constructor() {
        this.highlights = [];
        this.positionManager = new TutorialPositionManager();
    }

    /**
     * Highlight element(s)
     *
     * @param {string} selector CSS selector
     * @param {object} options { pulsate: boolean, color: string }
     */
    highlight(selector, options = {}) {
        console.log('[TutorialHighlighter] highlight() called with selector:', selector);
        const $elements = $(selector);
        console.log('[TutorialHighlighter] jQuery found', $elements.length, 'elements');

        if ($elements.length === 0) {
            console.warn('[TutorialHighlighter] No elements found for selector:', selector);
            // Debug: Try to understand why
            console.warn('[TutorialHighlighter] DOM ready?', document.readyState);
            console.warn('[TutorialHighlighter] Trying alternate selectors...');
            console.warn('[TutorialHighlighter] .case elements:', $('.case').length);
            console.warn('[TutorialHighlighter] .case[data-coords] elements:', $('.case[data-coords]').length);
            console.warn('[TutorialHighlighter] image elements:', $('image').length);

            // Debug: Show actual data-coords values that exist
            const allCoords = [];
            $('.case[data-coords]').each((idx, el) => {
                const coords = $(el).attr('data-coords');
                if (idx < 10) { // Show first 10
                    allCoords.push(coords);
                }
            });
            console.warn('[TutorialHighlighter] Sample data-coords values:', allCoords);

            // Debug: Check if player avatar exists
            const $avatar = $('#current-player-avatar');
            if ($avatar.length > 0) {
                const avatarX = $avatar.attr('x');
                const avatarY = $avatar.attr('y');
                console.warn('[TutorialHighlighter] Player avatar found at x:', avatarX, 'y:', avatarY);

                // Try to find the case at player position
                const playerCase = $(`.case[x="${avatarX}"][y="${avatarY}"]`);
                console.warn('[TutorialHighlighter] Case at player position:', playerCase.length, playerCase.attr('data-coords'));
            }

            return;
        }

        console.log('[TutorialHighlighter] Highlighting', selector, $elements.length, 'elements');

        // Log element details for debugging
        $elements.each((idx, el) => {
            const rect = el.getBoundingClientRect();
            console.log(`[TutorialHighlighter] Element ${idx}:`, {
                tag: el.tagName,
                visible: rect.width > 0 && rect.height > 0,
                position: { top: rect.top, left: rect.left, width: rect.width, height: rect.height }
            });
        });

        $elements.each((index, element) => {
            const $element = $(element);

            console.log(`[TutorialHighlighter] Processing element ${index}...`);

            // Create highlight box
            const $highlight = $('<div class="tutorial-highlight"></div>');
            console.log('[TutorialHighlighter] Created highlight div');

            // Add pulsate class if needed
            if (options.pulsate) {
                $highlight.addClass('pulsate');
                console.log('[TutorialHighlighter] Added pulsate class');
            }

            // Custom color
            if (options.color) {
                $highlight.css('border-color', options.color);
                console.log('[TutorialHighlighter] Set custom color:', options.color);
            }

            // Position highlight box
            console.log('[TutorialHighlighter] Positioning highlight...');
            this.positionHighlight($highlight, $element);

            // Log the actual position after setting
            const computedPos = {
                top: $highlight.css('top'),
                left: $highlight.css('left'),
                width: $highlight.css('width'),
                height: $highlight.css('height'),
                display: $highlight.css('display'),
                visibility: $highlight.css('visibility')
            };
            console.log('[TutorialHighlighter] Highlight positioned at:', computedPos);

            // Add to DOM
            $('body').append($highlight);
            console.log('[TutorialHighlighter] Appended to body. Highlight in DOM:', $highlight.length, 'visible in body:', $('body .tutorial-highlight').length);

            // Generate unique ID for tracking
            const trackingId = `highlight_${Date.now()}_${index}`;

            // Track for cleanup
            this.highlights.push({
                $highlight: $highlight,
                $element: $element,
                trackingId: trackingId
            });

            // Use shared position manager for automatic repositioning
            this.positionManager.track(trackingId, $highlight, ($hl) => {
                this.positionHighlight($hl, $element);
            });

            // Watch for DOM changes on the element itself (e.g., when button expands)
            const elementObserver = new MutationObserver(() => {
                this.positionHighlight($highlight, $element);
            });

            elementObserver.observe(element, {
                attributes: true,    // Watch for attribute changes (class, style)
                childList: true,     // Watch for child elements being added/removed
                subtree: true,       // Watch descendants too
                characterData: true  // Watch for text changes
            });

            // Store observer for cleanup
            this.highlights[this.highlights.length - 1].elementObserver = elementObserver;

            // Fade in
            console.log('[TutorialHighlighter] Calling fadeIn(200)...');
            $highlight.fadeIn(200, () => {
                console.log('[TutorialHighlighter] ✅ Fade-in complete! Display:', $highlight.css('display'), 'Opacity:', $highlight.css('opacity'));
            });
        });

        // Show the spotlight overlay (single dark layer for all highlights)
        this.showSpotlightOverlay();
    }

    /**
     * Position highlight box around element
     */
    positionHighlight($highlight, $element) {
        // Use shared position manager for accurate positioning
        const pos = TutorialPositionManager.getElementPosition($element);

        console.log('[TutorialHighlighter] Position data from TutorialPositionManager:', pos);

        // Validate position has dimensions
        if (pos.width === 0 || pos.height === 0) {
            console.warn('[TutorialHighlighter] ⚠️ Element has zero dimensions!', {
                width: pos.width,
                height: pos.height,
                element: $element[0]
            });
        }

        $highlight.css({
            top: `${pos.top - 5}px`,
            left: `${pos.left - 5}px`,
            width: `${pos.width + 10}px`,
            height: `${pos.height + 10}px`
        });

        console.log('[TutorialHighlighter] Applied CSS:', {
            top: $highlight.css('top'),
            left: $highlight.css('left'),
            width: $highlight.css('width'),
            height: $highlight.css('height')
        });
    }

    /**
     * Clear all highlights
     *
     * @returns {Promise} Resolves when all highlights are removed
     */
    clearAll() {
        const fadePromises = [];

        this.highlights.forEach(item => {
            // Create promise for fadeOut animation
            const fadePromise = new Promise(resolve => {
                item.$highlight.fadeOut(200, () => {
                    item.$highlight.remove();
                    resolve();
                });
            });
            fadePromises.push(fadePromise);

            // Untrack from position manager
            if (item.trackingId) {
                this.positionManager.untrack(item.trackingId);
            }

            // Disconnect element observer if exists
            if (item.elementObserver) {
                item.elementObserver.disconnect();
            }
        });

        this.highlights = [];

        // Hide the spotlight overlay
        this.hideSpotlightOverlay();

        console.log('[TutorialHighlighter] Cleared all highlights');

        // Return promise that resolves when all fadeOuts complete
        return Promise.all(fadePromises);
    }

    /**
     * Clear specific highlight
     */
    clear(selector) {
        this.highlights = this.highlights.filter(item => {
            if (item.$element.is(selector)) {
                item.$highlight.fadeOut(200, () => item.$highlight.remove());
                return false;
            }
            return true;
        });
    }

    /**
     * Update highlight positions (call after DOM changes)
     */
    updatePositions() {
        this.highlights.forEach(item => {
            this.positionHighlight(item.$highlight, item.$element);
        });
    }

    /**
     * Show the spotlight overlay (single dark layer)
     */
    showSpotlightOverlay() {
        // Create overlay if it doesn't exist
        if ($('#tutorial-spotlight-overlay').length === 0) {
            $('body').append('<div id="tutorial-spotlight-overlay"></div>');
        }

        // Hide the regular tutorial overlay to avoid double darkening
        $('#tutorial-overlay').addClass('has-spotlight');

        // Show spotlight overlay
        $('#tutorial-spotlight-overlay').fadeIn(200);
    }

    /**
     * Hide the spotlight overlay
     */
    hideSpotlightOverlay() {
        $('#tutorial-spotlight-overlay').fadeOut(200, () => {
            $('#tutorial-spotlight-overlay').remove();
        });

        // Restore regular tutorial overlay
        $('#tutorial-overlay').removeClass('has-spotlight');
    }
}

// Export for global use
window.TutorialHighlighter = TutorialHighlighter;
