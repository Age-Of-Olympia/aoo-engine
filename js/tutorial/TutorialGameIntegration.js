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
            console.log('[TutorialGameIntegration] Tutorial UI not available, skipping integration');
            return;
        }

        console.log('[TutorialGameIntegration] Initializing game integration...');

        // ====================================================================
        // INTERCEPT ACTION BUTTON CLICKS
        // ====================================================================

        // Use event delegation to catch all action button clicks
        // This works for dynamically loaded action buttons in #ajax-data
        $(document).on('click', 'button.action, .action[data-action]', function(e) {
            const $button = $(this);
            const actionName = $button.data('action') || $button.attr('data-action');

            console.log('[TutorialGameIntegration] Button clicked - Element:', this);
            console.log('[TutorialGameIntegration] Button classes:', this.className);
            console.log('[TutorialGameIntegration] data-action attr:', $button.attr('data-action'));
            console.log('[TutorialGameIntegration] .data("action"):', $button.data('action'));
            console.log('[TutorialGameIntegration] Final actionName:', actionName);

            if (actionName) {
                console.log('[TutorialGameIntegration] Action button clicked:', actionName);

                // Notify tutorial system
                if (window.tutorialUI && typeof window.tutorialUI.notifyAction === 'function') {
                    console.log('[TutorialGameIntegration] Calling notifyAction with:', {
                        action_name: actionName,
                        button: $button.text().trim()
                    });
                    window.tutorialUI.notifyAction('action_used', {
                        action_name: actionName,
                        button: $button.text().trim()
                    });
                } else {
                    console.warn('[TutorialGameIntegration] tutorialUI.notifyAction not available!');
                }
            } else {
                console.warn('[TutorialGameIntegration] No actionName found for button');
            }
        });

        // ====================================================================
        // INTERCEPT MOVEMENTS
        // ====================================================================

        // Intercept tile clicks for movement
        $(document).on('click', '.case.go', function(e) {
            const coords = $(this).data('coords');
            if (coords) {
                console.log('[TutorialGameIntegration] Movement tile clicked:', coords);

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
            console.log('[TutorialGameIntegration] Characteristics button clicked');

            setTimeout(() => {
                if (window.tutorialUI && typeof window.tutorialUI.notifyAction === 'function') {
                    window.tutorialUI.notifyAction('ui_interaction', {
                        element_clicked: '#show-caracs',
                        panel: 'characteristics'
                    });
                }
            }, 100);
        });

        // Intercept inventory button (#show-inventory)
        $(document).on('click', '#show-inventory', function(e) {
            console.log('[TutorialGameIntegration] Inventory button (#show-inventory) clicked');

            if (window.tutorialUI && typeof window.tutorialUI.notifyAction === 'function') {
                window.tutorialUI.notifyAction('ui_interaction', {
                    element_clicked: 'show-inventory',
                    panel: 'inventory'
                }, true); // skipUIUpdate = true because page will reload
            }
        });

        // Intercept inventory link
        $(document).on('click', 'a[href="inventory.php"]', function(e) {
            console.log('[TutorialGameIntegration] Inventory link clicked');

            if (window.tutorialUI && typeof window.tutorialUI.notifyAction === 'function') {
                window.tutorialUI.notifyAction('ui_interaction', {
                    element_clicked: 'a[href="inventory.php"]',
                    panel: 'inventory'
                }, true); // skipUIUpdate = true because page will reload
            }
        });

        // Intercept return to map link (from inventory)
        $(document).on('click', 'a[href="index.php"]', function(e) {
            console.log('[TutorialGameIntegration] Return to map clicked');

            if (window.tutorialUI && typeof window.tutorialUI.notifyAction === 'function') {
                window.tutorialUI.notifyAction('ui_interaction', {
                    element_clicked: 'a[href="index.php"]'
                }, true); // skipUIUpdate = true because page will reload
            }
        });

        console.log('[TutorialGameIntegration] Integration complete');
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
            console.log('[TutorialGameIntegration] TutorialUI not available after 5 seconds, skipping integration');
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
