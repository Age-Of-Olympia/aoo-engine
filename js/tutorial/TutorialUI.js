/**
 * TutorialUI - Main tutorial UI controller
 *
 * Manages the complete tutorial user interface including:
 * - API communication
 * - Step rendering
 * - Progress bar display (step progression + earned XP)
 * - Navigation controls
 */
class TutorialUI {
    constructor() {
        this.currentSession = null;
        this.currentStep = null;  // step_id (string)
        this.currentStepPosition = 0;  // actual position (number for display)
        this.totalSteps = 0;
        this.xpEarned = 0;
        this.level = 1;
        this.pi = 0;

        // Component references
        this.tooltip = null;
        this.highlighter = null;
        this.navigator = null;

        // State
        this.isActive = false;

        // UI observers for validation
        this.panelObserver = null;
    }

    /**
     * Initialize tutorial UI components
     */
    init() {
        // Will be initialized with tooltip, highlighter, navigator
        console.log('[TutorialUI] Initialized');
    }

    /**
     * Start new tutorial
     */
    async start(mode = 'first_time') {
        try {
            console.log('[TutorialUI] Starting tutorial...', { mode });

            const response = await this.apiCall('/api/tutorial/start.php', {
                mode: mode,
                version: '1.0.0'
            });

            if (response.success) {
                this.currentSession = response.session_id;
                this.currentStep = response.current_step;  // step_id (string)
                this.currentStepPosition = response.current_step_position || 1;  // position (number)
                this.totalSteps = response.total_steps;
                this.isActive = true;

                console.log('[TutorialUI] Tutorial started', response);

                // Reload page to switch to tutorial character view
                if (response.reload_required) {
                    console.log('[TutorialUI] Reloading to tutorial map...');
                    // Set flag to auto-resume after reload
                    sessionStorage.setItem('tutorial_just_started', 'true');
                    window.location.reload();
                    return true;
                }

                // Activate tutorial UI
                this.activateTutorialUI(response.step_data);

                return true;
            } else {
                console.error('[TutorialUI] Failed to start tutorial', response);
                alert('Impossible de démarrer le tutoriel: ' + (response.error || 'Erreur inconnue'));
                return false;
            }
        } catch (error) {
            console.error('[TutorialUI] Start error', error);
            const errorMsg = error.message || 'Erreur inconnue';
            alert(`Impossible de démarrer le tutoriel: ${errorMsg}\n\nVeuillez recharger la page et réessayer.`);
            return false;
        }
    }

    /**
     * Resume existing tutorial
     */
    async resume() {
        try {
            console.log('[TutorialUI] Checking for resume...');

            const response = await this.apiCall('/api/tutorial/resume.php', {}, 'GET');
            console.log('[TutorialUI] Resume API response:', response);

            if (response.success && response.has_active_tutorial) {
                this.currentSession = response.session_id;
                this.currentStep = response.current_step;  // step_id (string)
                this.currentStepPosition = response.current_step_position || 1;  // position (number)
                this.totalSteps = response.total_steps;
                this.xpEarned = response.xp_earned;
                this.level = response.level || 1;
                this.pi = response.pi || 0;
                this.isActive = true;

                console.log('[TutorialUI] Resuming tutorial - current_step:', this.currentStep, 'step_id:', response.step_data?.step_id);

                // Activate tutorial UI
                this.activateTutorialUI(response.step_data);

                // Update XP bar with resumed progress
                this.updateXPBar();

                return true;
            } else {
                console.log('[TutorialUI] No active tutorial to resume - response:', response);
                return false;
            }
        } catch (error) {
            console.error('[TutorialUI] Resume error', error);
            alert(`Erreur lors de la reprise du tutoriel: ${error.message || 'Erreur inconnue'}\n\nVeuillez recharger la page.`);
            return false;
        }
    }

    /**
     * Activate tutorial UI (common logic for start and resume)
     *
     * This method ensures the correct order:
     * 1. Create overlay first
     * 2. Render step (which applies interaction mode to the overlay)
     * 3. Mark as active in sessionStorage
     *
     * @param {Object} stepData - Step data to render
     */
    activateTutorialUI(stepData) {
        // Close any open panels to start with a clean state
        this.closeAllPanels();

        // Show tutorial overlay FIRST (must exist before renderStep)
        this.showTutorialOverlay();

        // Render step (which applies interaction mode to overlay)
        if (stepData) {
            this.renderStep(stepData);
        }

        // Mark tutorial as actively running (for auto-resume after page reload)
        sessionStorage.setItem('tutorial_active', 'true');
    }

    /**
     * Close all UI panels for a clean tutorial start
     */
    closeAllPanels() {
        // Close characteristics panel if open
        const caracsPanel = document.querySelector('#caracs-panel');
        if (caracsPanel && caracsPanel.style.display !== 'none') {
            $('#caracs-panel').hide();
            console.log('[TutorialUI] Closed characteristics panel');
        }

        // Close inventory panel if open
        const inventoryPanel = document.querySelector('#inventory-panel');
        if (inventoryPanel && inventoryPanel.style.display !== 'none') {
            $('#inventory-panel').hide();
            console.log('[TutorialUI] Closed inventory panel');
        }

        // Close any open UI card
        const uiCard = document.querySelector('#ui-card');
        if (uiCard) {
            $('#ui-card').hide();
            console.log('[TutorialUI] Closed UI card');
        }
    }

    /**
     * Notify tutorial of game action (called by game code)
     *
     * Example: window.tutorialUI.notifyAction('movement', { from: [x1,y1], to: [x2,y2] })
     *
     * @param {string} actionType - Type of action performed
     * @param {object} actionData - Action data for validation
     * @param {boolean} skipUIUpdate - If true, save progress but don't update UI (for pre-reload)
     */
    notifyAction(actionType, actionData = {}, skipUIUpdate = false) {
        console.log('[TutorialUI] Action notification:', actionType, actionData, 'skipUI:', skipUIUpdate);

        if (!this.stepData) {
            console.log('[TutorialUI] No current step data, ignoring action notification');
            return;
        }

        const requiresValidation = this.stepData.requires_validation;
        const allowManualAdvance = this.stepData.config?.allow_manual_advance;

        // If step has manual advance enabled and doesn't require validation,
        // it should ONLY advance via the manual Next button, not via notifyAction
        if (allowManualAdvance && !requiresValidation) {
            console.log('[TutorialUI] Step has manual advance and no validation, ignoring notifyAction (use Next button instead)');
            return;
        }

        // Auto-advance with validation data
        // The server-side validation will determine if this action is valid for the current step
        this.next(actionData, skipUIUpdate);
    }

