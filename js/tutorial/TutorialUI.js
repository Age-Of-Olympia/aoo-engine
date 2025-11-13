/**
 * TutorialUI - Main tutorial UI controller
 *
 * Manages the complete tutorial user interface including:
 * - API communication
 * - Step rendering
 * - XP bar display
 * - Navigation controls
 */
class TutorialUI {
    constructor() {
        this.currentSession = null;
        this.currentStep = 0;
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
                this.currentStep = response.current_step;
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

                // Render first step
                if (response.step_data) {
                    this.renderStep(response.step_data);
                }

                // Show tutorial overlay
                this.showTutorialOverlay();

                // Mark tutorial as actively running
                sessionStorage.setItem('tutorial_active', 'true');

                return true;
            } else {
                console.error('[TutorialUI] Failed to start tutorial', response);
                alert('Impossible de démarrer le tutoriel: ' + (response.error || 'Erreur inconnue'));
                return false;
            }
        } catch (error) {
            console.error('[TutorialUI] Start error', error);
            alert('Erreur lors du démarrage du tutoriel');
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

            if (response.success && response.has_active_tutorial) {
                this.currentSession = response.session_id;
                this.currentStep = response.current_step;
                this.totalSteps = response.total_steps;
                this.xpEarned = response.xp_earned;
                this.isActive = true;

                console.log('[TutorialUI] Resuming tutorial', response);

                // Render current step
                if (response.step_data) {
                    this.renderStep(response.step_data);
                }

                // Show tutorial overlay
                this.showTutorialOverlay();

                // Mark tutorial as actively running (for auto-resume after page reload)
                sessionStorage.setItem('tutorial_active', 'true');

                return true;
            } else {
                console.log('[TutorialUI] No active tutorial to resume');
                return false;
            }
        } catch (error) {
            console.error('[TutorialUI] Resume error', error);
            return false;
        }
    }

    /**
     * Notify tutorial of game action (called by game code)
     *
     * Example: window.tutorialUI.notifyAction('movement', { from: [x1,y1], to: [x2,y2] })
     */
    notifyAction(actionType, actionData = {}) {
        console.log('[TutorialUI] Action notification:', actionType, actionData);

        // Auto-advance with validation data
        this.next(actionData);
    }

    /**
     * Advance to next step
     */
    async next(validationData = {}) {
        try {
            console.log('[TutorialUI] Advancing to next step...', { validationData });

            const response = await this.apiCall('/api/tutorial/advance.php', {
                session_id: this.currentSession,
                validation_data: validationData
            });

            if (response.success) {
                if (response.completed) {
                    // Tutorial complete!
                    this.onTutorialComplete(response);
                } else {
                    // Update state
                    this.currentStep = response.current_step;
                    this.xpEarned = response.xp_earned;
                    this.level = response.level;
                    this.pi = response.pi;

                    // Update XP bar
                    this.updateXPBar();

                    // Render next step
                    if (response.step_data) {
                        this.renderStep(response.step_data);
                    }
                }

                return true;
            } else {
                // Validation failed
                console.warn('[TutorialUI] Validation failed', response);
                this.showValidationError(response.error, response.hint);
                return false;
            }
        } catch (error) {
            console.error('[TutorialUI] Advance error', error);
            alert('Erreur lors de l\'avancement du tutoriel');
            return false;
        }
    }

    /**
     * Render tutorial step
     */
    renderStep(stepData) {
        console.log('[TutorialUI] Rendering step', stepData);

        // Clear previous highlights
        if (this.highlighter) {
            this.highlighter.clearAll();
        }

        // Show step tooltip
        this.showStepTooltip(stepData);

        // Highlight target element if specified
        if (stepData.target_selector && this.highlighter) {
            this.highlighter.highlight(stepData.target_selector, {
                pulsate: stepData.requires_validation
            });
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

        if (this.tooltip) {
            this.tooltip.show(title, text, targetSelector, position);
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
        const $controls = $(`
            <div id="tutorial-controls">
                <div id="tutorial-progress">
                    <span id="tutorial-step-counter">Étape ${this.currentStep + 1} / ${this.totalSteps}</span>
                </div>
                <div id="tutorial-xp-bar">
                    <div class="xp-bar-container">
                        <div class="xp-bar-fill" style="width: 0%"></div>
                    </div>
                    <div class="xp-text">Niveau ${this.level} - XP: ${this.xpEarned}</div>
                </div>
                <button id="tutorial-skip" class="btn-tutorial-secondary">Passer le tutoriel</button>
            </div>
        `);
        $('body').append($controls);

        // Bind skip button
        $('#tutorial-skip').on('click', () => this.skip());

        console.log('[TutorialUI] Overlay shown');
    }

    /**
     * Hide tutorial overlay
     */
    hideTutorialOverlay() {
        $('#tutorial-overlay').fadeOut(() => $('#tutorial-overlay').remove());
        $('#tutorial-controls').fadeOut(() => $('#tutorial-controls').remove());

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
     * Update XP bar
     */
    updateXPBar() {
        // Calculate XP progress (simple: every 100 XP = 1 level)
        const xpForNextLevel = 100 * (this.level + 1);
        const xpProgress = (this.xpEarned / xpForNextLevel) * 100;

        $('#tutorial-xp-bar .xp-bar-fill').css('width', `${Math.min(xpProgress, 100)}%`);
        $('#tutorial-xp-bar .xp-text').text(`Niveau ${this.level} - XP: ${this.xpEarned}`);
    }

    /**
     * Update progress indicator
     */
    updateProgressIndicator() {
        $('#tutorial-step-counter').text(`Étape ${this.currentStep + 1} / ${this.totalSteps}`);
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
                alert('Erreur lors de l\'annulation du tutoriel');
            }
        }
    }

    /**
     * Tutorial complete
     */
    onTutorialComplete(response) {
        console.log('[TutorialUI] Tutorial complete!', response);

        // Show completion message
        const $modal = $(`
            <div id="tutorial-complete-modal">
                <div class="modal-content">
                    <h2>🎉 Tutoriel terminé!</h2>
                    <p>${response.message || 'Félicitations!'}</p>
                    <p><strong>XP gagnés:</strong> ${response.xp_earned || 0}</p>
                    <p><strong>Niveau final:</strong> ${response.final_level || 1}</p>
                    <button id="tutorial-complete-continue" class="btn-tutorial-primary">
                        Continuer vers le jeu
                    </button>
                </div>
            </div>
        `);

        $('body').append($modal);
        $modal.fadeIn();

        $('#tutorial-complete-continue').on('click', () => {
            $modal.fadeOut(() => $modal.remove());
            this.hideTutorialOverlay();
            // Clear active flag since tutorial is complete
            sessionStorage.removeItem('tutorial_active');
            window.location.reload();
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
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return await response.json();
    }
}

// Export for global use
window.TutorialUI = TutorialUI;
