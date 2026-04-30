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
    }

    /**
     * Start new tutorial
     * @param {string} mode - Tutorial mode (first_time, replay, practice)
     * @param {string} version - Tutorial version (e.g., '1.0.0', '2.0.0-craft')
     */
    async start(mode = 'first_time', version = '1.0.0') {
        try {

            const response = await this.apiCall('/api/tutorial/start.php', {
                mode: mode,
                version: version
            });

            if (response.success) {
                this.currentSession = response.session_id;
                this.currentStep = response.current_step;  // step_id (string)
                this.currentStepPosition = response.current_step_position || 1;  // position (number)
                this.totalSteps = response.total_steps;
                this.isActive = true;


                // Reload page to switch to tutorial character view
                if (response.reload_required) {
                    // Set flag to auto-resume after reload
                    sessionStorage.setItem('tutorial_just_started', 'true');
                    // Reload to clean URL without query parameters to prevent loop
                    window.location.href = 'index.php';
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

            const response = await this.apiCall('/api/tutorial/resume.php', {}, 'GET');

            if (response.success && response.has_active_tutorial) {
                this.currentSession = response.session_id;
                this.currentStep = response.current_step;  // step_id (string)
                this.currentStepPosition = response.current_step_position || 1;  // position (number)
                this.totalSteps = response.total_steps;
                this.xpEarned = response.xp_earned;
                this.level = response.level || 1;
                this.pi = response.pi || 0;
                this.isActive = true;


                // Check if we need to reload to switch to tutorial map
                if (response.reload_required) {
                    // Set flag to auto-resume after reload
                    sessionStorage.setItem('tutorial_just_started', 'true');
                    window.location.reload();
                    return true;
                }

                // Activate tutorial UI
                this.activateTutorialUI(response.step_data);

                // Update XP bar with resumed progress
                this.updateXPBar();

                return true;
            } else {
                return false;
            }
        } catch (error) {
            console.error('[TutorialUI] Resume error', error);

            /* Don't show alert for authentication errors (401) - user is not logged in */
            if (!error.message || !error.message.includes('401')) {
                alert(`Erreur lors de la reprise du tutoriel: ${error.message || 'Erreur inconnue'}\n\nVeuillez recharger la page.`);
            } else {
            }

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
        }

        // Close inventory panel if open
        const inventoryPanel = document.querySelector('#inventory-panel');
        if (inventoryPanel && inventoryPanel.style.display !== 'none') {
            $('#inventory-panel').hide();
        }

        // Close any open UI card
        const uiCard = document.querySelector('#ui-card');
        if (uiCard) {
            $('#ui-card').hide();
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

        if (!this.stepData) {
            return;
        }

        const requiresValidation = this.stepData.config?.requires_validation;
        const allowManualAdvance = this.stepData.config?.allow_manual_advance;

        // If step has manual advance enabled and doesn't require validation,
        // it should ONLY advance via the manual Next button, not via notifyAction
        if (allowManualAdvance && !requiresValidation) {
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
        /* Re-entrancy guard: rapid double-clicks on "Suivant" or two
         * notifyAction() events firing in the same frame would otherwise
         * launch parallel POSTs to /api/tutorial/advance.php, awarding
         * duplicate step XP and racing the step pointer past the next step.
         */
        if (this.isAdvancing) {
            return false;
        }
        this.isAdvancing = true;

        try {

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

                /* Check if the COMPLETED step requires page reload for next step (e.g., MVT restoration) */
                if (this.stepData?.config?.prepare_next_step?.reload === 'true' || this.stepData?.config?.prepare_next_step?.reload === true) {
                    window.location.reload();
                    return true;
                }

                // Check if the COMPLETED step wanted to auto-close the card
                if (this.stepData?.config?.auto_close_card && !skipUIUpdate) {
                    const closeBtn = document.querySelector('.close-card, #ui-card .close');
                    if (closeBtn) {
                        closeBtn.click();
                    } else {
                        $('#ui-card').hide();
                    }
                }

                if (response.completed) {
                    // Tutorial complete!
                    if (!skipUIUpdate) {
                        this.onTutorialComplete(response);
                    } else {
                        console.warn('[TutorialUI] Skipping completion UI update (skipUIUpdate=true)');
                    }
                } else {
                    // Update state
                    this.currentStep = response.current_step;  // step_id (string)
                    this.currentStepPosition = response.current_step_position || this.currentStepPosition + 1;  // position (number)
                    this.xpEarned = response.xp_earned;
                    this.level = response.level;
                    this.pi = response.pi;

                    // Only update UI if not skipping (e.g., before page reload)
                    if (!skipUIUpdate) {
                        // Update XP bar
                        this.updateXPBar();

                        // Render next step
                        if (response.step_data) {
                            this.renderStep(response.step_data);
                        } else {
                            console.error('[TutorialUI] ⚠️ Advance SUCCESS but no step_data in response!', response);
                        }
                    } else {
                    }
                }

                return true;
            } else {
                // Validation not met yet

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
        } finally {
            this.isAdvancing = false;
        }
    }

    /**
     * Render tutorial step
     */
    async renderStep(stepData) {

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
        if (stepData.config?.requires_validation) {
            setTimeout(() => {
                this.checkAutoValidation(stepData);
            }, 1000); // Wait 1s for page to fully load
        }

        // Setup UI observers for validation (ui_interaction steps)
        this.setupUIObservers(stepData);

        // Check if step has a show_delay (for steps that need UI to settle first)
        const showDelay = stepData.config?.show_delay || 0;

        if (showDelay > 0) {
            await new Promise(resolve => setTimeout(resolve, showDelay));
        }

        // Show step tooltip (now after panel is ready)
        this.showStepTooltip(stepData);

        // Highlight target element if specified (now after panel is ready)
        if (stepData.target_selector && this.highlighter) {
            this.highlighter.highlight(stepData.target_selector, {
                pulsate: stepData.config?.requires_validation
            });
        }

        // Highlight additional elements (e.g., counter values)
        if (stepData.config?.additional_highlights && this.highlighter) {
            const additionalHighlights = stepData.config.additional_highlights;
            if (Array.isArray(additionalHighlights)) {
                additionalHighlights.forEach(selector => {
                    // Don't re-highlight the main target
                    if (selector !== stepData.target_selector) {
                        this.highlighter.highlight(selector, { pulsate: false });
                    }
                });
            }
        }

        // Check for auto-advance flag
        if (stepData.config?.auto_advance_delay) {
            const delay = parseInt(stepData.config.auto_advance_delay);

            setTimeout(() => {
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
        const hideNextButton = Boolean(stepData.config?.requires_validation) && !isManualAdvance;


        if (this.tooltip) {
            this.tooltip.show(title, text, targetSelector, position, hideNextButton);
        } else {
            console.warn('[TutorialUI] Tooltip not initialized; cannot display step text');
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
                    <div class="progress-header">
                        <span class="progress-icon"><i class="ra ra-scroll-unfurled"></i></span>
                        <span class="progress-title">Tutoriel</span>
                    </div>
                    <div class="progress-steps">
                        <span id="tutorial-step-counter" class="step-counter">
                            Étape <span class="step-current">${this.currentStepPosition}</span>
                        </span>
                    </div>
                    <div class="progress-bar-wrapper">
                        <div class="progress-bar-fill" style="width: ${Math.min(initialProgress, 100)}%"></div>
                    </div>
                </div>
                <div id="tutorial-xp-bar">
                    <div class="xp-info">
                        <span class="xp-icon"><i class="ra ra-lightning-bolt"></i></span>
                        <span class="xp-text">XP: <span class="xp-value">${this.xpEarned}</span></span>
                    </div>
                </div>
                <div class="tutorial-controls-buttons">
                    <button id="tutorial-skip" class="btn-tutorial-secondary">
                        <span class="btn-icon"><i class="ra ra-cancel"></i></span>
                        <span class="btn-text">Passer</span>
                    </button>
                </div>
            </div>
        `);
        $('body').append($controls);

        // Bind skip button - use event delegation for reliability
        $(document).off('click', '#tutorial-skip').on('click', '#tutorial-skip', () => {
            this.skip();
        });

    }

    /**
     * Apply interaction mode to step
     */
    applyInteractionMode(stepData) {
        const mode = stepData.interaction_mode || this.getDefaultInteractionMode(stepData.step_type);
        const $overlay = $('#tutorial-overlay');

        // Remove previous mode classes
        $overlay.removeClass('blocking semi-blocking open');

        // Reset inline pointer-events. Semi-blocking sets it to 'none'
        // as an inline style, which then wins over the .blocking CSS
        // rule on the next step transition — so a step that should be
        // blocking would silently let clicks fall through to background
        // menus (e.g. step 28 'Ennemi blessé' after the semi-blocking
        // 'attack_enemy' step).
        $overlay.css('pointer-events', '');

        // Remove ALL event handlers from overlay (from previous modes)
        $overlay.off('click');

        // Remove previous event blocker (from semi-blocking mode)
        if (this.eventBlocker) {
            document.removeEventListener('click', this.eventBlocker, true);
            this.eventBlocker = null;
        }

        // Disconnect observer from previous mode
        if (this.domObserver) {
            this.domObserver.disconnect();
            this.domObserver = null;
        }


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

        const allowedSelectors = stepData.config?.allowed_interactions || [];
        const validationType = stepData.config?.validation_type;

        // Create event blocker function
        this.eventBlocker = (e) => {
            const target = e.target;


            // Always allow clicks on tutorial UI elements (including skip modal)
            if ($(target).closest('#tutorial-controls, .tutorial-tooltip, #tutorial-next, #tutorial-skip-modal, .tutorial-modal-overlay').length > 0 ||
                $(target).is('#tutorial-next')) {
                return; // Allow clicks on tutorial UI
            }

            // Check if click is on an allowed element
            for (const selector of allowedSelectors) {
                // Use jQuery to check if element matches selector (works with SVG)
                const matches = $(target).is(selector);
                const hasParent = $(target).closest(selector).length > 0;


                if (matches || hasParent) {

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

        // Check if this is a navigation link - if so, we need to wait for validation
        const $link = $(targetElement).closest('a[href]');
        const isNavigationLink = $link.length > 0 && $link.attr('href') && !$link.attr('href').startsWith('#');

        if (isNavigationLink) {
            const href = $link.attr('href');

            // Send validation and wait for it before navigating
            this.next({
                element_clicked: selector,
                target_selector: stepData.config?.target_selector
            }, true).then(() => {  // skipUIUpdate=true since we're navigating anyway
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

        // Store selectors for re-application
        this.allowedSelectors = selectors;

        // Function to apply allowed class
        const applyAllowedClasses = () => {
            selectors.forEach(selector => {
                const $elements = $(selector);
                $elements.addClass('tutorial-allowed-element');
            });

            const totalAllowed = $('.tutorial-allowed-element').length;
        };

        // Apply initially
        applyAllowedClasses();

        // Watch for DOM changes and re-apply (for when game updates tiles)
        if (this.domObserver) {
            this.domObserver.disconnect();
        }

        this.domObserver = new MutationObserver(() => {
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
        if (!stepData.config?.requires_validation) {
            return;
        }

        const validationType = stepData.config?.validation_type;

        /* Only setup observers for UI-related validation types */
        const uiValidationTypes = ['ui_panel_opened', 'ui_element_hidden', 'ui_element_visible', 'ui_interaction', 'ui_button_clicked'];
        if (!uiValidationTypes.includes(validationType)) {
            return;
        }

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
        } else if (validationType === 'ui_element_visible') {
            /* Watch for a specific element to become visible */
            const selector = stepData.config?.validation_params?.element;
            if (selector) {
                this.setupElementVisibleObserver(selector);
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


        this.panelObserver = new MutationObserver((mutations) => {
            // Check if panel is now visible
            const isVisible = $(panelSelector).is(':visible');

            if (isVisible && this.isActive) {

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


        /* Note: we deliberately do NOT use "any children in #ajax-data" as a
         * trigger. When a step like close_card_for_tree completes, #ui-card is
         * hidden via display:none but its DOM stays in #ajax-data. A subsequent
         * step with ui_panel_opened on "actions" would see those stale children
         * and auto-validate without any user action (observed bug: observe_tree
         * getting skipped after close_card_for_tree). The tutorial step must
         * actually open a fresh card to validate. */
        const isActionsPanelOpen = () => {
            const hasActions = $('#ajax-data .action, #ajax-data button.action').length > 0;
            const hasCaseInfos = $('#ajax-data .case-infos').length > 0;
            const hasUiCardVisible = $('#ui-card').length > 0 && $('#ui-card').is(':visible');
            return hasActions || hasCaseInfos || hasUiCardVisible;
        };

        this.panelObserver = new MutationObserver(() => {
            if (isActionsPanelOpen() && this.isActive) {
                window.notifyTutorial('ui_interaction', {
                    panel: 'actions',
                    panel_visible: true,
                    timestamp: Date.now()
                });
                if (this.panelObserver) {
                    this.panelObserver.disconnect();
                    this.panelObserver = null;
                }
            }
        });

        this.panelObserver.observe(ajaxData, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['style', 'class']
        });

        /* Also check immediately in case the panel is ALREADY open when this
         * step started (e.g. click_yourself following actions_panel_info,
         * where the actions panel is legitimately pre-open). We require one
         * of the strict signals (actions/case-infos/visible ui-card), not
         * merely "#ajax-data has children". */
        if (isActionsPanelOpen() && this.isActive) {
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


        this.panelObserver = new MutationObserver((mutations) => {
            // Check if element is now hidden
            const element = document.querySelector(elementSelector);
            const isHidden = !element || !$(elementSelector).is(':visible');

            if (isHidden && this.isActive) {

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

        /* Also observe if element is removed from DOM */
        if (targetElement.parentNode) {
            this.panelObserver.observe(targetElement.parentNode, {
                childList: true,
                subtree: true
            });
        }

    }

    /**
     * Setup observer to watch for a specific element to become visible
     * Used for ui_element_visible validation type
     */
    setupElementVisibleObserver(elementSelector) {

        /* Check immediately if element is already visible */
        const existingElement = document.querySelector(elementSelector);
        if (existingElement && $(elementSelector).is(':visible') && this.isActive) {
            window.notifyTutorial('ui_interaction', {
                element: elementSelector,
                is_visible: true,
                timestamp: Date.now()
            });
            return;
        }

        /* Watch for the element to appear and become visible */
        /* Observe the entire document since the element might be added dynamically */
        this.elementVisibleObserver = new MutationObserver((mutations) => {
            const element = document.querySelector(elementSelector);
            const isVisible = element && $(elementSelector).is(':visible');

            if (isVisible && this.isActive) {

                /* Send validation */
                window.notifyTutorial('ui_interaction', {
                    element: elementSelector,
                    is_visible: true,
                    timestamp: Date.now()
                });

                /* Disconnect observer */
                if (this.elementVisibleObserver) {
                    this.elementVisibleObserver.disconnect();
                    this.elementVisibleObserver = null;
                }
            }
        });

        /* Observe the #ui-card area where action panels are loaded */
        const uiCard = document.querySelector('#ui-card');
        if (uiCard) {
            this.elementVisibleObserver.observe(uiCard, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['style', 'class']
            });
        }

        /* Also observe body as fallback */
        this.elementVisibleObserver.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    /**
     * Cleanup all observers
     */
    cleanupObservers() {
        if (this.panelObserver) {
            this.panelObserver.disconnect();
            this.panelObserver = null;
        }
        if (this.elementVisibleObserver) {
            this.elementVisibleObserver.disconnect();
            this.elementVisibleObserver = null;
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


        // Note: Actual resource restoration happens server-side in TutorialContext
        // This is just for client-side display/validation

        if (prereqs.mvt !== undefined) {
            // Refresh characteristics panel if it's open (to show updated movement count)
            this.refreshCaracsPanel();
        }

        if (prereqs.actions !== undefined) {
            // Refresh characteristics panel if it's open
            this.refreshCaracsPanel();
        }

        if (prereqs.ensure_enemy) {
        }

        if (prereqs.ensure_item) {
        }
    }

    /**
     * Ensure required panels are visible/hidden based on step requirements
     */
    ensurePanelVisibility(stepData) {
        // Use target_selector from stepData directly, not from config
        const targetSelector = stepData.target_selector;


        if (!targetSelector) {
            return Promise.resolve(); // Return resolved promise if nothing to do
        }

        // If step targets an action button, ensure correct actions panel is open
        if (targetSelector.includes('.action[data-action=')) {
            const $actionsPanel = $('#ui-card');

            // Check if this is a combat step targeting enemy actions
            const isCombatStep = stepData.step_type === 'combat' ||
                                 stepData.action_name === 'attaquer' ||
                                 stepData.action_name === 'attaque_double';

            if (!$actionsPanel.is(':visible')) {

                // Return a promise that resolves when panel is ready
                return new Promise((resolve) => {
                    // Store resolve callback for later
                    this.panelReadyCallback = resolve;

                    if (isCombatStep) {
                        // Combat step - open enemy panel
                        this.openEnemyCard();
                    } else {
                        // Non-combat action - open player panel

                        // Get player coords from window (set in TutorialView.php)
                        let playerCoords = window.dataCoords;

                        // Fallback: if dataCoords not available yet, wait briefly for it
                        if (!playerCoords) {

                            let coordsRetries = 0;
                            const maxCoordsRetries = 3; // Max 300ms - if not ready quickly, use avatar method

                            const waitForCoords = () => {
                                if (window.dataCoords) {
                                    playerCoords = window.dataCoords;
                                    this.openPlayerCardDirect(playerCoords);
                                } else if (coordsRetries < maxCoordsRetries) {
                                    coordsRetries++;
                                    setTimeout(waitForCoords, 100);
                                } else {
                                    this.openPlayerCardViaAvatar();
                                }
                            };

                            waitForCoords();
                        } else {
                            // Coords available, open card directly
                            this.openPlayerCardDirect(playerCoords);
                        }
                    }
                });
            } else {
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

        $.ajax({
            type: "POST",
            url: 'observe.php',
            data: {'coords': coords},
            success: (data) => {
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
     * Open enemy card for combat steps
     */
    openEnemyCard() {

        // Find the enemy image element (marked with .tutorial-enemy class)
        const $enemyImage = $('.tutorial-enemy, .enemy');

        if ($enemyImage.length > 0) {

            // The enemy image is in SVG layer, not inside .case
            // Get x,y attributes from the image element
            const enemyX = $enemyImage.attr('x');
            const enemyY = $enemyImage.attr('y');


            if (enemyX !== undefined && enemyY !== undefined) {
                // Find the .case tile at this position
                const $tile = $(`.case[x="${enemyX}"][y="${enemyY}"]`);

                if ($tile.length > 0) {
                    const coords = $tile.data('coords');

                    if (coords) {
                        // Open the enemy's observation panel
                        $.ajax({
                            type: "POST",
                            url: 'observe.php',
                            data: {'coords': coords},
                            success: (data) => {
                                $('#ajax-data').html(data);

                                // Cache the result
                                window.clickedCases = window.clickedCases || {};
                                window.clickedCases[coords] = data;

                                // Wait for panel to appear
                                this.waitForActionsPanel();
                            },
                            error: (xhr, status, error) => {
                                console.error('[TutorialUI] Failed to load enemy card:', error);
                                // Fallback: resolve anyway to prevent blocking
                                if (this.panelReadyCallback) {
                                    this.panelReadyCallback();
                                    this.panelReadyCallback = null;
                                }
                            }
                        });
                    } else {
                        console.error('[TutorialUI] Tile at enemy position has no coords!');
                        // Resolve callback to prevent blocking
                        if (this.panelReadyCallback) {
                            this.panelReadyCallback();
                            this.panelReadyCallback = null;
                        }
                    }
                } else {
                    console.error('[TutorialUI] No tile found at enemy position x:', enemyX, 'y:', enemyY);
                    // Resolve callback to prevent blocking
                    if (this.panelReadyCallback) {
                        this.panelReadyCallback();
                        this.panelReadyCallback = null;
                    }
                }
            } else {
                console.error('[TutorialUI] Enemy image has no x,y attributes!');
                // Resolve callback to prevent blocking
                if (this.panelReadyCallback) {
                    this.panelReadyCallback();
                    this.panelReadyCallback = null;
                }
            }
        } else {
            console.error('[TutorialUI] No enemy element found on map!');
            // Resolve callback to prevent blocking
            if (this.panelReadyCallback) {
                this.panelReadyCallback();
                this.panelReadyCallback = null;
            }
        }
    }

    /**
     * Open player card by finding coords from avatar element (fallback method)
     */
    openPlayerCardViaAvatar() {

        const $avatar = $('#current-player-avatar');
        if ($avatar.length > 0) {
            // Avatar has x,y attributes - use these to find the .case element
            const avatarX = $avatar.attr('x');
            const avatarY = $avatar.attr('y');


            // Find the .case element at this position
            const $case = $(`.case[x="${avatarX}"][y="${avatarY}"]`);

            if ($case.length > 0) {
                const coords = $case.data('coords');

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

                // Resolve the promise if callback exists
                if (this.panelReadyCallback) {
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
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: "POST",
                    url: "load_caracs.php",
                    success: function(data) {
                        $("#load-caracs").html(data);
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

        // Check if already watching
        if (this.actionResultObserver) {
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

            if (content && !content.includes('Lancé de dés') && content.trim().length > 10) {

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
    }

    /**
     * Update progress bar
     * Shows tutorial step progression and earned XP
     */
    updateXPBar() {
        // Update XP display
        $('.xp-value').text(this.xpEarned);
    }

    /**
     * Update progress indicator
     */
    updateProgressIndicator() {
        $('.step-current').text(this.currentStepPosition);
        const progress = (this.currentStepPosition / this.totalSteps) * 100;
        $('.progress-bar-fill').css('width', `${Math.min(progress, 100)}%`);
    }

    /**
     * Skip/Cancel tutorial - shows modal with clear reward communication
     */
    async skip() {

        /* Remove any existing modal first */
        $('#tutorial-skip-modal').remove();

        /* Get reward values and replay status from page constants (set by PHP) */
        const skipXP = window.TUTORIAL_SKIP_REWARD_XP || 50;
        const totalXP = window.TUTORIAL_TOTAL_XP || 240;
        const isReplay = window.TUTORIAL_IS_REPLAY || false;

        /* Show modal with different content for replay vs first time */
        const $modal = isReplay ? $(`
            <div id="tutorial-skip-modal" class="tutorial-modal-overlay">
                <div class="tutorial-modal-content">
                    <h2 style="margin-bottom: 10px;">Quitter le tutoriel ?</h2>
                    <p style="margin-bottom: 20px;">Tu rejoues le tutoriel.</p>

                    <div style="text-align: left; margin: 20px 0;">
                        <div style="background: rgba(76, 175, 80, 0.1); padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #4CAF50;">
                            <strong style="color: #4CAF50;">✓ Continuer le tutoriel</strong>
                            <p style="margin: 8px 0 0 0; font-size: 14px; color: #666;">
                                Continue l'entraînement
                            </p>
                        </div>

                        <div style="background: rgba(244, 67, 54, 0.1); padding: 15px; border-radius: 8px; border-left: 4px solid #f44336;">
                            <strong style="color: #f44336;">⊗ Retour au jeu</strong>
                            <p style="margin: 8px 0 0 0; font-size: 14px; color: #666;">
                                Quitte le tutoriel et retourne au jeu
                            </p>
                        </div>
                    </div>

                    <div class="tutorial-modal-buttons">
                        <button id="tutorial-skip-close" class="btn-tutorial-primary">
                            <span class="btn-icon">↩</span>
                            <span class="btn-text">Continuer le tutoriel</span>
                        </button>
                        <button id="tutorial-skip-cancel" class="btn-tutorial-secondary">
                            <span class="btn-icon">⊗</span>
                            <span class="btn-text">Retour au jeu</span>
                        </button>
                    </div>
                </div>
            </div>
        `) : $(`
            <div id="tutorial-skip-modal" class="tutorial-modal-overlay">
                <div class="tutorial-modal-content">
                    <h2 style="margin-bottom: 10px;">Quitter le tutoriel ?</h2>
                    <p style="margin-bottom: 20px;">Tu n'as pas encore terminé le tutoriel.</p>

                    <div style="text-align: left; margin: 20px 0;">
                        <div style="background: rgba(76, 175, 80, 0.1); padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #4CAF50;">
                            <strong style="color: #4CAF50;">✓ Continuer le tutoriel (recommandé)</strong>
                            <p style="margin: 8px 0 0 0; font-size: 14px; color: #666;">
                                Tu peux gagner jusqu'à <strong style="color: #4CAF50;">${totalXP} XP/PI</strong> en complétant toutes les étapes
                            </p>
                        </div>

                        <div style="background: rgba(244, 67, 54, 0.1); padding: 15px; border-radius: 8px; border-left: 4px solid #f44336;">
                            <strong style="color: #f44336;">⊗ Passer le tutoriel</strong>
                            <p style="margin: 8px 0 0 0; font-size: 14px; color: #666;">
                                Tu recevras seulement <strong style="color: #f44336;">${skipXP} XP/PI</strong> au lieu de ${totalXP} XP/PI
                            </p>
                        </div>
                    </div>

                    <div class="tutorial-modal-buttons">
                        <button id="tutorial-skip-close" class="btn-tutorial-primary">
                            <span class="btn-icon">↩</span>
                            <span class="btn-text">Continuer le tutoriel</span>
                        </button>
                        <button id="tutorial-skip-cancel" class="btn-tutorial-secondary">
                            <span class="btn-icon">⊗</span>
                            <span class="btn-text">Passer (${skipXP} XP)</span>
                        </button>
                    </div>
                </div>
            </div>
        `);

        $('body').append($modal);
        $modal.fadeIn(300);

        /* Cancel button - skip without completion, grant skip rewards */
        $('#tutorial-skip-cancel').on('click', async () => {

            /* Confirmation dialog - different message for replay vs first time */
            const confirmMessage = isReplay
                ? `Es-tu sûr de vouloir quitter le tutoriel ?\n\nTu retourneras au jeu normal.`
                : `Es-tu sûr de vouloir passer le tutoriel ?\n\n` +
                  `Tu recevras seulement ${skipXP} XP/PI\n` +
                  `au lieu de ${totalXP} XP/PI du tutoriel complet.`;

            if (!confirm(confirmMessage)) {
                return;
            }

            try {
                const response = await this.apiCall('/api/tutorial/cancel.php', {
                    session_id: this.currentSession
                }, 'POST');

                if (response.success) {
                    $modal.fadeOut(300, () => $modal.remove());
                    this.hideTutorialOverlay();
                    this.currentSession = null;
                    this.isActive = false;
                    sessionStorage.removeItem('tutorial_active');
                    sessionStorage.setItem('tutorial_just_cancelled', 'true');
                    /* Redirect to main game screen instead of reloading current page */
                    window.location.href = 'index.php';
                } else {
                    alert('Erreur lors du passage du tutoriel: ' + (response.error || 'Erreur inconnue'));
                }
            } catch (error) {
                console.error('[TutorialUI] Cancel error', error);

                /* Clear tutorial state even on error to prevent stuck state */
                sessionStorage.removeItem('tutorial_active');
                this.currentSession = null;
                this.isActive = false;

                /* Try to fetch the actual response to debug */
                try {
                    const debugResponse = await fetch('/api/tutorial/cancel.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({session_id: this.currentSession})
                    });
                    const responseText = await debugResponse.text();
                    console.error('[TutorialUI] Raw API response:', responseText);
                } catch (e) {
                    console.error('[TutorialUI] Could not fetch debug response');
                }

                alert(`Erreur: ${error.message || 'Erreur inconnue'}`);
            }
        });

        /* Close button - return to tutorial */
        $('#tutorial-skip-close').on('click', () => {
            $modal.fadeOut(300, () => $modal.remove());
        });
    }

    /**
     * Tutorial complete
     */
    onTutorialComplete(response) {

        // Get redirect delay from step config (in milliseconds, default 5000ms = 5s)
        const redirectDelayMs = response.step_data?.config?.redirect_delay ?? 5000;
        const redirectDelaySec = Math.ceil(redirectDelayMs / 1000);

        // Remove any existing modal first
        $('#tutorial-complete-modal').remove();

        // Show completion celebration
        const $modal = $(`
            <div id="tutorial-complete-modal" class="tutorial-celebration">
                <div class="modal-content celebration-content">
                    <div class="celebration-header">
                        <h2><i class="ra ra-trophy"></i> Tutoriel terminé! <i class="ra ra-trophy"></i></h2>
                        <div class="celebration-stars"><i class="ra ra-crown"></i></div>
                    </div>
                    <p class="celebration-message">${response.message || 'Félicitations ! Vous êtes prêt pour l\'aventure !'}</p>
                    <div class="rewards-display">
                        <div class="reward-item">
                            <span class="reward-icon"><i class="ra ra-lightning-bolt"></i></span>
                            <span class="reward-label">XP gagnés</span>
                            <span class="reward-value">${response.xp_earned || 0}</span>
                        </div>
                        <div class="reward-item">
                            <span class="reward-icon"><i class="ra ra-gem"></i></span>
                            <span class="reward-label">PI gagnés</span>
                            <span class="reward-value">${response.pi_earned || 0}</span>
                        </div>
                    </div>
                    <button id="tutorial-complete-continue" class="btn-tutorial-primary celebration-btn">
                        <i class="ra ra-sword"></i> Commencer l'aventure!
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
        });
    }

    /**
     * Complete tutorial and redirect to main game.
     *
     * Calls /api/tutorial/complete.php so the server-side completion work runs:
     *   - award first-time completion reward (XP/PI)
     *   - remove invisibleMode option
     *   - move player from waiting_room to faction's respawnPlan
     *   - add race actions
     *
     * Without this API call, tutorial_progress.completed gets set by advance.php
     * but the player stays stuck on waiting_room with invisibleMode on, which
     * contradicts the tutorial's "Bonne chance dans Olympia !" finale.
     */
    async completeTutorialAndRedirect() {
        const $modal = $('#tutorial-complete-modal');
        const sessionId = this.currentSession;

        try {
            await this.apiCall('/api/tutorial/complete.php', { session_id: sessionId }, 'POST');
        } catch (error) {
            /* Don't block the redirect on a failure — log + continue so the
             * user isn't trapped in the modal. The hard cypress assertion on
             * leaving waiting_room will surface any regression. */
            console.error('[TutorialUI] complete.php failed, redirecting anyway', error);
        }

        $modal.fadeOut(400, () => {
            $modal.remove();
            this.hideTutorialOverlay();
            sessionStorage.removeItem('tutorial_active');
            sessionStorage.removeItem('tutorial_session_id');
            sessionStorage.setItem('tutorial_just_completed', 'true');
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