    /**
     * Advance to next step
     *
     * @param {object} validationData - Data to validate the current step
     * @param {boolean} skipUIUpdate - If true, save progress but don't update UI
     * @param {boolean} showFeedbackOnFailure - If true, show hint and shake when validation fails (for manual button clicks)
     */
    async next(validationData = {}, skipUIUpdate = false, showFeedbackOnFailure = false) {
        try {
            console.log('[TutorialUI] Advancing to next step...', {
                validationData,
                currentSession: this.currentSession,
                skipUIUpdate: skipUIUpdate,
                showFeedbackOnFailure: showFeedbackOnFailure
            });

            if (!this.currentSession) {
                console.error('[TutorialUI] ERROR: No current session! Cannot advance.');
                alert('Erreur: Session tutoriel introuvable. Veuillez redémarrer le tutoriel.');
                return false;
            }

            // Hide tooltip immediately when validation is triggered
            // This prevents confusion during the API call / page reload
            if (this.tooltip && !skipUIUpdate) {
                this.tooltip.hide();
            }

            // Use special handling for advance - don't throw on validation failures
            const response = await this.apiCallWithValidation('/api/tutorial/advance.php', {
                session_id: this.currentSession,
                validation_data: validationData
            });

            if (response.success) {
                console.log('[TutorialUI] ✅ ADVANCE SUCCESSFUL! New step:', response.current_step);

                // Check if the COMPLETED step wanted to auto-close the card
                if (this.stepData?.config?.auto_close_card && !skipUIUpdate) {
                    console.log('[TutorialUI] Completed step requested auto-close card, closing...');
                    const closeBtn = document.querySelector('.close-card, #ui-card .close');
                    if (closeBtn) {
                        closeBtn.click();
                        console.log('[TutorialUI] Card closed via button click');
                    } else {
                        $('#ui-card').hide();
                        console.log('[TutorialUI] Card hidden via jQuery');
                    }
                }

                if (response.completed) {
                    // Tutorial complete!
                    console.log('[TutorialUI] ✅ TUTORIAL COMPLETED! Response:', response);
                    if (!skipUIUpdate) {
                        this.onTutorialComplete(response);
                    } else {
                        console.warn('[TutorialUI] Skipping completion UI update (skipUIUpdate=true)');
                    }
                } else {
                    // Update state
                    console.log('[TutorialUI] Previous step was:', this.currentStep);
                    this.currentStep = response.current_step;  // step_id (string)
                    this.currentStepPosition = response.current_step_position || this.currentStepPosition + 1;  // position (number)
                    this.xpEarned = response.xp_earned;
                    this.level = response.level;
                    this.pi = response.pi;
                    console.log('[TutorialUI] Updated to step:', this.currentStep, 'position:', this.currentStepPosition);
                    console.log('[TutorialUI] sessionStorage.tutorial_active is:', sessionStorage.getItem('tutorial_active'));

                    // Only update UI if not skipping (e.g., before page reload)
                    if (!skipUIUpdate) {
                        // Update XP bar
                        this.updateXPBar();

                        // Render next step
                        if (response.step_data) {
                            console.log('[TutorialUI] ✅ Advance SUCCESS - rendering new step:', response.step_data.step_id);
                            this.renderStep(response.step_data);
                        } else {
                            console.error('[TutorialUI] ⚠️ Advance SUCCESS but no step_data in response!', response);
                        }
                    } else {
                        console.log('[TutorialUI] Skipping UI update - page will reload. sessionStorage.tutorial_active:', sessionStorage.getItem('tutorial_active'));
                        console.log('[TutorialUI] Next step that should load on new page:', response.current_step);
                    }
                }

                return true;
            } else {
                // Validation not met yet
                console.log('[TutorialUI] Validation not met yet:', response.hint || response.error);

                // Only show feedback if this was a manual button click
                if (showFeedbackOnFailure) {
                    const hint = response.hint || response.error || 'Suivez les instructions du tutoriel.';
                    this.showBlockedInteractionWarning(hint);
                }

                return false;
            }
        } catch (error) {
            console.error('[TutorialUI] Advance error', error);
            alert(`Erreur lors de l'avancement du tutoriel: ${error.message || 'Erreur inconnue'}\n\nSi le problème persiste, essayez de quitter et reprendre le tutoriel.`);
            return false;
        }
    }

    /**
     * Render tutorial step
     */
    async renderStep(stepData) {
        console.log('[TutorialUI] Rendering step', stepData);
        console.log('[TutorialUI] renderStep() called from:', new Error().stack);

        // Store stepData for external access (e.g., E2E tests)
        this.stepData = stepData;

        // Note: auto_close_card is now handled when the step COMPLETES in next() function,
        // not when the step STARTS. This matches the UI label "Auto-close action card on step complete".

        // Clear previous highlights (wait for fade animation to complete)
        if (this.highlighter) {
            await this.highlighter.clearAll();
        }

        // Clear previous allowed interactions
        $('.tutorial-allowed-element').removeClass('tutorial-allowed-element');

        // Clear previous warning/hint messages from previous step
        $('.tooltip-blocked-message').remove();

        // Clear previous observers
        this.cleanupObservers();

        // Ensure required panels are in correct state BEFORE applying interaction mode
        // Wait for panels to be ready before showing tooltip/highlight
        await this.ensurePanelVisibility(stepData);

        // Apply interaction mode (blocking, semi-blocking, or open)
        this.applyInteractionMode(stepData);

        // Ensure prerequisites are met
        this.ensurePrerequisites(stepData);

        // Auto-check validation for steps that can complete without user action
        // (e.g., movement_depleted when player loads page with 0 movements)
        if (stepData.requires_validation) {
            setTimeout(() => {
                this.checkAutoValidation(stepData);
            }, 1000); // Wait 1s for page to fully load
        }

        // Setup UI observers for validation (ui_interaction steps)
        this.setupUIObservers(stepData);

        // Check if step has a show_delay (for steps that need UI to settle first)
        const showDelay = stepData.config?.show_delay || 0;

        if (showDelay > 0) {
            console.log(`[TutorialUI] Delaying tooltip/highlight by ${showDelay}ms`);
            await new Promise(resolve => setTimeout(resolve, showDelay));
        }

        // Show step tooltip (now after panel is ready)
        this.showStepTooltip(stepData);

        // Highlight target element if specified (now after panel is ready)
        if (stepData.target_selector && this.highlighter) {
            console.log('[TutorialUI] Creating highlight for step:', stepData.step_id, 'selector:', stepData.target_selector);
            this.highlighter.highlight(stepData.target_selector, {
                pulsate: stepData.requires_validation
            });
        }

        // Highlight additional elements (e.g., counter values)
        if (stepData.config?.additional_highlights && this.highlighter) {
            const additionalHighlights = stepData.config.additional_highlights;
            if (Array.isArray(additionalHighlights)) {
                console.log('[TutorialUI] Creating additional highlights for step:', stepData.step_id, 'count:', additionalHighlights.length);
                additionalHighlights.forEach(selector => {
                    // Don't re-highlight the main target
                    if (selector !== stepData.target_selector) {
                        console.log('[TutorialUI] Additional highlight:', selector);
                        this.highlighter.highlight(selector, { pulsate: false });
                    }
                });
            }
        }

        // Check for auto-advance flag
        if (stepData.config?.auto_advance_delay) {
            const delay = parseInt(stepData.config.auto_advance_delay);
            console.log(`[TutorialUI] Step will auto-advance in ${delay}ms`);

            setTimeout(() => {
                console.log('[TutorialUI] Auto-advancing step...');
                this.next({}, false);
            }, delay);
        }

        // Update progress indicator
        this.updateProgressIndicator();

        // Update navigator buttons
        if (this.navigator) {
            this.navigator.update(stepData);
        }
    }

