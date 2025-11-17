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

            // Update position on window resize or scroll
            $(window).on('resize.tutorial scroll.tutorial', () => {
                this.positionHighlight($highlight, $element);
            });

            // Update position when #view changes (map updates after movement)
            const viewObserver = new MutationObserver(() => {
                this.positionHighlight($highlight, $element);
            });

            const viewElement = document.getElementById('view');
            if (viewElement) {
                viewObserver.observe(viewElement, {
                    childList: true,
                    subtree: true
                });
                this.highlights[this.highlights.length - 1].viewObserver = viewObserver;
            }

            // Update position when characteristics panel appears/disappears
            // This prevents highlights from being offset when the panel loads after highlighting
            const caracObserver = new MutationObserver(() => {
                this.positionHighlight($highlight, $element);
            });

            const caracElement = document.getElementById('load-caracs');
            if (caracElement) {
                caracObserver.observe(caracElement, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['style'] // Watch for display changes
                });
                this.highlights[this.highlights.length - 1].caracObserver = caracObserver;
            }

            // Also watch for any changes to the body that might affect layout
            const bodyObserver = new MutationObserver(() => {
                // Use requestAnimationFrame to debounce rapid changes
                if (!this._repositionPending) {
                    this._repositionPending = true;
                    requestAnimationFrame(() => {
                        this.positionHighlight($highlight, $element);
                        this._repositionPending = false;
                    });
                }
            });

            bodyObserver.observe(document.body, {
                childList: true,
                subtree: false // Only watch direct children of body
            });
            this.highlights[this.highlights.length - 1].bodyObserver = bodyObserver;

            // Watch for DOM changes on the element (e.g., when button expands)
            const observer = new MutationObserver(() => {
                this.positionHighlight($highlight, $element);
            });

            observer.observe(element, {
                attributes: true,    // Watch for attribute changes (class, style)
                childList: true,     // Watch for child elements being added/removed
                subtree: true,       // Watch descendants too
                characterData: true  // Watch for text changes
            });

            // Store observer for cleanup
            this.highlights[this.highlights.length - 1].observer = observer;

            // Fade in
            $highlight.fadeIn(200);
        });
    }

    /**
     * Position highlight box around element
     */
    positionHighlight($highlight, $element) {
        const element = $element[0];

        // For SVG elements, use getBoundingClientRect() instead of jQuery offset()
        const rect = element.getBoundingClientRect();

        // Account for page scroll
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;

        $highlight.css({
            top: `${rect.top + scrollTop - 5}px`,
            left: `${rect.left + scrollLeft - 5}px`,
            width: `${rect.width + 10}px`,
            height: `${rect.height + 10}px`
        });
    }

    /**
     * Clear all highlights
     */
    clearAll() {
        this.highlights.forEach(item => {
            item.$highlight.fadeOut(200, () => item.$highlight.remove());

            // Disconnect MutationObserver if exists
            if (item.observer) {
                item.observer.disconnect();
            }

            // Disconnect viewObserver if exists
            if (item.viewObserver) {
                item.viewObserver.disconnect();
            }
        });

        this.highlights = [];

        // Remove resize and scroll listeners
        $(window).off('resize.tutorial scroll.tutorial');

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
