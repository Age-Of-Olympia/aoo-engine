/**
 * TutorialTooltip - Smart tooltip system for tutorial
 *
 * Features:
 * - Smart positioning (doesn't go off-screen)
 * - Multiple position options (top, bottom, left, right, center)
 * - Error message support
 * - Auto-dismiss for errors
 */
class TutorialTooltip {
    constructor() {
        this.$tooltip = null;
        this.errorTimeout = null;
        this.currentTargetSelector = null;
        this.currentPosition = 'center';
        this.repositionObserver = null;
        this._repositionPending = false;
    }

    /**
     * Show tooltip
     *
     * @param {string} title Tooltip title
     * @param {string} text Tooltip content (HTML allowed)
     * @param {string|null} targetSelector Element to point to
     * @param {string} position Position (top, bottom, left, right, center)
     */
    show(title, text, targetSelector = null, position = 'center') {
        // Update existing tooltip if present, otherwise create new
        if (this.$tooltip && this.$tooltip.length > 0) {
            // Update existing tooltip
            this.$tooltip.find('.tooltip-title').text(title);
            this.$tooltip.find('.tooltip-text').html(text);
            this.$tooltip.removeClass('top bottom left right center').addClass(position);
        } else {
            // Create new tooltip
            // Only create arrow if NOT centered (center tooltips don't need arrows)
            const arrowHtml = position !== 'center' ? '<div class="tooltip-arrow"></div>' : '';
            this.$tooltip = $(`
                <div class="tutorial-tooltip ${position}">
                    ${arrowHtml}
                    <div class="tooltip-content">
                        <h3 class="tooltip-title">${title}</h3>
                        <div class="tooltip-text">${text}</div>
                        <button id="tutorial-next" class="btn-tutorial-primary">
                            Suivant →
                        </button>
                    </div>
                </div>
            `);

            $('body').append(this.$tooltip);

            // Fade in new tooltip
            this.$tooltip.fadeIn(300);
        }

        // Remove any error messages
        $('.tooltip-error').remove();

        // Store current target and position for repositioning
        this.currentTargetSelector = targetSelector;
        this.currentPosition = position;

        // Position tooltip
        this.repositionTooltip();

        // Setup MutationObserver to track DOM changes and reposition tooltip
        this.setupRepositionObserver();

        console.log('[TutorialTooltip] Shown', { title, targetSelector, position });
    }

    /**
     * Reposition tooltip based on current target
     */
    repositionTooltip() {
        if (this.currentTargetSelector) {
            const $target = $(this.currentTargetSelector);
            if ($target.length > 0) {
                this.positionNear($target, this.currentPosition);
            } else {
                // Target not found, center it
                this.positionCenter();
            }
        } else {
            this.positionCenter();
        }
    }

