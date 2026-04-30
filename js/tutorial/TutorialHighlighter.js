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


        // Log element details for debugging
        $elements.each((idx, el) => {
            const rect = el.getBoundingClientRect();
        });

        // Padding (px) extends both the gold-bordered highlight box AND
        // the SVG mask cut-out outward from the element. Used to cover
        // not just the element but the surrounding zone the player needs
        // to interact with — e.g. highlight the player avatar with a
        // 50px padding to reveal the 8 walkable tiles around them.
        const padding = Number.isFinite(options.padding) ? options.padding : 0;

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
            this.positionHighlight($highlight, $element, padding);

            // Log the actual position after setting
            const computedPos = {
                top: $highlight.css('top'),
                left: $highlight.css('left'),
                width: $highlight.css('width'),
                height: $highlight.css('height'),
                display: $highlight.css('display'),
                visibility: $highlight.css('visibility')
            };

            // Add to DOM
            $('body').append($highlight);

            // Generate unique ID for tracking
            const trackingId = `highlight_${Date.now()}_${index}`;

            // Track for cleanup. padding is needed for the SVG mask
            // cut-out so it stays in sync with the visible box.
            this.highlights.push({
                $highlight: $highlight,
                $element: $element,
                trackingId: trackingId,
                padding: padding
            });

            // Use shared position manager for automatic repositioning
            this.positionManager.track(trackingId, $highlight, ($hl) => {
                this.positionHighlight($hl, $element, padding);
            });

            // Watch for DOM changes on the element itself (e.g., when button expands)
            const elementObserver = new MutationObserver(() => {
                this.positionHighlight($highlight, $element, padding);
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
            $highlight.fadeIn(200, () => {
            });
        });

        // Show the spotlight overlay (single dark layer for all highlights)
        this.showSpotlightOverlay();
    }

    /**
     * Position highlight box around element
     */
    positionHighlight($highlight, $element, padding = 0) {
        // Use shared position manager for accurate positioning
        const pos = TutorialPositionManager.getElementPosition($element);


        // Validate position has dimensions
        if (pos.width === 0 || pos.height === 0) {
            console.warn('[TutorialHighlighter] ⚠️ Element has zero dimensions!', {
                width: pos.width,
                height: pos.height,
                element: $element[0]
            });
        }

        // 5px breathing room for the gold border + caller-supplied
        // padding to extend the highlight outward (e.g. cover the 8
        // tiles around the player avatar).
        const gap = 5 + padding;
        $highlight.css({
            top: `${pos.top - gap}px`,
            left: `${pos.left - gap}px`,
            width: `${pos.width + gap * 2}px`,
            height: `${pos.height + gap * 2}px`
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
     * Show the spotlight overlay.
     *
     * Renders a fullscreen dim with one cut-out per "thing the player
     * needs to see" — each highlighted target plus the open #ui-card
     * — using an SVG mask. Each cut-out matches the actual element
     * rectangle, so unrelated chrome stays dimmed without forcing a
     * giant union bounding box (the previous bounding-box approach
     * lit up huge empty regions between the map and the card).
     *
     * Falls back to a plain fullscreen dim when no element is on
     * screen (info / dialog steps off the map page).
     */
    showSpotlightOverlay() {
        $('#tutorial-spotlight-overlay').remove();

        const holes = this.computeSpotlightHoles();
        const $overlay = $(this.buildSpotlightSvg(holes));
        // Show immediately so the handoff from #tutorial-pre-dim
        // (server-rendered placeholder, dropped by TutorialUI on
        // mode-apply) doesn't flash un-dimmed content on reload.
        $overlay.css('display', 'block');
        $('body').append($overlay);

        // Hide the regular tutorial overlay to avoid double darkening
        $('#tutorial-overlay').addClass('has-spotlight');

        // Re-render on viewport changes so the holes track the elements.
        this.bindSpotlightReposition();
    }

    /**
     * Build the SVG markup for the spotlight overlay.
     *
     * @param {Array<{top:number,left:number,width:number,height:number}>} holes
     * @returns {string}
     */
    buildSpotlightSvg(holes) {
        const w = window.innerWidth;
        const h = window.innerHeight;
        const padding = 4; /* breathing room around each cut-out */
        const radius = 6;  /* rounded corners on cut-outs */

        const holeMarkup = holes.map(r => {
            const x = Math.round(r.left - padding);
            const y = Math.round(r.top - padding);
            const rw = Math.round(r.width + padding * 2);
            const rh = Math.round(r.height + padding * 2);
            return `<rect x="${x}" y="${y}" width="${rw}" height="${rh}" rx="${radius}" ry="${radius}" fill="black"/>`;
        }).join('');

        return `
            <svg id="tutorial-spotlight-overlay"
                 width="${w}" height="${h}"
                 viewBox="0 0 ${w} ${h}"
                 preserveAspectRatio="none"
                 style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; z-index:9998; pointer-events:none;">
                <defs>
                    <mask id="tutorial-spotlight-mask" maskUnits="userSpaceOnUse">
                        <rect width="${w}" height="${h}" fill="white"/>
                        ${holeMarkup}
                    </mask>
                </defs>
                <rect width="${w}" height="${h}"
                      fill="black" fill-opacity="0.5"
                      mask="url(#tutorial-spotlight-mask)"/>
            </svg>`;
    }

    /**
     * The set of screen-space rects to cut out of the dim:
     *   - every currently-tracked highlight target
     *   - the open #ui-card (read its text, click its action buttons)
     *
     * Caller treats an empty list as "fullscreen dim" — useful for
     * info steps that have no on-screen target.
     */
    computeSpotlightHoles() {
        const rects = [];

        const push = ($el, pad = 0) => {
            if (!$el || !$el.length) {
                return;
            }
            const r = $el[0].getBoundingClientRect();
            if (r.width > 0 && r.height > 0) {
                rects.push({
                    top: r.top - pad,
                    left: r.left - pad,
                    width: r.width + pad * 2,
                    height: r.height + pad * 2
                });
            }
        };

        // Each highlight may carry a padding so the cut-out stays in
        // sync with the visible gold-bordered box.
        this.highlights.forEach(item => push(item.$element, item.padding || 0));

        // The character card. Its action buttons sit in .card-actions
        // which is position:absolute (out of normal flow), so #ui-card's
        // own bounding rect cuts off any actions past the first ~3.
        // Push both rects so every action button stays un-dimmed.
        push($('#ui-card:visible'));
        push($('#ui-card:visible .card-actions'));

        return rects;
    }

    /**
     * Re-render the spotlight on resize / scroll so the holes follow
     * their elements. Idempotent — only binds once per highlighter
     * instance.
     */
    bindSpotlightReposition() {
        if (this.spotlightRepositionBound) {
            return;
        }
        this.spotlightRepositionBound = true;

        const reposition = () => {
            if (!$('#tutorial-spotlight-overlay').length) {
                return; /* nothing to redraw */
            }
            this.refreshSpotlight();
        };

        window.addEventListener('resize', reposition);
        window.addEventListener('scroll', reposition, true);
    }

    /**
     * Rebuild the spotlight markup in place (preserves visibility,
     * skips fade-in) so resize/scroll updates feel instant.
     */
    refreshSpotlight() {
        const wasVisible = $('#tutorial-spotlight-overlay').is(':visible');
        $('#tutorial-spotlight-overlay').remove();

        const holes = this.computeSpotlightHoles();
        const $overlay = $(this.buildSpotlightSvg(holes));
        if (wasVisible) {
            $overlay.css('display', 'block');
        }
        $('body').append($overlay);
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
