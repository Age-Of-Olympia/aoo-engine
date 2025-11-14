/**
 * TutorialInit - Initializes and wires up tutorial system
 *
 * Call window.initTutorial() to start the tutorial system
 */
(function() {
    // Global tutorial instance
    window.tutorialUI = null;
    window.tutorialTooltip = null;
    window.tutorialHighlighter = null;

    /**
     * Initialize tutorial system
     */
    window.initTutorial = function() {
        console.log('[Tutorial] Initializing...');

        // Create components
        window.tutorialUI = new TutorialUI();
        window.tutorialTooltip = new TutorialTooltip();
        window.tutorialHighlighter = new TutorialHighlighter();

        // Wire up components
        window.tutorialUI.tooltip = window.tutorialTooltip;
        window.tutorialUI.highlighter = window.tutorialHighlighter;

        // Initialize
        window.tutorialUI.init();

        // Set up next button handler (delegated event)
        $(document).on('click', '#tutorial-next', function() {
            console.log('[Tutorial] Next button clicked');
            window.tutorialUI.next();
        });

        console.log('[Tutorial] Initialized successfully');
    };

    /**
     * Start new tutorial
     */
    window.startTutorial = function(mode = 'first_time') {
        if (!window.tutorialUI) {
            window.initTutorial();
        }

        return window.tutorialUI.start(mode);
    };

    /**
     * Resume tutorial
     */
    window.resumeTutorial = function() {
        if (!window.tutorialUI) {
            window.initTutorial();
        }

        return window.tutorialUI.resume();
    };

    /**
     * Notify tutorial of game action (for integration with game code)
     *
     * Call this from your game code when player performs an action:
     * - Movement: notifyTutorial('movement', { from: [x1, y1], to: [x2, y2] })
     * - Combat: notifyTutorial('combat', { target_id: enemyId })
     * - Action: notifyTutorial('action', { action_type: 'search' })
     *
     * @param {string} actionType - Type of action performed
     * @param {object} actionData - Action data for validation
     * @param {boolean} skipUIUpdate - If true, save progress but don't show next step (use before page reload)
     */
    window.notifyTutorial = function(actionType, actionData = {}, skipUIUpdate = false) {
        if (window.tutorialUI && window.tutorialUI.currentSession) {
            window.tutorialUI.notifyAction(actionType, actionData, skipUIUpdate);
        }
    };

    /**
     * Auto-check for tutorial on page load
     */
    $(document).ready(function() {
        // Check URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const tutorialParam = urlParams.get('tutorial');

        if (tutorialParam === 'start') {
            console.log('[Tutorial] Auto-starting from URL parameter');
            setTimeout(() => {
                window.startTutorial('first_time');
            }, 500);
        } else if (tutorialParam === 'resume') {
            console.log('[Tutorial] Auto-resuming from URL parameter');
            setTimeout(() => {
                window.resumeTutorial();
            }, 500);
        } else {
            // Check for active tutorial session
            checkForActiveTutorial();
        }
    });

    /**
     * Check for active tutorial session
     */
    async function checkForActiveTutorial() {
        // Don't check if we just cancelled (prevents loop)
        if (sessionStorage.getItem('tutorial_just_cancelled') === 'true') {
            console.log('[Tutorial] Skipping check after cancel');
            sessionStorage.removeItem('tutorial_just_cancelled');
            return;
        }

        // Auto-resume if we just started (after reload)
        if (sessionStorage.getItem('tutorial_just_started') === 'true') {
            console.log('[Tutorial] Auto-resuming after start...');
            sessionStorage.removeItem('tutorial_just_started');
            setTimeout(() => {
                window.resumeTutorial();
            }, 500);
            return;
        }

        // Auto-resume if tutorial is actively running (after page reload during tutorial)
        if (sessionStorage.getItem('tutorial_active') === 'true') {
            console.log('[Tutorial] Auto-resuming active tutorial...');
            setTimeout(() => {
                window.resumeTutorial();
            }, 500);
            return;
        }

        try {
            const response = await fetch('/api/tutorial/resume.php');

            if (!response.ok) {
                console.log('[Tutorial] Resume API returned', response.status);
                return;
            }

            const data = await response.json();
            console.log('[Tutorial] Resume check:', data);

            if (data.success && data.has_active_tutorial) {
                // Show modal to resume (don't auto-resume to avoid loop)
                console.log('[Tutorial] Found active tutorial, showing resume modal');
                showResumeTutorialModal();
            }
        } catch (error) {
            console.log('[Tutorial] Resume check error:', error.message);
        }
    }

    /**
     * Show modal asking to resume tutorial
     */
    function showResumeTutorialModal() {
        // Check if modal already exists
        let modal = document.getElementById('tutorial-resume-modal');

        if (!modal) {
            // Create modal
            modal = document.createElement('div');
            modal.id = 'tutorial-resume-modal';
            modal.className = 'modal-bg';
            modal.innerHTML = `
                <div class="modal">
                    <div class="modal-content">
                        <h3>Tutoriel en cours</h3>
                        <p>Vous avez un tutoriel en cours. Que voulez-vous faire?</p>
                        <div style="margin-top: 20px; display: flex; gap: 10px; flex-direction: column;">
                            <button id="tutorial-resume-yes" class="btn-tutorial-primary">
                                ▶️ Continuer le tutoriel
                            </button>
                            <button id="tutorial-resume-later" class="btn-tutorial-secondary">
                                ⏸️ Plus tard
                            </button>
                            <button id="tutorial-resume-cancel" style="background: #f44336; color: white; padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer;">
                                ❌ Annuler le tutoriel
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            // Bind buttons
            document.getElementById('tutorial-resume-yes').addEventListener('click', () => {
                hideModal(modal);
                window.resumeTutorial();
            });

            document.getElementById('tutorial-resume-later').addEventListener('click', () => {
                hideModal(modal);
                // Clear active flag so we don't auto-resume next time
                sessionStorage.removeItem('tutorial_active');
            });

            document.getElementById('tutorial-resume-cancel').addEventListener('click', async () => {
                if (confirm('Êtes-vous sûr de vouloir annuler le tutoriel? Votre progression sera perdue.')) {
                    try {
                        const response = await fetch('/api/tutorial/cancel.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({})
                        });
                        const data = await response.json();

                        if (data.success) {
                            hideModal(modal);
                            // Set flag to prevent auto-check after reload
                            sessionStorage.setItem('tutorial_just_cancelled', 'true');
                            window.location.reload();
                        } else {
                            alert('Erreur lors de l\'annulation du tutoriel');
                        }
                    } catch (error) {
                        console.error('Cancel error:', error);
                        alert('Erreur lors de l\'annulation du tutoriel');
                    }
                }
            });

            // Click outside to close
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    hideModal(modal);
                }
            });
        }

        // Show modal (using global showModal if available, otherwise set display)
        if (typeof showModal === 'function') {
            showModal(modal);
        } else {
            modal.style.display = 'flex';
        }
    }

    /**
     * Hide modal helper
     */
    function hideModal(modal) {
        if (typeof window.hideModal === 'function') {
            window.hideModal(modal);
        } else {
            modal.style.display = 'none';
        }
    }

    console.log('[Tutorial] Init script loaded');
})();
