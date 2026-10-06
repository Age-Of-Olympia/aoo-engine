/**
 * TutorialGameIntegration - Bridges game actions with tutorial system
 *
 * This script intercepts game actions (button clicks, movements, etc.)
 * and notifies the tutorial system when they occur.
 */

(function() {
    'use strict';

    // Only initialize if tutorial is active
    function initTutorialGameIntegration() {
        if (!window.tutorialUI) {
            return;
        }


        // ====================================================================
        // INTERCEPT ACTION BUTTON CLICKS
        // ====================================================================

        /* An action counts once action.php has run it, not on the click: the
         * HUD's first click only arms the button, and a refused action
         * ("Action Impossible", no PA left, target gone) must not advance
         * the step. A run action answers with its results block
         * (src/View/ActionResultsView.php: .action-details), a refused one
         * adds .action-blocked; the other refusals are plain text. */
        $(document).ajaxSuccess(function(e, xhr, settings) {
            if (!/(^|\/)action\.php$/.test(settings.url) || typeof settings.data !== 'string') {
                return;
            }
            const response = xhr.responseText || '';
            if (!response.includes('action-details') || response.includes('action-blocked')) {
                return;
            }
            const actionName = new URLSearchParams(settings.data).get('action');
            if (actionName && window.tutorialUI && typeof window.tutorialUI.notifyAction === 'function') {
                window.tutorialUI.notifyAction('action_used', { action_name: actionName });
            }
        });

        // ====================================================================
        // INTERCEPT MOVEMENTS
        // ====================================================================

        // Intercept tile clicks for movement
        $(document).on('click', '.case.go', function(e) {
            const coords = $(this).data('coords');
            if (coords) {

                // Extract x,y from coords string
                const [x, y] = coords.split(',').map(n => parseInt(n));

                // Notify tutorial (after a short delay to let movement process)
                setTimeout(() => {
                    if (window.tutorialUI && typeof window.tutorialUI.notifyAction === 'function') {
                        window.tutorialUI.notifyAction('movement', {
                            to: [x, y],
                            coords: coords
                        });
                    }
                }, 100);
            }
        });

        // ====================================================================
        // INTERCEPT UI INTERACTIONS
        // ====================================================================

        // Intercept characteristics button
        $(document).on('click', '#show-caracs', function(e) {

            setTimeout(() => {
                if (window.tutorialUI && typeof window.tutorialUI.notifyAction === 'function') {
                    window.tutorialUI.notifyAction('ui_interaction', {
                        element_clicked: '#show-caracs',
                        panel: 'characteristics',
                        panel_visible: true
                    });
                }
            }, 100);
        });

        // Intercept inventory button (#show-inventory)
        $(document).on('click', '#show-inventory', function(e) {

            if (window.tutorialUI && typeof window.tutorialUI.notifyAction === 'function') {
                window.tutorialUI.notifyAction('ui_interaction', {
                    element_clicked: 'show-inventory',
                    panel: 'inventory',
                    panel_visible: true
                }, true); // skipUIUpdate = true because page will reload
            }
        });

        // Intercept inventory link
        $(document).on('click', 'a[href="inventory.php"]', function(e) {

            if (window.tutorialUI && typeof window.tutorialUI.notifyAction === 'function') {
                window.tutorialUI.notifyAction('ui_interaction', {
                    element_clicked: 'a[href="inventory.php"]',
                    panel: 'inventory',
                    panel_visible: true
                }, true); // skipUIUpdate = true because page will reload
            }
        });

        // Intercept return to map link (from inventory)
        $(document).on('click', 'a[href="index.php"]', function(e) {

            if (window.tutorialUI && typeof window.tutorialUI.notifyAction === 'function') {
                window.tutorialUI.notifyAction('ui_interaction', {
                    element_clicked: 'a[href="index.php"]'
                }, true); // skipUIUpdate = true because page will reload
            }
        });

    }

    // Initialize when document is ready AND when tutorialUI becomes available
    let initAttempts = 0;
    const maxAttempts = 50; // Try for up to 5 seconds (50 * 100ms)

    function checkAndInit() {
        if (window.tutorialUI) {
            // TutorialUI exists, initialize integration
            initTutorialGameIntegration();
        } else if (initAttempts < maxAttempts) {
            // TutorialUI not ready yet, check again soon
            initAttempts++;
            setTimeout(checkAndInit, 100);
        } else {
            // Give up after max attempts
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkAndInit);
    } else {
        checkAndInit();
    }

    // Also expose for manual initialization
    window.initTutorialGameIntegration = initTutorialGameIntegration;
})();