    /**
     * Show step tooltip
     */
    showStepTooltip(stepData) {
        const title = stepData.title || 'Tutoriel';
        const text = stepData.text || '';
        const targetSelector = stepData.target_selector;
        const position = stepData.tooltip_position || 'center';

        // Determine if we should hide the "Suivant" button:
        // - Hide if validation is required AND it's NOT a manual-advance step
        // - Show if no validation required OR if it's a manual-advance step (clicking button IS the validation)
        const isManualAdvance = stepData.config?.validation_type === 'ui_interaction' &&
                               stepData.config?.validation_params?.element_clicked === 'tutorial_next';
        const hideNextButton = Boolean(stepData.requires_validation) && !isManualAdvance;

        console.log('[TutorialUI] showStepTooltip', {
            step_id: stepData.step_id,
            requires_validation_raw: stepData.requires_validation,
            isManualAdvance: isManualAdvance,
            hideNextButton: hideNextButton
        });

        if (this.tooltip) {
            this.tooltip.show(title, text, targetSelector, position, hideNextButton);
        } else {
            // Fallback: simple display
            console.log('[TutorialUI] Tooltip not initialized, showing alert');
            console.log(`${title}: ${text}`);
        }
    }

    /**
     * Show validation error
     */
    showValidationError(error, hint) {
        if (this.tooltip) {
            this.tooltip.showError(error, hint);
        } else {
            alert(`Erreur: ${error}\n${hint || ''}`);
        }
    }

    /**
     * Show tutorial overlay
     */
    showTutorialOverlay() {
        // Remove existing overlay if any
        $('#tutorial-overlay').remove();

        // Create overlay
        const $overlay = $('<div id="tutorial-overlay"></div>');
        $('body').append($overlay);

        // Create controls container
        const initialProgress = (this.currentStepPosition / this.totalSteps) * 100;
        const $controls = $(`
            <div id="tutorial-controls">
                <div id="tutorial-progress">
                    <span id="tutorial-step-counter">Étape ${this.currentStepPosition}</span>
                </div>
                <div id="tutorial-xp-bar">
                    <div class="xp-bar-container">
                        <div class="xp-bar-fill" style="width: ${Math.min(initialProgress, 100)}%"></div>
                    </div>
                    <div class="xp-text">XP gagné: ${this.xpEarned}</div>
                </div>
                <div class="tutorial-controls-buttons">
                    <button id="tutorial-skip" class="btn-tutorial-secondary">Passer le tutoriel</button>
                </div>
            </div>
        `);
        $('body').append($controls);

        // Bind skip button - use event delegation for reliability
        $(document).off('click', '#tutorial-skip').on('click', '#tutorial-skip', () => {
            console.log('[TutorialUI] Skip button handler fired');
            this.skip();
        });

        console.log('[TutorialUI] Overlay shown');
    }

    /**
     * Apply interaction mode to step
     */
    applyInteractionMode(stepData) {
        const mode = stepData.interaction_mode || this.getDefaultInteractionMode(stepData.step_type);
        const $overlay = $('#tutorial-overlay');

        // Remove previous mode classes
        $overlay.removeClass('blocking semi-blocking open');

        // Remove ALL event handlers from overlay (from previous modes)
        $overlay.off('click');

        // Remove previous event blocker (from semi-blocking mode)
        if (this.eventBlocker) {
            console.log('[TutorialUI] Removing previous event blocker');
            document.removeEventListener('click', this.eventBlocker, true);
            this.eventBlocker = null;
        }

        // Disconnect observer from previous mode
        if (this.domObserver) {
            this.domObserver.disconnect();
            this.domObserver = null;
        }

        console.log('[TutorialUI] Applying interaction mode:', mode);

        if (mode === 'blocking') {
            // Full blocking - show overlay, disable all game interactions
            $overlay.addClass('blocking').show();
            this.setupOverlayClickHandler(stepData);

        } else if (mode === 'semi-blocking') {
            // Partial blocking - allow only specific elements
            $overlay.addClass('semi-blocking').show();

            // Make overlay transparent to clicks so they pass through to elements underneath
            // The event handler in capture phase will still intercept and block non-allowed clicks
            $overlay.css('pointer-events', 'none');

            this.allowSpecificInteractions(stepData.config?.allowed_interactions || []);
            this.setupSemiBlockingEventHandler(stepData);

        } else if (mode === 'open') {
            // No blocking
            $overlay.addClass('open').hide();
        }
    }

    /**
     * Get default interaction mode based on step type
     */
    getDefaultInteractionMode(stepType) {
        const defaults = {
            'info': 'blocking',
            'welcome': 'blocking',
            'dialog': 'blocking',
            'movement': 'semi-blocking',
            'movement_limit': 'semi-blocking',
            'action': 'semi-blocking',
            'action_intro': 'blocking',
            'combat': 'semi-blocking',
            'combat_intro': 'blocking',
            'exploration': 'open',
            'ui_interaction': 'semi-blocking'
        };

        return defaults[stepType] || 'blocking';
    }

    /**
     * Setup click handler on overlay (for blocking mode)
     */
    setupOverlayClickHandler(stepData) {
        const $overlay = $('#tutorial-overlay');

        $overlay.off('click').on('click', (e) => {
            e.preventDefault();
            e.stopPropagation();

            // Get blocked click message
            const message = stepData.config?.blocked_click_message ||
                           stepData.config?.target_description ?
                           `Pour continuer, ${stepData.config.target_description}.` :
                           'Suivez les instructions du tutoriel pour continuer.';

            // Show message in tooltip + shake
            this.showBlockedInteractionWarning(message);
        });
    }