    /**
     * Setup MutationObserver to reposition tooltip when DOM changes
     */
    setupRepositionObserver() {
        // Disconnect previous observer if exists
        if (this.repositionObserver) {
            this.repositionObserver.disconnect();
        }

        // Only observe if we have a target to follow
        if (!this.currentTargetSelector) {
            return;
        }

        // Watch for DOM changes that might affect tooltip position
        this.repositionObserver = new MutationObserver(() => {
            // Use requestAnimationFrame to debounce rapid changes
            if (!this._repositionPending) {
                this._repositionPending = true;
                requestAnimationFrame(() => {
                    this.repositionTooltip();
                    this._repositionPending = false;
                });
            }
        });

        // Observe body for any changes (panels opening, cards appearing, etc.)
        this.repositionObserver.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['style', 'class']
        });
    }

    /**
     * Show error message
     */
    showError(error, hint = null) {
        // Clear existing error timeout
        if (this.errorTimeout) {
            clearTimeout(this.errorTimeout);
        }

        // Add error message to existing tooltip
        if (this.$tooltip) {
            // Remove existing error
            $('.tooltip-error').remove();

            const errorHtml = `
                <div class="tooltip-error">
                    <strong>❌ ${error}</strong>
                    ${hint ? `<p>${hint}</p>` : ''}
                </div>
            `;

            this.$tooltip.find('.tooltip-text').after(errorHtml);

            // Auto-dismiss after 5 seconds
            this.errorTimeout = setTimeout(() => {
                $('.tooltip-error').fadeOut(() => $('.tooltip-error').remove());
            }, 5000);
        }

        console.log('[TutorialTooltip] Error shown', { error, hint });
    }

    /**
     * Hide tooltip
     */
    hide() {
        if (this.$tooltip) {
            this.$tooltip.fadeOut(200, () => {
                this.$tooltip.remove();
                this.$tooltip = null;
            });
        }

        if (this.errorTimeout) {
            clearTimeout(this.errorTimeout);
            this.errorTimeout = null;
        }

        // Disconnect reposition observer
        if (this.repositionObserver) {
            this.repositionObserver.disconnect();
            this.repositionObserver = null;
        }

        // Clear target tracking
        this.currentTargetSelector = null;
        this.currentPosition = 'center';
    }

    /**
     * Position tooltip near target element
     */
    positionNear($target, position) {
        const targetOffset = $target.offset();
        const targetWidth = $target.outerWidth();
        const targetHeight = $target.outerHeight();
        const tooltipWidth = this.$tooltip.outerWidth();
        const tooltipHeight = this.$tooltip.outerHeight();
        const windowWidth = $(window).width();
        const windowHeight = $(window).height();

        let top, left;

        switch (position) {
            case 'top':
                top = targetOffset.top - tooltipHeight - 20;
                left = targetOffset.left + (targetWidth / 2) - (tooltipWidth / 2);
                break;

            case 'bottom':
                top = targetOffset.top + targetHeight + 20;
                left = targetOffset.left + (targetWidth / 2) - (tooltipWidth / 2);
                break;

            case 'left':
                top = targetOffset.top + (targetHeight / 2) - (tooltipHeight / 2);
                left = targetOffset.left - tooltipWidth - 20;
                break;

            case 'right':
                top = targetOffset.top + (targetHeight / 2) - (tooltipHeight / 2);
                // Add extra spacing for map tiles to avoid covering them
                left = targetOffset.left + targetWidth + 80;
                break;

            default: // center
                this.positionCenter();
                return;
        }

        // Boundary detection - don't go off screen
        if (left < 10) left = 10;
        if (left + tooltipWidth > windowWidth - 10) {
            left = windowWidth - tooltipWidth - 10;
        }

        if (top < 10) top = 10;
        if (top + tooltipHeight > windowHeight - 10) {
            top = windowHeight - tooltipHeight - 10;
        }

        // Apply position - clear all position properties to avoid conflicts
        this.$tooltip.css({
            position: 'absolute',
            top: `${top}px`,
            left: `${left}px`,
            bottom: 'auto',   // Clear bottom to prevent stretching
            right: 'auto',    // Clear right to prevent stretching
            width: 'auto',    // Clear width
            height: 'auto'    // Clear height
        });
    }

    /**
     * Position tooltip at bottom-left (default position, doesn't block map)
     */
    positionCenter() {
        this.$tooltip.css({
            position: 'fixed',
            bottom: '20px',
            left: '20px',
            top: 'auto',
            right: 'auto',
            transform: 'none',
            width: 'auto',    // Clear any previous width
            height: 'auto'    // Clear any previous height that would stretch it
        });
    }

    /**
     * Update tooltip text (without repositioning)
     */
    updateText(text) {
        if (this.$tooltip) {
            this.$tooltip.find('.tooltip-text').html(text);
        }
    }

    /**
     * Enable/disable next button
     */
    enableNext(enabled = true) {
        if (this.$tooltip) {
            $('#tutorial-next').prop('disabled', !enabled);
        }
    }
}

// Export for global use
window.TutorialTooltip = TutorialTooltip;
