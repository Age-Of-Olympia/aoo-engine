/**
 * TutorialPositionManager - Shared positioning utility for tutorial elements
 *
 * Provides robust positioning that handles:
 * - Window resize/scroll
 * - DOM mutations (panels opening, map updates)
 * - Accurate positioning using getBoundingClientRect()
 *
 * Used by both TutorialHighlighter and TutorialTooltip
 */
class TutorialPositionManager {
    constructor() {
        this.trackedElements = new Map();
        this._repositionPending = false;
    }

    /**
     * Start tracking an element for repositioning
     *
     * @param {string} id Unique identifier for this tracked element
     * @param {jQuery} $element Element to position
     * @param {function} positionCallback Function to call for positioning: (element) => void
     */
    track(id, $element, positionCallback) {
        // Stop tracking if already tracked
        if (this.trackedElements.has(id)) {
            this.untrack(id);
        }

        const tracking = {
            $element: $element,
            positionCallback: positionCallback,
            observers: {}
        };

        // Window resize and scroll listeners
        const windowHandler = () => {
            // Add small delay to ensure DOM has settled after resize
            requestAnimationFrame(() => {
                positionCallback($element);
            });
        };
        $(window).on(`resize.tutorial_${id} scroll.tutorial_${id}`, windowHandler);
        tracking.windowHandler = windowHandler;

        // Watch for #view changes (map updates after movement)
        const viewElement = document.getElementById('view');
        if (viewElement) {
            const viewObserver = new MutationObserver(() => {
                this._debouncedReposition(() => positionCallback($element));
            });

            viewObserver.observe(viewElement, {
                childList: true,
                subtree: true
            });

            tracking.observers.view = viewObserver;
        }

        // Watch for #load-caracs changes (characteristics panel)
        const caracElement = document.getElementById('load-caracs');
        if (caracElement) {
            const caracObserver = new MutationObserver(() => {
                this._debouncedReposition(() => positionCallback($element));
            });

            caracObserver.observe(caracElement, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['style'] // Watch for display changes
            });

            tracking.observers.carac = caracObserver;
        }

        // Watch for body changes (panels opening, cards appearing, etc.)
        const bodyObserver = new MutationObserver(() => {
            this._debouncedReposition(() => positionCallback($element));
        });

        bodyObserver.observe(document.body, {
            childList: true,
            subtree: false, // Only watch direct children of body
            attributes: true,
            attributeFilter: ['style', 'class']
        });

        tracking.observers.body = bodyObserver;

        // Store tracking info
        this.trackedElements.set(id, tracking);

        console.log('[TutorialPositionManager] Now tracking:', id);
    }

    /**
     * Stop tracking an element
     *
     * @param {string} id Unique identifier
     */
    untrack(id) {
        const tracking = this.trackedElements.get(id);
        if (!tracking) return;

        // Disconnect all observers
        Object.values(tracking.observers).forEach(observer => {
            observer.disconnect();
        });

        // Remove window listeners
        $(window).off(`resize.tutorial_${id} scroll.tutorial_${id}`);

        // Remove from map
        this.trackedElements.delete(id);

        console.log('[TutorialPositionManager] Stopped tracking:', id);
    }

    /**
     * Stop tracking all elements
     */
    untrackAll() {
        for (const id of this.trackedElements.keys()) {
            this.untrack(id);
        }
    }

    /**
     * Debounced repositioning using requestAnimationFrame
     */
    _debouncedReposition(callback) {
        if (!this._repositionPending) {
            this._repositionPending = true;
            requestAnimationFrame(() => {
                callback();
                this._repositionPending = false;
            });
        }
    }

    /**
     * Get accurate element position using getBoundingClientRect with scroll offset
     *
     * @param {jQuery} $element Element to get position for
     * @returns {object} { top, left, width, height }
     */
    static getElementPosition($element) {
        const element = $element[0];
        const rect = element.getBoundingClientRect();

        // Account for page scroll
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;

        return {
            top: rect.top + scrollTop,
            left: rect.left + scrollLeft,
            width: rect.width,
            height: rect.height
        };
    }
}

// Export for global use
window.TutorialPositionManager = TutorialPositionManager;