    /**
     * Setup event handler for semi-blocking mode
     * Intercepts clicks in capture phase, allows clicks on allowed elements only
     */
    setupSemiBlockingEventHandler(stepData) {
        console.log('[TutorialUI] Setting up semi-blocking event handler');

        const allowedSelectors = stepData.config?.allowed_interactions || [];
        const validationType = stepData.config?.validation_type;

        // Create event blocker function
        this.eventBlocker = (e) => {
            const target = e.target;

            console.log('[TutorialUI] Click detected on:', target, 'classes:', target.className);

            // Always allow clicks on tutorial UI elements
            if ($(target).closest('#tutorial-controls, .tutorial-tooltip, #tutorial-next').length > 0 ||
                $(target).is('#tutorial-next')) {
                console.log('[TutorialUI] ✅ Tutorial UI click allowed');
                return; // Allow clicks on tutorial UI
            }

            // Check if click is on an allowed element
            for (const selector of allowedSelectors) {
                // Use jQuery to check if element matches selector (works with SVG)
                const matches = $(target).is(selector);
                const hasParent = $(target).closest(selector).length > 0;

                console.log(`[TutorialUI] Checking selector "${selector}": matches=${matches}, hasParent=${hasParent}`);

                if (matches || hasParent) {
                    console.log('[TutorialUI] ✅ Click allowed on:', selector, target);

                    // Track click for UI interaction validation
                    if (validationType === 'ui_interaction') {
                        // Check if this is a navigation link - prevent default to let trackElementClick handle navigation
                        const $link = $(target).closest('a[href]');
                        if ($link.length > 0 && $link.attr('href') && !$link.attr('href').startsWith('#')) {
                            e.preventDefault();
                            e.stopPropagation();
                        }
                        this.trackElementClick(selector, target, stepData);
                    }

                    // Check if this is an action button click (for action_used validation)
                    if (validationType === 'action_used') {
                        const $target = $(target).closest('button.action, .action[data-action]');
                        if ($target.length > 0) {
                            const actionName = $target.data('action') || $target.attr('data-action');
                            if (actionName) {
                                console.log('[TutorialUI] Action button clicked during tutorial:', actionName);

                                // Don't notify immediately - wait for action to actually execute
                                // Actions require 2 clicks: 1st expands button, 2nd executes
                                // We'll watch for the action result to appear in .card-text
                                this.watchForActionResult(actionName);
                            }
                        }
                    }

                    return; // Allow the click to proceed
                }
            }

            // Block all other clicks
            console.log('[TutorialUI] ❌ Click blocked - no matching selector found');
            e.stopPropagation();
            e.preventDefault();

            // Show warning
            const message = stepData.config?.blocked_click_message || 'Suivez les instructions du tutoriel.';
            this.showBlockedInteractionWarning(message);
        };

        // Add event listener in capture phase (before jQuery handlers)
        document.addEventListener('click', this.eventBlocker, true);
    }

    /**
     * Show blocked interaction warning in tooltip
     */
    showBlockedInteractionWarning(message) {
        const $tooltip = $('.tutorial-tooltip');

        // Remove any existing warning
        $('.tooltip-blocked-message').remove();

        // Add warning to tooltip
        const $warning = $(`
            <div class="tooltip-blocked-message">
                <p>
                    <span class="tooltip-warning"><i class="ra ra-crossed-sabres"></i></span>
                    ${message}
                </p>
            </div>
        `);

        $('.tooltip-content').append($warning);

        // Shake the tooltip
        $tooltip.addClass('shake');
        setTimeout(() => {
            $tooltip.removeClass('shake');
        }, 600);

        // Auto-remove warning after 4 seconds
        setTimeout(() => {
            $warning.fadeOut(() => $warning.remove());
        }, 4000);
    }

    /**
     * Track element click for UI interaction validation
     * Sends validation data to advance the tutorial when the expected element is clicked
     */
    trackElementClick(selector, targetElement, stepData) {
        console.log('[TutorialUI] Tracking click for validation:', selector);

        // Check if this is a navigation link - if so, we need to wait for validation
        const $link = $(targetElement).closest('a[href]');
        const isNavigationLink = $link.length > 0 && $link.attr('href') && !$link.attr('href').startsWith('#');

        if (isNavigationLink) {
            const href = $link.attr('href');
            console.log('[TutorialUI] Navigation link clicked, advancing step before navigation to:', href);

            // Send validation and wait for it before navigating
            this.next({
                element_clicked: selector,
                target_selector: stepData.config?.target_selector
            }, true).then(() => {  // skipUIUpdate=true since we're navigating anyway
                console.log('[TutorialUI] Step advanced, now navigating to:', href);
                window.location.href = href;
            }).catch(err => {
                console.error('[TutorialUI] Failed to advance step, navigating anyway:', err);
                window.location.href = href;
            });
            return;
        }

        // Extract the selector for validation
        const elementClicked = selector;

        // Send validation data to advance the step
        this.notifyAction('ui_interaction', {
            element_clicked: elementClicked,
            target_selector: stepData.config?.target_selector
        });
    }

