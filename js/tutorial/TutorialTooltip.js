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

        // Drag state
        this.isDragging = false;
        this.dragStartX = 0;
        this.dragStartY = 0;
        this.draggedPosition = null; // Store custom position after drag
    }

    /**
     * Show tooltip
     *
     * @param {string} title Tooltip title
     * @param {string} text Tooltip content (HTML allowed)
     * @param {string|null} targetSelector Element to point to
     * @param {string} position Position (top, bottom, left, right, center, center-top, center-bottom)
     * @param {boolean} requiresValidation If true, hide "Suivant" button (user must interact with game)
     */
    async show(title, text, targetSelector = null, position = 'center', requiresValidation = false) {
        // Update existing tooltip if present, otherwise create new
        if (this.$tooltip && this.$tooltip.length > 0) {
            // Hide existing tooltip briefly for smooth transition
            this.$tooltip.fadeOut(150, async () => {
                // Update content while hidden
                this.$tooltip.find('.tooltip-title').text(title);
                this.$tooltip.find('.tooltip-text').html(text);
                this.$tooltip.removeClass('top bottom left right center center-top center-bottom').addClass(position);

                // Handle arrow - add if needed for directional positions, remove for centered
                const needsArrow = position !== 'center' && position !== 'center-top' && position !== 'center-bottom';
                const $existingArrow = this.$tooltip.find('.tooltip-arrow');

                if (needsArrow && $existingArrow.length === 0) {
                    // Need arrow but don't have one - add it
                    this.$tooltip.prepend('<div class="tooltip-arrow"></div>');
                } else if (!needsArrow && $existingArrow.length > 0) {
                    // Don't need arrow but have one - remove it
                    $existingArrow.remove();
                }

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

                // Remove any error messages
                $('.tooltip-error').remove();

                // Reset dragged position if target or position changed
                const targetChanged = this.currentTargetSelector !== targetSelector;
                const positionChanged = this.currentPosition !== position;
                if (targetChanged || positionChanged) {
                    this.draggedPosition = null;
                }

                // Store current target and position for repositioning
                this.currentTargetSelector = targetSelector;
                this.currentPosition = position;

                // Wait for target element to be ready if specified
                if (targetSelector) {
                    console.log(`[TutorialTooltip] Waiting for element: ${targetSelector}`);
                    await this.waitForElementReady(targetSelector);
                    console.log(`[TutorialTooltip] Element ready, positioning...`);
                }

                // Clear all positioning CSS from previous step (especially transforms!)
                // Then make tooltip visible but invisible (for dimension calculation)
                this.$tooltip.css({
                    display: 'block',
                    visibility: 'hidden',
                    opacity: 0,
                    transform: 'none',  // Clear any transforms from centered positions
                    top: 'auto',
                    left: 'auto',
                    bottom: 'auto',
                    right: 'auto'
                });

                // Wait for CSS to apply and browser to render
                await new Promise(resolve => requestAnimationFrame(resolve));

                // Position tooltip (now with correct dimensions and applied CSS)
                this.repositionTooltip();

                // Debug: Log tooltip and target positions after repositioning
                if (targetSelector) {
                    const $target = $(targetSelector);
                    if ($target.length > 0) {
                        const targetRect = $target[0].getBoundingClientRect();
                        const tooltipRect = this.$tooltip[0].getBoundingClientRect();
                        console.log(`[TutorialTooltip] Positioned - Target: (${targetRect.left}, ${targetRect.top}) ${targetRect.width}x${targetRect.height}, Tooltip: (${tooltipRect.left}, ${tooltipRect.top}) ${tooltipRect.width}x${tooltipRect.height}`);
                    }
                }

                // Now hide it properly before fading in
                this.$tooltip.css({
                    display: 'none',
                    visibility: 'visible',
                    opacity: 1
                });

                // Setup MutationObserver to track DOM changes and reposition tooltip
                this.setupRepositionObserver();

                // Enable dragging for center-positioned tooltips on desktop
                this.setupDragging();

                // Fade in tooltip
                if (this.$tooltip) {
                    this.$tooltip.fadeIn(300);
                }
            });

            return; // Exit early - rest is handled in fadeOut callback
        } else {
            // Create new tooltip
            // Only create arrow if NOT centered (center tooltips don't need arrows)
            const arrowHtml = position !== 'center' && position !== 'center-top' && position !== 'center-bottom' ? '<div class="tooltip-arrow"></div>' : '';
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

            // Store current target and position for repositioning
            this.currentTargetSelector = targetSelector;
            this.currentPosition = position;

            // Wait for target element to be ready if specified
            if (targetSelector) {
                console.log(`[TutorialTooltip] Waiting for element: ${targetSelector}`);
                await this.waitForElementReady(targetSelector);
                console.log(`[TutorialTooltip] Element ready, positioning...`);
            }

            // Make tooltip visible but invisible (for dimension calculation)
            // Keep it this way until after positioning
            this.$tooltip.css({
                display: 'block',
                visibility: 'hidden',
                opacity: 0
            });

            // Wait for CSS to apply and browser to render
            await new Promise(resolve => requestAnimationFrame(resolve));

            // Position tooltip (now with correct dimensions and applied CSS)
            this.repositionTooltip();

            // Debug: Log tooltip and target positions after repositioning
            if (targetSelector) {
                const $target = $(targetSelector);
                if ($target.length > 0) {
                    const targetRect = $target[0].getBoundingClientRect();
                    const tooltipRect = this.$tooltip[0].getBoundingClientRect();
                    console.log(`[TutorialTooltip] Positioned - Target: (${targetRect.left}, ${targetRect.top}) ${targetRect.width}x${targetRect.height}, Tooltip: (${tooltipRect.left}, ${tooltipRect.top}) ${tooltipRect.width}x${tooltipRect.height}`);

                    // Monitor if target position changes after we positioned the tooltip
                    setTimeout(() => {
                        const newTargetRect = $target[0].getBoundingClientRect();
                        if (newTargetRect.left !== targetRect.left || newTargetRect.top !== targetRect.top) {
                            console.warn(`[TutorialTooltip] TARGET MOVED AFTER POSITIONING! Was: (${targetRect.left}, ${targetRect.top}), Now: (${newTargetRect.left}, ${newTargetRect.top})`);
                        }
                    }, 500);
                }
            }

            // Now hide it properly before fading in
            this.$tooltip.css({
                display: 'none',
                visibility: 'visible',
                opacity: 1
            });

            // Setup MutationObserver to track DOM changes and reposition tooltip
            this.setupRepositionObserver();

            // Enable dragging for center-positioned tooltips on desktop
            this.setupDragging();

            // Fade in tooltip
            if (this.$tooltip) {
                this.$tooltip.fadeIn(300);
            }
        }

        console.log('[TutorialTooltip] Shown', { title, targetSelector, position, requiresValidation });
    }

    /**
     * Wait for an element to be ready (visible, positioned, and stable)
     *
     * @param {string} selector CSS selector for target element
     * @param {number} maxWaitMs Maximum time to wait in milliseconds
     * @returns {Promise<jQuery>} Promise that resolves when element is ready
     */
    waitForElementReady(selector, maxWaitMs = 2000) {
        return new Promise((resolve) => {
            const startTime = Date.now();
            let lastWidth = 0;
            let lastHeight = 0;
            let lastTop = 0;
            let lastLeft = 0;
            let stableCount = 0;
            const requiredStableFrames = 3; // Increased from 2 to 3 for better reliability

            const checkElement = () => {
                const $element = $(selector);

                // Element doesn't exist yet
                if ($element.length === 0) {
                    if (Date.now() - startTime < maxWaitMs) {
                        requestAnimationFrame(checkElement);
                    } else {
                        console.warn(`[TutorialTooltip] Element ${selector} not found after ${maxWaitMs}ms`);
                        resolve(null);
                    }
                    return;
                }

                // Get element dimensions and position
                const rect = $element[0].getBoundingClientRect();
                const width = rect.width;
                const height = rect.height;
                const top = rect.top;
                const left = rect.left;
                const isVisible = $element.is(':visible') && width > 0 && height > 0;

                if (!isVisible) {
                    // Element exists but not visible yet
                    if (Date.now() - startTime < maxWaitMs) {
                        requestAnimationFrame(checkElement);
                    } else {
                        console.warn(`[TutorialTooltip] Element ${selector} not visible after ${maxWaitMs}ms`);
                        resolve($element); // Return anyway, might work
                    }
                    return;
                }

                // Check if both dimensions AND position are stable (not changing)
                const dimensionsStable = (width === lastWidth && height === lastHeight);
                const positionStable = (top === lastTop && left === lastLeft);

                if (dimensionsStable && positionStable) {
                    stableCount++;
                    // Wait for N consecutive stable frames to ensure everything is settled
                    if (stableCount >= requiredStableFrames) {
                        console.log(`[TutorialTooltip] Element ${selector} ready - size: ${width}x${height}, pos: (${left}, ${top})`);
                        resolve($element);
                        return;
                    }
                } else {
                    // Reset counter if anything changed
                    if (stableCount > 0) {
                        console.log(`[TutorialTooltip] Element ${selector} still settling - size: ${width}x${height}, pos: (${left}, ${top})`);
                    }
                    stableCount = 0;
                    lastWidth = width;
                    lastHeight = height;
                    lastTop = top;
                    lastLeft = left;
                }

                // Continue checking
                if (Date.now() - startTime < maxWaitMs) {
                    requestAnimationFrame(checkElement);
                } else {
                    console.warn(`[TutorialTooltip] Element ${selector} not fully stable after ${maxWaitMs}ms, using anyway - final: ${width}x${height} at (${left}, ${top})`);
                    resolve($element);
                }
            };

            // Start checking on next frame
            requestAnimationFrame(checkElement);
        });
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
                // Target not found, center it with correct vertical alignment
                this.applyCenterPosition(this.currentPosition);
            }
        } else {
            // No target, use center position with correct vertical alignment
            this.applyCenterPosition(this.currentPosition);
        }
    }

    /**
     * Apply center positioning based on position string
     * @param {string} position Position string (e.g., 'center', 'center-top', 'center-bottom')
     */
    applyCenterPosition(position) {
        let verticalAlign = 'center';

        if (position === 'center-top') {
            verticalAlign = 'top';
        } else if (position === 'center-bottom') {
            verticalAlign = 'bottom';
        }

        this.positionCenter(verticalAlign);
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
     * Setup dragging for center-positioned tooltips on desktop
     */
    setupDragging() {
        if (!this.$tooltip) {
            return;
        }

        // Only enable dragging for:
        // 1. Center-positioned tooltips
        // 2. Desktop view (viewport width > 768px)
        const isDesktop = $(window).width() > 768;
        const isCentered = this.currentPosition === 'center';

        if (!isDesktop || !isCentered) {
            this.$tooltip.removeClass('draggable');
            return;
        }

        // Add draggable class for cursor styling
        this.$tooltip.addClass('draggable');

        // Remove previous drag handlers if they exist
        this.$tooltip.off('mousedown.drag');

        // Add drag handlers
        this.$tooltip.on('mousedown.drag', (e) => {
            // Only drag from title or tooltip content, not buttons
            if ($(e.target).is('button') || $(e.target).closest('button').length > 0) {
                return;
            }

            this.isDragging = true;
            this.dragStartX = e.clientX;
            this.dragStartY = e.clientY;

            // Get current position
            const tooltipRect = this.$tooltip[0].getBoundingClientRect();
            const tooltipLeft = tooltipRect.left;
            const tooltipTop = tooltipRect.top;

            // Add dragging class for cursor feedback
            this.$tooltip.addClass('dragging');

            // Mouse move handler
            const onMouseMove = (moveEvent) => {
                if (!this.isDragging) return;

                const deltaX = moveEvent.clientX - this.dragStartX;
                const deltaY = moveEvent.clientY - this.dragStartY;

                const newLeft = tooltipLeft + deltaX;
                const newTop = tooltipTop + deltaY;

                // Apply new position
                this.$tooltip.css({
                    position: 'fixed',
                    left: `${newLeft}px`,
                    top: `${newTop}px`,
                    transform: 'none' // Remove center transform
                });

                // Store dragged position
                this.draggedPosition = { left: newLeft, top: newTop };
            };

            // Mouse up handler
            const onMouseUp = () => {
                this.isDragging = false;
                this.$tooltip.removeClass('dragging');
                $(document).off('mousemove.drag mouseup.drag');
            };

            // Attach document-level handlers
            $(document).on('mousemove.drag', onMouseMove);
            $(document).on('mouseup.drag', onMouseUp);

            e.preventDefault(); // Prevent text selection
        });

        console.log('[TutorialTooltip] Drag enabled', { isDesktop, isCentered });
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
            // Remove drag handlers
            this.$tooltip.off('mousedown.drag');
            $(document).off('mousemove.drag mouseup.drag');

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

        // Clear target tracking and drag state
        this.currentTargetSelector = null;
        this.currentPosition = 'center';
        this.isDragging = false;
        this.draggedPosition = null; // Reset dragged position for next tooltip
    }

    /**
     * Position tooltip near target element
     */
    positionNear($target, position) {
        // Get viewport-relative position for fixed positioning
        // Don't use TutorialPositionManager because it adds scroll offset for absolute positioning
        const rect = $target[0].getBoundingClientRect();
        const pos = {
            top: rect.top,
            left: rect.left,
            width: rect.width,
            height: rect.height
        };

        // Get tooltip dimensions (should be visible at this point via show() method)
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

            case 'center':
            case 'center-center':
                this.positionCenter('center');
                return;

            case 'center-top':
                this.positionCenter('top');
                return;

            case 'center-bottom':
                this.positionCenter('bottom');
                return;

            default:
                this.positionCenter('center');
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
        // Use 'fixed' to be consistent with CSS and avoid scroll offset issues
        this.$tooltip.css({
            position: 'fixed',
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
     * If user has dragged the tooltip, preserve that position
     *
     * @param {string} verticalAlign Vertical alignment: 'center', 'top', 'bottom'
     */
    positionCenter(verticalAlign = 'center') {
        // If tooltip has been manually dragged, keep it there
        if (this.draggedPosition) {
            this.$tooltip.css({
                position: 'fixed',
                left: `${this.draggedPosition.left}px`,
                top: `${this.draggedPosition.top}px`,
                transform: 'none',
                bottom: 'auto',
                right: 'auto',
                width: 'auto',
                height: 'auto'
            });
        } else {
            // Position based on vertical alignment
            let css = {
                position: 'fixed',
                left: '50%',
                bottom: 'auto',
                right: 'auto',
                width: 'auto',
                height: 'auto'
            };

            switch(verticalAlign) {
                case 'top':
                    // Position near top with fixed pixel offset for predictability
                    css.top = '80px';
                    css.transform = 'translate(-50%, 0)';
                    break;

                case 'bottom':
                    // Position near bottom using top offset to ensure visibility
                    // Use calc to position from bottom while keeping it on-screen
                    css.top = 'auto';
                    css.bottom = '80px';
                    css.transform = 'translateX(-50%)';
                    break;

                case 'center':
                default:
                    // True center - both horizontally and vertically
                    css.top = '50%';
                    css.transform = 'translate(-50%, -50%)';
                    break;
            }

            this.$tooltip.css(css);
        }
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
