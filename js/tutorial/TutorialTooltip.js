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
        this.positionManager = new TutorialPositionManager();
        this.trackingId = null;
    }

    /**
     * Show tooltip
     *
     * @param {string} title Tooltip title
     * @param {string} text Tooltip content (HTML allowed)
     * @param {string|null} targetSelector Element to point to
     * @param {string} position Position (top, bottom, left, right, center)
     * @param {boolean} requiresValidation If true, hide "Suivant" button (user must interact with game)
     */
    show(title, text, targetSelector = null, position = 'center', requiresValidation = false) {
        // Update existing tooltip if present, otherwise create new
        if (this.$tooltip && this.$tooltip.length > 0) {
            // Update existing tooltip
            this.$tooltip.find('.tooltip-title').text(title);
            this.$tooltip.find('.tooltip-text').html(text);
            this.$tooltip.removeClass('top bottom left right center').addClass(position);

            // Handle "Suivant" button based on validation requirement
            const $nextButton = this.$tooltip.find('#tutorial-next');
            if (requiresValidation) {
                // Hide button if it exists
                $nextButton.hide();
            } else {
                // Show button if it exists, or create it if it doesn't
                if ($nextButton.length > 0) {
                    $nextButton.show();
                } else {
                    // Button doesn't exist, create it
                    this.$tooltip.find('.tooltip-content').append(`
                        <button id="tutorial-next" class="btn-tutorial-primary">
                            Suivant →
                        </button>
                    `);
                }
            }
        } else {
            // Create new tooltip
            // Only create arrow if NOT centered (center tooltips don't need arrows)
            const arrowHtml = position !== 'center' ? '<div class="tooltip-arrow"></div>' : '';
            // Only show "Suivant" button if validation is NOT required
            const nextButtonHtml = requiresValidation ? '' : `
                <button id="tutorial-next" class="btn-tutorial-primary">
                    Suivant →
                </button>
            `;

            this.$tooltip = $(`
                <div class="tutorial-tooltip ${position}">
                    ${arrowHtml}
                    <div class="tooltip-content">
                        <h3 class="tooltip-title">${title}</h3>
                        <div class="tooltip-text">${text}</div>
                        ${nextButtonHtml}
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

        console.log('[TutorialTooltip] Shown', { title, targetSelector, position, requiresValidation });
    }

    /**
     * Reposition tooltip based on current target
     */
    repositionTooltip() {
        if (!this.$tooltip) {
            return;
        }

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
        // Untrack previous if exists
        if (this.trackingId) {
            this.positionManager.untrack(this.trackingId);
            this.trackingId = null;
        }

        // Always track for window resize (even centered tooltips can be affected by viewport changes)
        // Only skip if no tooltip exists
        if (!this.$tooltip) {
            return;
        }

        // Use shared position manager for automatic repositioning
        this.trackingId = `tooltip_${Date.now()}`;
        this.positionManager.track(this.trackingId, this.$tooltip, () => {
            this.repositionTooltip();
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

        // Untrack from position manager
        if (this.trackingId) {
            this.positionManager.untrack(this.trackingId);
            this.trackingId = null;
        }

        // Clear target tracking
        this.currentTargetSelector = null;
        this.currentPosition = 'center';
    }

    /**
     * Position tooltip near target element
     */
    positionNear($target, position) {
        // Use shared position manager for accurate positioning
        const pos = TutorialPositionManager.getElementPosition($target);

        const tooltipWidth = this.$tooltip.outerWidth();
        const tooltipHeight = this.$tooltip.outerHeight();
        const windowWidth = $(window).width();
        const windowHeight = $(window).height();

        let top, left;

        switch (position) {
            case 'top':
                top = pos.top - tooltipHeight - 20;
                left = pos.left + (pos.width / 2) - (tooltipWidth / 2);
                break;

            case 'bottom':
                top = pos.top + pos.height + 20;
                left = pos.left + (pos.width / 2) - (tooltipWidth / 2);
                break;

            case 'left':
                top = pos.top + (pos.height / 2) - (tooltipHeight / 2);
                left = pos.left - tooltipWidth - 20;
                break;

            case 'right':
                top = pos.top + (pos.height / 2) - (tooltipHeight / 2);
                // Add extra spacing for map tiles to avoid covering them
                left = pos.left + pos.width + 80;
                break;

            default: // center
                this.positionCenter();
                return;
        }

        // Boundary detection - ONLY adjust horizontal position to stay on screen
        // Don't adjust vertical position as it causes tooltip to jump when viewport height changes
        const originalTop = top;
        const originalLeft = left;

        // Only adjust left/right to prevent going off-screen horizontally
        if (left < 10) left = 10;
        if (left + tooltipWidth > windowWidth - 10) {
            left = windowWidth - tooltipWidth - 10;
        }

        // For vertical: only prevent going above the top, but allow going below viewport
        // This prevents the tooltip from jumping when dev tools open/close
        if (top < 10) top = 10;
        // Remove bottom boundary check - let it go below viewport if needed

        // Debug: log if position changed due to boundary detection
        if (top !== originalTop || left !== originalLeft) {
            console.log('[TutorialTooltip] Boundary adjusted', {
                original: { top: originalTop, left: originalLeft },
                adjusted: { top, left },
                window: { width: windowWidth, height: windowHeight },
                tooltip: { width: tooltipWidth, height: tooltipHeight }
            });
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
     * Position tooltip at center of screen (truly centered, doesn't block specific elements)
     */
    positionCenter() {
        this.$tooltip.css({
            position: 'fixed',
            top: '50%',
            left: '50%',
            transform: 'translate(-50%, -50%)',
            bottom: 'auto',
            right: 'auto',
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