    /**
     * Allow specific interactions (semi-blocking mode)
     */
    allowSpecificInteractions(selectors) {
        console.log('[TutorialUI] Allowing interactions:', selectors);

        // Store selectors for re-application
        this.allowedSelectors = selectors;

        // Function to apply allowed class
        const applyAllowedClasses = () => {
            selectors.forEach(selector => {
                const $elements = $(selector);
                console.log(`[TutorialUI] Found ${$elements.length} elements for selector: ${selector}`);
                $elements.addClass('tutorial-allowed-element');
            });

            const totalAllowed = $('.tutorial-allowed-element').length;
            console.log(`[TutorialUI] Total allowed elements: ${totalAllowed}`);
        };

        // Apply initially
        applyAllowedClasses();

        // Watch for DOM changes and re-apply (for when game updates tiles)
        if (this.domObserver) {
            this.domObserver.disconnect();
        }

        this.domObserver = new MutationObserver(() => {
            console.log('[TutorialUI] DOM changed, re-applying allowed classes');
            applyAllowedClasses();
        });

        // Observe the main game container for changes
        const gameContainer = document.body;
        this.domObserver.observe(gameContainer, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class']
        });
    }

    /**
     * Setup UI observers for validation
     * Automatically detects when UI interactions occur and sends validation
     */
    setupUIObservers(stepData) {
        // Setup observers for ANY step that requires validation with UI-related validation types
        if (!stepData.requires_validation) {
            return;
        }

        const validationType = stepData.config?.validation_type;

        // Only setup observers for UI-related validation types
        const uiValidationTypes = ['ui_panel_opened', 'ui_element_hidden', 'ui_interaction', 'ui_button_clicked'];
        if (!uiValidationTypes.includes(validationType)) {
            return;
        }
        console.log('[TutorialUI] Setting up UI observer for:', validationType);

        if (validationType === 'ui_panel_opened') {
            const panel = stepData.config?.validation_params?.panel;
            if (panel === 'characteristics') {
                this.setupPanelVisibilityObserver('#load-caracs', 'characteristics');
            } else if (panel === 'actions') {
                this.setupActionsPanelObserver();
            } else if (panel === 'inventory') {
                this.setupPanelVisibilityObserver('#inventory-panel', 'inventory');
            }
        } else if (validationType === 'ui_element_hidden') {
            const element = stepData.config?.validation_params?.element;
            if (element) {
                this.setupElementHiddenObserver(element);
            }
        }
    }

    /**
     * Setup MutationObserver to watch for panel visibility
     * Automatically sends validation when panel becomes visible
     */
    setupPanelVisibilityObserver(panelSelector, panelType) {
        const panel = document.querySelector(panelSelector);
        if (!panel) {
            console.warn('[TutorialUI] Panel not found:', panelSelector);
            return;
        }

        console.log('[TutorialUI] Setting up panel visibility observer for:', panelSelector);

        this.panelObserver = new MutationObserver((mutations) => {
            // Check if panel is now visible
            const isVisible = $(panelSelector).is(':visible');

            if (isVisible && this.isActive) {
                console.log('[TutorialUI] Panel became visible, sending validation');

                // Send validation
                window.notifyTutorial('ui_interaction', {
                    panel: panelType,
                    panel_visible: true,
                    timestamp: Date.now()
                });

                // Disconnect observer - we only need to detect this once
                if (this.panelObserver) {
                    this.panelObserver.disconnect();
                    this.panelObserver = null;
                }
            }
        });

        // Observe style and attribute changes on the panel
        this.panelObserver.observe(panel, {
            attributes: true,
            attributeFilter: ['style', 'class']
        });

        // Also check immediately in case panel is already visible
        const isVisible = $(panelSelector).is(':visible');
        if (isVisible && this.isActive) {
            console.log('[TutorialUI] Panel already visible on setup');
            window.notifyTutorial('ui_interaction', {
                panel: panelType,
                panel_visible: true,
                timestamp: Date.now()
            });
        }
    }

    /**
     * Setup MutationObserver to watch for actions panel (player card in #ajax-data)
     * Automatically sends validation when actions panel is opened
     */
    setupActionsPanelObserver() {
        const ajaxData = document.querySelector('#ajax-data');
        if (!ajaxData) {
            console.warn('[TutorialUI] #ajax-data not found');
            return;
        }

        console.log('[TutorialUI] Setting up actions panel observer');

        this.panelObserver = new MutationObserver((mutations) => {
            // Check if #ajax-data contains any card content (actions, case-infos, or ui-card)
            const hasActions = $('#ajax-data .action, #ajax-data button.action').length > 0;
            const hasCaseInfos = $('#ajax-data .case-infos').length > 0;
            const hasUiCard = $('#ui-card').length > 0 && $('#ui-card').is(':visible');
            const hasAnyContent = $('#ajax-data').children().length > 0;

            if ((hasActions || hasCaseInfos || hasUiCard || hasAnyContent) && this.isActive) {
                console.log('[TutorialUI] Actions panel opened, sending validation (hasActions:', hasActions, 'hasCaseInfos:', hasCaseInfos, 'hasUiCard:', hasUiCard, ')');

                // Send validation
                window.notifyTutorial('ui_interaction', {
                    panel: 'actions',
                    panel_visible: true,
                    timestamp: Date.now()
                });

                // Disconnect observer - we only need to detect this once
                if (this.panelObserver) {
                    this.panelObserver.disconnect();
                    this.panelObserver = null;
                }
            }
        });

        // Observe content changes in #ajax-data
        this.panelObserver.observe(ajaxData, {
            childList: true,
            subtree: true
        });

        // Also check immediately in case panel is already open
        const hasActionsNow = $('#ajax-data .action, #ajax-data button.action').length > 0;
        const hasCaseInfosNow = $('#ajax-data .case-infos').length > 0;
        const hasUiCardNow = $('#ui-card').length > 0 && $('#ui-card').is(':visible');
        const hasAnyContentNow = $('#ajax-data').children().length > 0;
        if ((hasActionsNow || hasCaseInfosNow || hasUiCardNow || hasAnyContentNow) && this.isActive) {
            console.log('[TutorialUI] Actions panel already visible on setup');
            window.notifyTutorial('ui_interaction', {
                panel: 'actions',
                panel_visible: true,
                timestamp: Date.now()
            });
        }
    }

    /**
     * Setup MutationObserver to watch for element hiding
     * Automatically sends validation when element is hidden
     */
    setupElementHiddenObserver(elementSelector) {
        const targetElement = document.querySelector(elementSelector);

        // Check immediately if element is already hidden/doesn't exist
        const isAlreadyHidden = !targetElement || !$(elementSelector).is(':visible');
        if (isAlreadyHidden && this.isActive) {
            console.log('[TutorialUI] Element already hidden/not found, auto-advancing');
            window.notifyTutorial('ui_interaction', {
                element: elementSelector,
                is_hidden: true,
                timestamp: Date.now()
            });
            return;
        }

        if (!targetElement) {
            console.warn('[TutorialUI] Element not found for hidden observer:', elementSelector);
            return;
        }

        console.log('[TutorialUI] Setting up element hidden observer for:', elementSelector);

        this.panelObserver = new MutationObserver((mutations) => {
            // Check if element is now hidden
            const element = document.querySelector(elementSelector);
            const isHidden = !element || !$(elementSelector).is(':visible');

            if (isHidden && this.isActive) {
                console.log('[TutorialUI] Element hidden, sending validation');

                // Send validation
                window.notifyTutorial('ui_interaction', {
                    element: elementSelector,
                    is_hidden: true,
                    timestamp: Date.now()
                });

                // Disconnect observer - we only need to detect this once
                if (this.panelObserver) {
                    this.panelObserver.disconnect();
                    this.panelObserver = null;
                }
            }
        });

        // Observe style and attribute changes on the element and its parent
        this.panelObserver.observe(targetElement, {
            attributes: true,
            attributeFilter: ['style', 'class']
        });

        // Also observe if element is removed from DOM
        if (targetElement.parentNode) {
            this.panelObserver.observe(targetElement.parentNode, {
                childList: true,
                subtree: true
            });
        }

    }

    /**
     * Cleanup all observers
     */
    cleanupObservers() {
        if (this.panelObserver) {
            console.log('[TutorialUI] Cleaning up panel observer');
            this.panelObserver.disconnect();
            this.panelObserver = null;
        }
    }

    /**
     * Ensure step prerequisites are met
     */
    ensurePrerequisites(stepData) {
        const prereqs = stepData.config?.prerequisites || {};

        if (Object.keys(prereqs).length === 0) {
            return; // No prerequisites
        }

        console.log('[TutorialUI] Checking prerequisites:', prereqs);

        // Note: Actual resource restoration happens server-side in TutorialContext
        // This is just for client-side display/validation

        if (prereqs.mvt !== undefined) {
            console.log(`[TutorialUI] Step requires ${prereqs.mvt} movement(s)`);
            // Refresh characteristics panel if it's open (to show updated movement count)
            this.refreshCaracsPanel();
        }

        if (prereqs.actions !== undefined) {
            console.log(`[TutorialUI] Step requires ${prereqs.actions} action point(s)`);
            // Refresh characteristics panel if it's open
            this.refreshCaracsPanel();
        }

        if (prereqs.ensure_enemy) {
            console.log(`[TutorialUI] Step requires enemy: ${prereqs.ensure_enemy}`);
        }

        if (prereqs.ensure_item) {
            console.log(`[TutorialUI] Step requires item: ${prereqs.ensure_item}`);
        }
    }

    /**
     * Ensure required panels are visible/hidden based on step requirements
     */
    ensurePanelVisibility(stepData) {
        // Use target_selector from stepData directly, not from config
        const targetSelector = stepData.target_selector;

        console.log('[TutorialUI] ensurePanelVisibility called with targetSelector:', targetSelector);

        if (!targetSelector) {
            console.log('[TutorialUI] No target selector, skipping panel visibility');
            return Promise.resolve(); // Return resolved promise if nothing to do
        }

        // If step targets an action button, ensure player actions panel is open
        if (targetSelector.includes('.action[data-action=')) {
            console.log('[TutorialUI] Detected action button target, checking panel...');
            const $actionsPanel = $('#ui-card');

            if (!$actionsPanel.is(':visible')) {
                console.log('[TutorialUI] Opening player actions panel for step');

                // Return a promise that resolves when panel is ready
                return new Promise((resolve) => {
                    // Store resolve callback for later
                    this.panelReadyCallback = resolve;

                    // Get player coords from window (set in TutorialView.php)
                    let playerCoords = window.dataCoords;

                    // Fallback: if dataCoords not available yet, wait briefly for it
                    if (!playerCoords) {
                        console.log('[TutorialUI] window.dataCoords not ready, waiting briefly...');

                        let coordsRetries = 0;
                        const maxCoordsRetries = 3; // Max 300ms - if not ready quickly, use avatar method

                        const waitForCoords = () => {
                            if (window.dataCoords) {
                                console.log('[TutorialUI] window.dataCoords now available:', window.dataCoords);
                                playerCoords = window.dataCoords;
                                this.openPlayerCardDirect(playerCoords);
                            } else if (coordsRetries < maxCoordsRetries) {
                                coordsRetries++;
                                setTimeout(waitForCoords, 100);
                            } else {
                                console.log('[TutorialUI] window.dataCoords not available after 300ms, using avatar fallback');
                                this.openPlayerCardViaAvatar();
                            }
                        };

                        waitForCoords();
                    } else {
                        // Coords available, open card directly
                        this.openPlayerCardDirect(playerCoords);
                    }
                });
            } else {
                console.log('[TutorialUI] Actions panel already visible');
                return Promise.resolve(); // Panel already visible
            }
        }

        // If step targets characteristics panel or movement/action counters, ensure panel is open
        const caracsPanelTargets = ['#load-caracs', '#mvt-counter', '#action-counter'];
        const needsCaracsPanel = caracsPanelTargets.some(selector =>
            targetSelector.includes(selector) || targetSelector === selector
        );

        if (needsCaracsPanel) {
            const $caracsPanel = $('#load-caracs');

            if (!$caracsPanel.is(':visible')) {
                console.log('[TutorialUI] Opening characteristics panel for step');

                // Return a promise that resolves when panel is ready
                return new Promise((resolve) => {
                    // Simulate clicking the show caracs button
                    $('#show-caracs').click();

                    // Wait for panel to fully appear before continuing
                    // This prevents tooltips from rendering before panel is visible
                    let retries = 0;
                    const maxRetries = 50; // Max 5 seconds (50 * 100ms)

                    const checkPanelVisible = async () => {
                        if ($('#load-caracs').is(':visible')) {
                            console.log('[TutorialUI] Panel now visible, refreshing...');
                            await this.refreshCaracsPanel(); // Wait for panel to be fully refreshed
                            resolve(); // Panel ready
                        } else if (retries < maxRetries) {
                            // Panel not visible yet, check again
                            retries++;
                            setTimeout(checkPanelVisible, 100);
                        } else {
                            // Give up after max retries
                            console.error('[TutorialUI] Panel failed to appear after 5 seconds, continuing anyway');
                            resolve(); // Continue anyway
                        }
                    };

                    setTimeout(checkPanelVisible, 100);
                });
            } else {
                // Panel already open, just refresh to show current values
                return this.refreshCaracsPanel(); // Return the promise
            }
        }

        // No panel needed, return resolved promise
        return Promise.resolve();
    }

    /**
     * Open player card directly by making AJAX call to observe.php
     */
    openPlayerCardDirect(coords) {
        console.log('[TutorialUI] Opening player card via direct AJAX call with coords:', coords);

        $.ajax({
            type: "POST",
            url: 'observe.php',
            data: {'coords': coords},
            success: (data) => {
                console.log('[TutorialUI] observe.php returned, loading card into #ajax-data');
                $('#ajax-data').html(data);

                // Cache the result like view.js does
                window.clickedCases = window.clickedCases || {};
                window.clickedCases[coords] = data;

                // Wait for panel to appear
                this.waitForActionsPanel();
            },
            error: (xhr, status, error) => {
                console.error('[TutorialUI] Failed to load player card:', error);
            }
        });
    }

    /**
     * Open player card by finding coords from avatar element (fallback method)
     */
    openPlayerCardViaAvatar() {
        console.log('[TutorialUI] Trying to get coords from avatar element...');

        const $avatar = $('#current-player-avatar');
        if ($avatar.length > 0) {
            // Avatar has x,y attributes - use these to find the .case element
            const avatarX = $avatar.attr('x');
            const avatarY = $avatar.attr('y');

            console.log('[TutorialUI] Avatar at position x:', avatarX, 'y:', avatarY);

            // Find the .case element at this position
            const $case = $(`.case[x="${avatarX}"][y="${avatarY}"]`);

            if ($case.length > 0) {
                const coords = $case.data('coords');
                console.log('[TutorialUI] Found case at avatar position with coords:', coords);

                if (coords) {
                    this.openPlayerCardDirect(coords);
                } else {
                    console.error('[TutorialUI] Case found but has no data-coords attribute!');
                }
            } else {
                console.error('[TutorialUI] No .case element found at avatar position');
            }
        } else {
            console.error('[TutorialUI] Avatar not found!');
        }
    }

    /**
     * Wait for actions panel to appear after clicking player tile
     */
    waitForActionsPanel() {
        let retries = 0;
        const maxRetries = 50; // Max 5 seconds
        const checkPanelVisible = () => {
            if ($('#ui-card').is(':visible')) {
                console.log('[TutorialUI] Actions panel now visible');

                // Resolve the promise if callback exists
                if (this.panelReadyCallback) {
                    console.log('[TutorialUI] Calling panelReadyCallback - tooltip can now be shown');
                    this.panelReadyCallback();
                    this.panelReadyCallback = null;
                }
            } else if (retries < maxRetries) {
                retries++;
                setTimeout(checkPanelVisible, 100);
            } else {
                console.error('[TutorialUI] Actions panel failed to appear after 5 seconds');

                // Resolve anyway to prevent hanging
                if (this.panelReadyCallback) {
                    console.log('[TutorialUI] Timeout - resolving anyway to prevent hang');
                    this.panelReadyCallback();
                    this.panelReadyCallback = null;
                }
            }
        };
        setTimeout(checkPanelVisible, 100);
    }

    /**
     * Refresh characteristics panel if it's currently open
     */
    refreshCaracsPanel() {
        // Only refresh if panel is visible
        if ($('#load-caracs').is(':visible')) {
            console.log('[TutorialUI] Refreshing characteristics panel...');
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: "POST",
                    url: "load_caracs.php",
                    success: function(data) {
                        $("#load-caracs").html(data);
                        console.log('[TutorialUI] Characteristics panel refreshed');
                        // Wait a bit more for DOM to settle and elements to be fully positioned
                        setTimeout(() => resolve(), 100);
                    },
                    error: function() {
                        console.error('[TutorialUI] Failed to refresh characteristics panel');
                        reject();
                    }
                });
            });
        } else {
            return Promise.resolve();
        }
    }

    /**
     * Hide tutorial overlay
     */
    /**
     * Watch for action result to appear in DOM (after action executes)
     */
    watchForActionResult(actionName) {
        console.log('[TutorialUI] Watching for action result:', actionName);

        // Check if already watching
        if (this.actionResultObserver) {
            console.log('[TutorialUI] Already watching for action result, skipping');
            return;
        }

        // Watch for changes in .card-text (where action results appear)
        const cardText = document.querySelector('.card-text');
        if (!cardText) {
            console.warn('[TutorialUI] .card-text not found, cannot watch for action result');
            return;
        }

        this.actionResultObserver = new MutationObserver((mutations) => {
            // Check if action result text appeared (not just "Lancé de dés...")
            const content = cardText.textContent;
            console.log('[TutorialUI] Card text changed. Content:', content.substring(0, 100));
            console.log('[TutorialUI] Contains dice?', content.includes('Lancé de dés'));
            console.log('[TutorialUI] Content length:', content.trim().length);

            if (content && !content.includes('Lancé de dés') && content.trim().length > 10) {
                console.log('[TutorialUI] ✅ Action result detected! Notifying with actionName:', actionName);

                // Disconnect observer
                if (this.actionResultObserver) {
                    this.actionResultObserver.disconnect();
                    this.actionResultObserver = null;
                }

                // Notify tutorial system
                this.notifyAction('action_used', {
                    action_name: actionName,
                    result_detected: true
                });
            }
        });

        // Observe changes to card-text
        this.actionResultObserver.observe(cardText, {
            childList: true,
            subtree: true,
            characterData: true
        });

        console.log('[TutorialUI] Action result observer started for:', actionName);
    }

    hideTutorialOverlay() {
        $('#tutorial-overlay').fadeOut(() => $('#tutorial-overlay').remove());
        $('#tutorial-controls').fadeOut(() => $('#tutorial-controls').remove());

        // Clean up event blocker
        if (this.eventBlocker) {
            document.removeEventListener('click', this.eventBlocker, true);
            this.eventBlocker = null;
        }

        // Clean up action result observer
        if (this.actionResultObserver) {
            this.actionResultObserver.disconnect();
            this.actionResultObserver = null;
        }

        // Clean up observers
        if (this.domObserver) {
            this.domObserver.disconnect();
            this.domObserver = null;
        }

        this.cleanupObservers();

        if (this.tooltip) {
            this.tooltip.hide();
        }
        if (this.highlighter) {
            this.highlighter.clearAll();
        }

        this.isActive = false;
        console.log('[TutorialUI] Overlay hidden');
    }

    /**
     * Update progress bar
     * Shows tutorial step progression and earned XP
     */
    updateXPBar() {
        // Calculate tutorial progression (current step / total steps)
        const barProgress = (this.currentStep / this.totalSteps) * 100;

        // Update progress bar fill
        $('#tutorial-xp-bar .xp-bar-fill').css('width', `${Math.min(barProgress, 100)}%`);

        // Show earned XP (step shown separately in progress indicator)
        const xpText = `XP gagné: ${this.xpEarned}`;
        $('#tutorial-xp-bar .xp-text').text(xpText);
    }

    /**
     * Update progress indicator
     */
    updateProgressIndicator() {
        $('#tutorial-step-counter').text(`Étape ${this.currentStepPosition}`);
    }

    /**
     * Skip/Cancel tutorial
     */
    async skip() {
        if (confirm('Êtes-vous sûr de vouloir annuler le tutoriel? Votre progression sera perdue.')) {
            try {
                const response = await this.apiCall('/api/tutorial/cancel.php', {
                    session_id: this.currentSession
                }, 'POST');

                if (response.success) {
                    this.hideTutorialOverlay();
                    this.currentSession = null;
                    this.isActive = false;

                    // Clear active flag and set cancel flag to prevent auto-check after reload
                    sessionStorage.removeItem('tutorial_active');
                    sessionStorage.setItem('tutorial_just_cancelled', 'true');

                    window.location.reload();
                }
            } catch (error) {
                console.error('[TutorialUI] Cancel error', error);
                alert(`Erreur lors de l'annulation du tutoriel: ${error.message || 'Erreur inconnue'}\n\nVous pouvez fermer cette fenêtre et recharger la page.`);
            }
        }
    }

    /**
     * Tutorial complete
     */
    onTutorialComplete(response) {
        console.log('[TutorialUI] ========================================');
        console.log('[TutorialUI] 🎉 onTutorialComplete() called');
        console.log('[TutorialUI] Response:', JSON.stringify(response, null, 2));
        console.log('[TutorialUI] ========================================');

        // Get redirect delay from step config (in milliseconds, default 5000ms = 5s)
        const redirectDelayMs = response.step_data?.config?.redirect_delay ?? 5000;
        const redirectDelaySec = Math.ceil(redirectDelayMs / 1000);
        console.log('[TutorialUI] Redirect delay:', redirectDelayMs, 'ms (', redirectDelaySec, 'seconds)');

        // Remove any existing modal first
        $('#tutorial-complete-modal').remove();

        // Show completion celebration
        const $modal = $(`
            <div id="tutorial-complete-modal" class="tutorial-celebration">
                <div class="modal-content celebration-content">
                    <div class="celebration-header">
                        <h2>🎉 Tutoriel terminé! 🎉</h2>
                        <div class="celebration-stars">✨ ⭐ ✨</div>
                    </div>
                    <p class="celebration-message">${response.message || 'Félicitations! Vous êtes prêt pour l\'aventure!'}</p>
                    <div class="rewards-display">
                        <div class="reward-item">
                            <span class="reward-icon">⚡</span>
                            <span class="reward-label">XP gagnés</span>
                            <span class="reward-value">${response.xp_earned || 0}</span>
                        </div>
                        <div class="reward-item">
                            <span class="reward-icon">💎</span>
                            <span class="reward-label">PI gagnés</span>
                            <span class="reward-value">${response.pi_earned || 0}</span>
                        </div>
                    </div>
                    <button id="tutorial-complete-continue" class="btn-tutorial-primary celebration-btn">
                        🎮 Commencer l'aventure!
                    </button>
                    <p class="auto-redirect-text" id="auto-redirect-text">
                        Redirection automatique dans <span id="redirect-countdown">${redirectDelaySec}</span>s...
                        <button id="cancel-redirect" class="btn-tutorial-secondary" style="margin-left: 10px; padding: 2px 8px; font-size: 0.9em;">Annuler</button>
                    </p>
                </div>
            </div>
        `);

        $('body').append($modal);
        $modal.fadeIn(600);

        // Auto-redirect countdown (use configured delay)
        let countdown = redirectDelaySec;
        let countdownInterval = setInterval(() => {
            countdown--;
            $('#redirect-countdown').text(countdown);
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                this.completeTutorialAndRedirect();
            }
        }, 1000);

        // Manual redirect on button click
        $('#tutorial-complete-continue').on('click', () => {
            clearInterval(countdownInterval);
            this.completeTutorialAndRedirect();
        });

        // Cancel auto-redirect
        $('#cancel-redirect').on('click', () => {
            clearInterval(countdownInterval);
            $('#auto-redirect-text').fadeOut(300, function() {
                $(this).html('<em style="opacity: 0.7;">Redirection automatique annulée</em>').fadeIn(300);
            });
            console.log('[TutorialUI] Auto-redirect cancelled by user');
        });
    }

    /**
     * Complete tutorial and redirect to main game
     */
    completeTutorialAndRedirect() {
        const $modal = $('#tutorial-complete-modal');
        $modal.fadeOut(400, () => {
            $modal.remove();
            this.hideTutorialOverlay();
            // Clear tutorial state
            sessionStorage.removeItem('tutorial_active');
            sessionStorage.removeItem('tutorial_session_id');
            // Reload to main game (without tutorial mode)
            window.location.href = 'index.php';
        });
    }

    /**
     * API call helper
     */
    async apiCall(url, data = {}, method = 'POST') {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            }
        };

        if (method === 'POST') {
            options.body = JSON.stringify(data);
        }

        const response = await fetch(url, options);

        if (!response.ok) {
            // Try to get error details from response body
            let errorDetails = response.statusText;
            try {
                const errorBody = await response.json();
                errorDetails = errorBody.error || errorBody.message || errorDetails;
                console.error('[TutorialUI] API error details:', errorBody);
            } catch (e) {
                // If response body is not JSON, use statusText
            }
            throw new Error(`HTTP ${response.status}: ${errorDetails}`);
        }

        return await response.json();
    }

    /**
     * API call helper for validation endpoints
     * Unlike apiCall, this returns error responses instead of throwing for 400 status
     */
    async apiCallWithValidation(url, data = {}, method = 'POST') {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            }
        };

        if (method === 'POST') {
            options.body = JSON.stringify(data);
        }

        const response = await fetch(url, options);

        // For validation endpoints, 400 with error response is expected
        if (!response.ok && response.status === 400) {
            // Try to parse error response
            try {
                const errorBody = await response.json();
                console.log('[TutorialUI] Validation response:', errorBody);
                return errorBody; // Return the error response instead of throwing
            } catch (e) {
                // If we can't parse it, throw
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
        }

        // For other errors (401, 404, 500, etc.), throw as usual
        if (!response.ok) {
            let errorDetails = response.statusText;
            try {
                const errorBody = await response.json();
                errorDetails = errorBody.error || errorBody.message || errorDetails;
                console.error('[TutorialUI] API error details:', errorBody);
            } catch (e) {
                // If response body is not JSON, get the text to see what error page was returned
                const errorText = await response.text();
                console.error('[TutorialUI] Non-JSON error response:', errorText.substring(0, 500));
                errorDetails = 'Server error (non-JSON response)';
            }
            throw new Error(`HTTP ${response.status}: ${errorDetails}`);
        }

        // Try to parse JSON response
        // Clone first BEFORE consuming the body
        const responseClone = response.clone();
        try {
            return await response.json();
        } catch (e) {
            // JSON parse failed - probably got HTML error page
            const responseText = await responseClone.text();
            console.error('[TutorialUI] JSON parse failed. Response text:', responseText.substring(0, 1000));
            throw new Error('Server returned invalid JSON (probably an error page). Check console for details.');
        }
    }

    /**
     * Check if step can auto-validate (without user action)
     * Used for steps like "deplete movements" where validation should happen automatically
     */
    async checkAutoValidation(stepData) {
        // Only auto-validate for specific step types
        const autoValidateTypes = ['movement_limit'];

        if (!autoValidateTypes.includes(stepData.step_type)) {
            return; // This step type doesn't support auto-validation
        }

        console.log('[TutorialUI] Checking auto-validation for step', stepData.step_number);

        // Try to validate automatically (e.g., movements_depleted)
        // Use notifyAction which calls this.next() internally
        try {
            // Call notifyAction like the game would, but with auto-validation flag
            this.notifyAction('auto_validation', {
                step_type: stepData.step_type,
                timestamp: Date.now()
            }, false); // Don't skip UI update - we want to advance if valid
        } catch (error) {
            console.error('[TutorialUI] Auto-validation error:', error);
            // Don't show error to user - just silently continue
        }
    }
}

// Export for global use
window.TutorialUI = TutorialUI;
