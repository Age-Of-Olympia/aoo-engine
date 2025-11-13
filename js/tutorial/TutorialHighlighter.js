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

            // Track for cleanup
            this.highlights.push({
                $highlight: $highlight,
                $element: $element
            });

            // Update position on window resize
            $(window).on('resize.tutorial', () => {
                this.positionHighlight($highlight, $element);
            });

            // Fade in
            $highlight.fadeIn(200);
        });
    }

    /**
     * Position highlight box around element
     */
    positionHighlight($highlight, $element) {
        const offset = $element.offset();
        const width = $element.outerWidth();
        const height = $element.outerHeight();

        $highlight.css({
            top: `${offset.top - 5}px`,
            left: `${offset.left - 5}px`,
            width: `${width + 10}px`,
            height: `${height + 10}px`
        });
    }

    /**
     * Clear all highlights
     */
    clearAll() {
        this.highlights.forEach(item => {
            item.$highlight.fadeOut(200, () => item.$highlight.remove());
        });

        this.highlights = [];

        // Remove resize listener
        $(window).off('resize.tutorial');

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
