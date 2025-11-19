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
        const $elements = $(selector);

        if ($elements.length === 0) {
            console.warn('[TutorialHighlighter] No elements found for selector:', selector);
            return;
        }

        console.log('[TutorialHighlighter] Highlighting', selector, $elements.length, 'elements');

        $elements.each((index, element) => {
            const $element = $(element);

            // Create highlight box
            const $highlight = $('<div class="tutorial-highlight"></div>');

            // Add pulsate class if needed
            if (options.pulsate) {
                $highlight.addClass('pulsate');
            }

            // Custom color
            if (options.color) {
                $highlight.css('border-color', options.color);
            }

            // Position highlight box
            this.positionHighlight($highlight, $element);

            // Add to DOM
            $('body').append($highlight);

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
            $highlight.fadeIn(200);
        });
    }

    /**
     * Position highlight box around element
     */
    positionHighlight($highlight, $element) {
        // Use shared position manager for accurate positioning
        const pos = TutorialPositionManager.getElementPosition($element);

        $highlight.css({
            top: `${pos.top - 5}px`,
            left: `${pos.left - 5}px`,
            width: `${pos.width + 10}px`,
            height: `${pos.height + 10}px`
        });
    }

    /**
     * Clear all highlights
     */
    clearAll() {
        this.highlights.forEach(item => {
            item.$highlight.fadeOut(200, () => item.$highlight.remove());

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

        console.log('[TutorialHighlighter] Cleared all highlights');
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
}

// Export for global use
window.TutorialHighlighter = TutorialHighlighter;
