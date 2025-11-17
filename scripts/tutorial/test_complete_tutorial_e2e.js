/**
 * Complete End-to-End Tutorial Test
 * Tests entire tutorial flow, validates highlights, encoding, and coherence
 */
const puppeteer = require('puppeteer');

class TutorialE2ETest {
    constructor(options = {}) {
        this.browser = null;
        this.page = null;
        this.issues = [];
        this.currentStep = null;
        this.screenshotsEnabled = options.screenshots !== false; // Default true

        // Create timestamped directory in data_tests
        const timestamp = Date.now();
        this.screenshotDir = options.screenshotDir || `/var/www/html/data_tests/tutorial_validation_${timestamp}`;
        this.stepCounter = 0;
        this.lastActionClicked = null; // Track last action to avoid loops
    }

    async initialize() {
        console.log('🚀 Starting complete E2E tutorial test...\n');

        // Create screenshot directory if enabled
        if (this.screenshotsEnabled) {
            const fs = require('fs');
            if (!fs.existsSync(this.screenshotDir)) {
                fs.mkdirSync(this.screenshotDir, { recursive: true });
            }
            console.log(`📸 Screenshots will be saved to: ${this.screenshotDir}\n`);
        }

        this.browser = await puppeteer.launch({
            headless: true,
            args: ['--no-sandbox', '--disable-setuid-sandbox']
        });

        this.page = await this.browser.newPage();
        await this.page.setViewport({ width: 1920, height: 1080 });

        // Capture console messages
        this.page.on('console', msg => {
            const text = msg.text();
            // Log tutorial-related messages
            if (text.includes('[Menu]') || text.includes('[Tutorial') || text.includes('Tutorial')) {
                console.log(`   🖥️  ${text}`);
            }
            // Capture errors
            if (msg.type() === 'error') {
                this.issues.push({
                    type: 'console_error',
                    message: text
                });
            }
        });

        // Capture page errors
        this.page.on('pageerror', error => {
            this.issues.push({
                type: 'page_error',
                message: error.message
            });
        });
    }

    async login() {
        console.log('🔐 Logging in as player 7...');

        await this.page.goto('http://localhost/index.php', { waitUntil: 'networkidle2' });

        await this.page.evaluate(() => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/login.php';

            const fields = {
                'name': '7',
                'psw': 'D0Oy7GF6ixBEo#>1RE{rG%9/5rk\\d*wk]**z`$pI',
                'footprint': 'puppeteer-test'
            };

            Object.entries(fields).forEach(([name, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        });

        await this.page.waitForNavigation({ waitUntil: 'networkidle2' });

        const content = await this.page.content();
        if (content.includes('Mauvais mot de passe')) {
            throw new Error('Login failed');
        }

        await this.page.goto('http://localhost/index.php', { waitUntil: 'networkidle2' });
        await this.sleep(2000);

        console.log('✓ Logged in\n');
    }

    async cancelExistingTutorial() {
        console.log('   Canceling any existing tutorial sessions...');

        // Wait a bit for any modals to appear (if there's a pending tutorial)
        await this.sleep(1500);

        // Check if modal exists
        const hasModal = await this.page.evaluate(() => {
            return !!document.getElementById('tutorial-resume-modal');
        });

        if (hasModal) {
            console.log('   📋 Resume modal detected, clicking cancel button...');

            // Override confirm dialog and click cancel button
            // This will trigger a reload, so we need to wait for navigation
            try {
                await Promise.all([
                    this.page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 10000 }),
                    this.page.evaluate(() => {
                        window.confirm = () => true; // Auto-accept confirmation
                        const cancelBtn = document.getElementById('tutorial-resume-cancel');
                        if (cancelBtn) {
                            cancelBtn.click();
                        }
                    })
                ]);
                console.log('   ✓ Tutorial canceled via modal button (page reloaded)');
            } catch (error) {
                console.log(`   ⚠️  Modal cancel failed: ${error.message}`);
            }
        } else {
            // No modal - call API directly
            const result = await this.page.evaluate(async () => {
                try {
                    const response = await fetch('/api/tutorial/cancel.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({})
                    });
                    const data = await response.json();

                    // Clean up any remaining modals
                    const modals = document.querySelectorAll('.modal-bg, .modal');
                    modals.forEach(modal => modal.remove());

                    // Clear session storage flags
                    sessionStorage.removeItem('tutorial_active');
                    sessionStorage.removeItem('tutorial_just_started');
                    sessionStorage.setItem('tutorial_just_cancelled', 'true');

                    return data;
                } catch (e) {
                    return { success: false, error: e.message };
                }
            });

            if (result.success) {
                console.log('   ✓ Tutorial canceled via API (no modal)');
            } else {
                console.log('   ℹ️  No active tutorial to cancel');
            }
        }

        await this.sleep(1000);
    }

    async startTutorial() {
        console.log('🎓 Starting tutorial...');

        const hasButton = await this.page.$('#tutorial-start-btn') !== null;
        if (!hasButton) {
            throw new Error('Tutorial button not found!');
        }

        // Cancel any existing tutorial first
        await this.cancelExistingTutorial();

        // Reload page to get fresh state
        console.log('   Reloading page after cancel...');
        await this.page.reload({ waitUntil: 'networkidle2' });
        await this.sleep(2000);

        // Start tutorial programmatically (more reliable than clicking button in headless mode)
        console.log('   Starting tutorial programmatically...');

        // Use Promise.all to handle both the start call and the navigation
        await Promise.all([
            this.page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 15000 }),
            this.page.evaluate(async () => {
                // Ensure tutorial system is initialized
                if (typeof window.initTutorial === 'function' && !window.tutorialUI) {
                    window.initTutorial();
                }

                // Start the tutorial
                if (typeof window.startTutorial === 'function') {
                    return await window.startTutorial('first_time');
                }

                return { error: 'startTutorial function not found' };
            })
        ]).catch(err => {
            console.log('   ⚠️  Navigation timeout (this may be normal if no reload needed):', err.message);
        });

        // Wait for tutorial to initialize after reload
        console.log('   Waiting for tutorial to initialize...');
        await this.sleep(3000);

        // Wait for tutorial overlay to appear
        try {
            await this.page.waitForSelector('#tutorial-overlay', { timeout: 10000 });
            console.log('   ✓ Tutorial overlay found');
        } catch (e) {
            console.log('   ⚠️  Timeout waiting for tutorial overlay');

            // Debug: check what's on the page
            const debugInfo = await this.page.evaluate(() => {
                return {
                    url: window.location.href,
                    hasOverlay: !!document.querySelector('.tutorial-overlay'),
                    hasTutorialUI: !!window.tutorialUI,
                    bodyClasses: document.body.className,
                    hasInitFunction: typeof window.initTutorial === 'function',
                    hasStartFunction: typeof window.startTutorial === 'function',
                    modals: Array.from(document.querySelectorAll('.modal')).map(m => m.className),
                    scripts: Array.from(document.querySelectorAll('script[src]')).map(s => s.src).slice(0, 10)
                };
            });
            console.log('   Debug info:', JSON.stringify(debugInfo, null, 2));
        }

        await this.sleep(2000); // Extra time for JS to initialize

        // Verify tutorial is active
        const isActive = await this.page.evaluate(() => {
            return {
                hasOverlay: !!document.querySelector('#tutorial-overlay'),
                hasUI: !!window.tutorialUI,
                currentStep: window.tutorialUI?.currentStep
            };
        });

        console.log(`   Tutorial active: ${isActive.hasOverlay}`);
        console.log(`   UI initialized: ${isActive.hasUI}`);
        console.log(`   Current step: ${isActive.currentStep || 'none'}`);

        console.log('✓ Tutorial started\n');
    }

    async getCurrentStepInfo() {
        return await this.page.evaluate(() => {
            if (!window.tutorialUI) {
                return { error: 'tutorialUI not found' };
            }

            const stepData = window.tutorialUI.stepData || {};

            return {
                stepId: window.tutorialUI.currentStep,
                targetSelector: stepData.config?.target_selector,
                text: stepData.config?.text,
                validationType: stepData.config?.validation_type,
                interactionMode: stepData.config?.interaction_mode,
                requiresValidation: stepData.requires_validation
            };
        });
    }

    async analyzeStep() {
        const stepInfo = await this.getCurrentStepInfo();

        if (stepInfo.error) {
            this.issues.push({
                type: 'step_error',
                step: 'unknown',
                message: stepInfo.error
            });
            return null;
        }

        this.currentStep = stepInfo.stepId;
        this.stepCounter++;

        console.log(`\n📊 STEP ${this.stepCounter}: ${stepInfo.stepId}`);
        console.log(`   Target: ${stepInfo.targetSelector || 'none'}`);
        console.log(`   Mode: ${stepInfo.interactionMode}`);

        // Check if we need the map but we're on a different page (like inventory)
        const needsMap = stepInfo.targetSelector && stepInfo.targetSelector.includes('.case');
        if (needsMap) {
            const currentPage = await this.page.evaluate(() => window.location.pathname);
            if (currentPage.includes('inventory.php') || currentPage.includes('craft.php') || currentPage.includes('bank.php')) {
                console.log(`   🔄 Need map but on ${currentPage}, navigating back to index...`);
                await this.page.goto('http://localhost/index.php', { waitUntil: 'networkidle2' });
                await this.sleep(2000);
            }
        }

        // Take screenshot if enabled
        if (this.screenshotsEnabled) {
            await this.takeScreenshot(stepInfo.stepId);
        }

        // Get detailed DOM analysis
        let analysis;
        try {
            analysis = await this.page.evaluate((targetSelector) => {
            const results = {
                targetElements: [],
                highlights: [],
                tooltip: null,
                playerPosition: null,
                npcs: [],
                resources: [],
                encoding: []
            };

            // Find target elements
            if (targetSelector) {
                const targets = document.querySelectorAll(targetSelector);
                targets.forEach((el, i) => {
                    const rect = el.getBoundingClientRect();
                    const coords = el.getAttribute('data-coords');
                    results.targetElements.push({
                        index: i,
                        coords: coords,
                        position: {
                            top: Math.round(rect.top),
                            left: Math.round(rect.left),
                            width: Math.round(rect.width),
                            height: Math.round(rect.height)
                        }
                    });
                });
            }

            // Find highlights
            document.querySelectorAll('.tutorial-highlight').forEach((el, i) => {
                const rect = el.getBoundingClientRect();
                results.highlights.push({
                    index: i,
                    position: {
                        top: Math.round(rect.top),
                        left: Math.round(rect.left),
                        width: Math.round(rect.width),
                        height: Math.round(rect.height)
                    }
                });
            });

            // Find tooltip
            const tooltip = document.querySelector('.tutorial-tooltip');
            if (tooltip) {
                const rect = tooltip.getBoundingClientRect();
                results.tooltip = {
                    text: tooltip.textContent,
                    position: {
                        top: Math.round(rect.top),
                        left: Math.round(rect.left)
                    }
                };

                // Check for encoding issues (common French characters)
                const text = tooltip.textContent;
                if (text.includes('Ã©') || text.includes('Ã¨') || text.includes('Ã ') ||
                    text.includes('rÃ©') || text.includes('GaÃ¯')) {
                    results.encoding.push({
                        location: 'tooltip',
                        text: text.substring(0, 100),
                        issue: 'Double-encoded UTF-8'
                    });
                }
            }

            // Find player position
            const playerAvatar = document.querySelector('.player-avatar, [data-player-id]');
            if (playerAvatar) {
                const parent = playerAvatar.closest('.case');
                if (parent) {
                    results.playerPosition = parent.getAttribute('data-coords');
                }
            }

            // Find NPCs
            document.querySelectorAll('.case').forEach(el => {
                const coords = el.getAttribute('data-coords');
                const npcName = el.querySelector('[data-npc-name]');
                if (npcName) {
                    results.npcs.push({
                        name: npcName.textContent,
                        coords: coords
                    });
                }
            });

            return results;
        }, stepInfo.targetSelector);
        } catch (error) {
            console.log(`   ⚠️  DOM analysis failed: ${error.message}`);
            // Return empty analysis
            analysis = {
                targetElements: [],
                highlights: [],
                tooltip: null,
                playerPosition: null,
                npcs: [],
                resources: [],
                encoding: []
            };
        }

        // Validate consistency
        this.validateStepConsistency(stepInfo, analysis);

        return { stepInfo, analysis };
    }

    validateStepConsistency(stepInfo, analysis) {
        const stepId = stepInfo.stepId;

        // Check 1: Target elements exist
        if (stepInfo.targetSelector && analysis.targetElements.length === 0) {
            this.issues.push({
                type: 'missing_target',
                step: stepId,
                message: `Target selector "${stepInfo.targetSelector}" found 0 elements`
            });
        }

        // Check 2: Multiple targets (ambiguous)
        if (analysis.targetElements.length > 1) {
            this.issues.push({
                type: 'multiple_targets',
                step: stepId,
                message: `Selector matches ${analysis.targetElements.length} elements`,
                details: analysis.targetElements.map(t => t.coords)
            });
        }

        // Check 3: Highlight matches target
        if (analysis.highlights.length > 0 && analysis.targetElements.length > 0) {
            const highlight = analysis.highlights[0];
            const target = analysis.targetElements[0];

            const distance = Math.abs(highlight.position.left - target.position.left) +
                           Math.abs(highlight.position.top - target.position.top);

            if (distance > 20) {
                this.issues.push({
                    type: 'highlight_mismatch',
                    step: stepId,
                    message: `Highlight is ${distance}px away from target`,
                    highlight: highlight.position,
                    target: target.position,
                    targetCoords: target.coords
                });
                console.log(`   ⚠️  Highlight mismatch: ${distance}px from target ${target.coords}`);
            } else {
                console.log(`   ✓ Highlight matches target ${target.coords}`);
            }
        }

        // Check 4: Encoding issues
        if (analysis.encoding.length > 0) {
            analysis.encoding.forEach(enc => {
                this.issues.push({
                    type: 'encoding_issue',
                    step: stepId,
                    location: enc.location,
                    text: enc.text
                });
                console.log(`   ⚠️  Encoding issue in ${enc.location}: ${enc.text}`);
            });
        }

        // Check 5: Step-specific validations
        this.validateStepSpecific(stepId, stepInfo, analysis);
    }

    validateStepSpecific(stepId, stepInfo, analysis) {
        switch(stepId) {
            case 'your_position':
                // Should highlight player position
                if (analysis.playerPosition) {
                    const target = analysis.targetElements[0];
                    if (target && target.coords !== analysis.playerPosition) {
                        this.issues.push({
                            type: 'coordinate_mismatch',
                            step: stepId,
                            message: `Highlighting ${target.coords} but player at ${analysis.playerPosition}`
                        });
                        console.log(`   ⚠️  Player at ${analysis.playerPosition} but highlighting ${target.coords}`);
                    }
                }
                break;

            case 'move_to_resource':
            case 'inspect_resource':
                // Should highlight tree position
                // Tree should be at (0,1) based on database
                const target = analysis.targetElements[0];
                if (target && target.coords !== '0,1') {
                    this.issues.push({
                        type: 'resource_position_mismatch',
                        step: stepId,
                        message: `Tree step highlighting ${target.coords} but tree should be at 0,1`
                    });
                    console.log(`   ⚠️  Highlighting ${target.coords} but tree should be at 0,1`);
                }
                break;

            case 'gaia_welcome':
                // Gaïa should be at (1,0)
                const gaiaTarget = analysis.targetElements[0];
                if (gaiaTarget && gaiaTarget.coords !== '1,0') {
                    this.issues.push({
                        type: 'npc_position_mismatch',
                        step: stepId,
                        message: `Gaïa step highlighting ${gaiaTarget.coords} but Gaïa at 1,0`
                    });
                }
                break;

            case 'move_to_enemy':
            case 'inspect_enemy':
                // Enemy should be at (0,-1)
                const enemyTarget = analysis.targetElements[0];
                if (enemyTarget && enemyTarget.coords !== '0,-1') {
                    this.issues.push({
                        type: 'enemy_position_mismatch',
                        step: stepId,
                        message: `Enemy step highlighting ${enemyTarget.coords} but enemy at 0,-1`
                    });
                }
                break;
        }
    }

    async performRequiredAction() {
        // Check what action is required and perform it
        const stepInfo = await this.page.evaluate(() => {
            if (!window.tutorialUI || !window.tutorialUI.stepData) {
                return null;
            }

            const stepData = window.tutorialUI.stepData;
            return {
                stepId: stepData.step_id,
                validationType: stepData.config?.validation_type,
                validationParams: stepData.config?.validation_params,
                requiresValidation: stepData.requires_validation,
                targetSelector: stepData.config?.target_selector
            };
        });

        if (!stepInfo) {
            return false;
        }

        console.log(`   🎯 Performing action for: ${stepInfo.validationType}`);

        switch (stepInfo.validationType) {
            case 'position':
            case 'specific_count':
            case 'movements_depleted':
            case 'any_movement':
                // For movement steps, we need to click on actual movement tiles, not UI elements
                const movementSelector = stepInfo.targetSelector;

                // Check if target is a movement tile or UI element
                const isMovementTile = movementSelector &&
                    (movementSelector.includes('.case') || movementSelector.includes('data-coords'));

                if (isMovementTile) {
                    // Click on the specified tile TWICE (first click shows arrow, second click moves)
                    try {
                        await this.page.click(movementSelector);
                        console.log(`   👉 First click on ${movementSelector} (showing arrow)`);
                        await this.sleep(500);

                        await this.page.click(movementSelector);
                        console.log(`   ✓ Second click - moved to ${movementSelector}`);
                        await this.sleep(2500); // Wait for movement animation
                        return true;
                    } catch (error) {
                        console.log(`   ⚠️  Failed to click ${movementSelector}: ${error.message}`);
                        return false;
                    }
                } else {
                    // This is a "deplete movements" step - click on any available movement tile
                    console.log(`   🎲 No specific tile - clicking any available movement tile`);
                    try {
                        // Get a random movement tile's data-coords
                        const tileCoords = await this.page.evaluate(() => {
                            const movementTiles = Array.from(document.querySelectorAll('.case.go'));
                            if (movementTiles.length > 0) {
                                const randomIndex = Math.floor(Math.random() * movementTiles.length);
                                const tile = movementTiles[randomIndex];
                                return tile.getAttribute('data-coords');
                            }
                            return null;
                        });

                        if (tileCoords) {
                            const selector = `.case[data-coords="${tileCoords}"]`;

                            // FIRST CLICK: Show arrow
                            await this.page.click(selector);
                            console.log(`   👉 First click on ${tileCoords} (showing arrow)`);
                            await this.sleep(500);

                            // SECOND CLICK: Actually move
                            await this.page.click(selector);
                            console.log(`   ✓ Second click - moved to ${tileCoords}`);
                            await this.sleep(2500); // Wait for movement
                            return true;
                        } else {
                            console.log(`   ⚠️  No movement tiles available`);
                            return false;
                        }
                    } catch (error) {
                        console.log(`   ⚠️  Movement failed: ${error.message}`);
                        return false;
                    }
                }
                break;

            case 'adjacent_to_position':
                // Move adjacent to a target (e.g., next to a tree) - may require multiple moves
                console.log(`   🌳 Moving adjacent to target (pathfinding)`);
                try {
                    const targetX = stepInfo.validationParams?.target_x;
                    const targetY = stepInfo.validationParams?.target_y;

                    if (targetX === undefined || targetY === undefined) {
                        console.log(`   ⚠️  No target coordinates specified`);
                        return false;
                    }

                    console.log(`   🎯 Target: (${targetX}, ${targetY})`);

                    // Get current player position and find path to adjacent tile
                    const pathInfo = await this.page.evaluate((tx, ty) => {
                        // Player is ALWAYS at the center of the displayed grid
                        // Find all cases and get their coordinates, then find the center one
                        const allCases = Array.from(document.querySelectorAll('.case'));

                        if (allCases.length === 0) {
                            return { error: 'No cases found on map' };
                        }

                        // Extract all coordinates
                        const coordsList = allCases.map(caseEl => {
                            const coords = caseEl.getAttribute('data-coords');
                            if (coords) {
                                const [x, y] = coords.split(',').map(Number);
                                return { x, y, coords, element: caseEl };
                            }
                            return null;
                        }).filter(c => c !== null);

                        // Find min/max to determine center
                        const xs = coordsList.map(c => c.x);
                        const ys = coordsList.map(c => c.y);
                        const minX = Math.min(...xs);
                        const maxX = Math.max(...xs);
                        const minY = Math.min(...ys);
                        const maxY = Math.max(...ys);

                        // Center coordinates
                        const centerX = Math.round((minX + maxX) / 2);
                        const centerY = Math.round((minY + maxY) / 2);

                        // Player is at center
                        const px = centerX;
                        const py = centerY;

                        // Calculate which adjacent tile to reach (pick closest)
                        const adjacentTiles = [
                            { x: tx, y: ty + 1, dir: 'above' },
                            { x: tx, y: ty - 1, dir: 'below' },
                            { x: tx + 1, y: ty, dir: 'right' },
                            { x: tx - 1, y: ty, dir: 'left' }
                        ];

                        // Find movement tiles
                        const movementTiles = Array.from(document.querySelectorAll('.case.go'));
                        const availableCoords = movementTiles.map(tile => {
                            const coords = tile.getAttribute('data-coords');
                            const [x, y] = coords.split(',').map(Number);
                            return { x, y, coords };
                        });

                        // Simple greedy pathfinding: move towards closest adjacent tile
                        let bestMove = null;
                        let bestDistance = Infinity;

                        for (const move of availableCoords) {
                            for (const adj of adjacentTiles) {
                                const distance = Math.abs(move.x - adj.x) + Math.abs(move.y - adj.y);
                                if (distance < bestDistance) {
                                    bestDistance = distance;
                                    bestMove = { coords: move.coords, targetAdj: adj, isAdjacent: distance === 0 };
                                }
                            }
                        }

                        return {
                            playerPos: { x: px, y: py },
                            bestMove: bestMove,
                            availableMoves: availableCoords.length
                        };
                    }, targetX, targetY);

                    if (pathInfo.error) {
                        console.log(`   ⚠️  ${pathInfo.error}`);
                        return false;
                    }

                    console.log(`   📍 Player at (${pathInfo.playerPos.x}, ${pathInfo.playerPos.y})`);
                    console.log(`   🎲 Available moves: ${pathInfo.availableMoves}`);

                    if (!pathInfo.bestMove) {
                        console.log(`   ⚠️  No valid moves found`);
                        return false;
                    }

                    const moveCoords = pathInfo.bestMove.coords;
                    const isAdjacent = pathInfo.bestMove.isAdjacent;

                    console.log(`   ➡️  Moving to ${moveCoords} ${isAdjacent ? '(ADJACENT!)' : '(getting closer)'}`);

                    const selector = `.case[data-coords="${moveCoords}"]`;

                    // FIRST CLICK: Show arrow
                    await this.page.click(selector);
                    console.log(`   👉 First click on ${moveCoords}`);
                    await this.sleep(500);

                    // SECOND CLICK: Actually move
                    await this.page.click(selector);
                    console.log(`   ✓ Second click - moved to ${moveCoords}`);
                    await this.sleep(2500);

                    return true;
                } catch (error) {
                    console.log(`   ⚠️  Adjacent movement failed: ${error.message}`);
                    return false;
                }
                break;

            case 'ui_panel_opened':
                // Click to open panel
                const panel = stepInfo.validationParams?.panel;
                if (panel === 'characteristics') {
                    await this.page.click('#show-caracs');
                    console.log('   ✓ Opened characteristics panel');
                    await this.sleep(1500);
                    return true;
                } else if (panel === 'actions') {
                    // Click on target tile to show actions
                    if (stepInfo.targetSelector) {
                        await this.page.click(stepInfo.targetSelector);
                        console.log(`   ✓ Clicked ${stepInfo.targetSelector} to show actions`);
                        await this.sleep(2000);
                        return true;
                    }
                }
                break;

            case 'action_used':
                // Find and click action button
                const actionName = stepInfo.validationParams?.action_name;
                if (actionName) {
                    // Check if we already clicked this action (to avoid loops)
                    // Use stepCounter instead of stepId because same stepId can appear multiple times
                    if (this.lastActionClicked === `${this.stepCounter}_${actionName}`) {
                        console.log(`   ⏭️  Already clicked ${actionName}, waiting for validation...`);
                        await this.sleep(3000); // Give more time for action to process
                        return false; // Don't click again
                    }

                    const clicked = await this.page.evaluate((action) => {
                        // Find action button in #ajax-data
                        const buttons = Array.from(document.querySelectorAll('#ajax-data button, #ajax-data .action'));
                        const actionBtn = buttons.find(btn =>
                            btn.textContent.toLowerCase().includes(action.toLowerCase()) ||
                            btn.getAttribute('data-action') === action
                        );
                        if (actionBtn) {
                            actionBtn.click();
                            return true;
                        }
                        return false;
                    }, actionName);

                    if (clicked) {
                        console.log(`   ✓ Clicked action: ${actionName}`);
                        this.lastActionClicked = `${this.stepCounter}_${actionName}`;
                        await this.sleep(4000); // Longer wait for action to execute
                        return true;
                    }
                }
                break;

            case 'ui_element_hidden':
                // Close a UI element (e.g., close card)
                const elementToHide = stepInfo.validationParams?.element;
                if (elementToHide) {
                    // Try to click close button
                    const closed = await this.page.evaluate(() => {
                        const closeBtn = document.querySelector('.close-card');
                        if (closeBtn) {
                            closeBtn.click();
                            return true;
                        }
                        return false;
                    });

                    if (closed) {
                        console.log(`   ✓ Closed element: ${elementToHide}`);
                        await this.sleep(1500);
                        return true;
                    }
                }
                break;

            case 'ui_interaction':
                // Generic UI interaction - click the target element
                if (stepInfo.targetSelector) {
                    try {
                        await this.page.click(stepInfo.targetSelector);
                        console.log(`   ✓ Clicked UI element: ${stepInfo.targetSelector}`);
                        await this.sleep(1500);
                        return true;
                    } catch (error) {
                        console.log(`   ⚠️  Failed to click ${stepInfo.targetSelector}: ${error.message}`);
                        return false;
                    }
                }
                break;
        }

        return false;
    }

    async advanceStep() {
        // Get step info
        const result = await this.page.evaluate(() => {
            const buttons = Array.from(document.querySelectorAll('button'));
            const nextBtn = buttons.find(btn => btn.textContent.includes('Suivant'));

            if (!window.tutorialUI || !window.tutorialUI.stepData) {
                return { hasButton: false, clicked: false, reason: 'no_tutorial_ui' };
            }

            const requiresValidation = window.tutorialUI.stepData.requires_validation;
            const validationType = window.tutorialUI.stepData.config?.validation_type;

            if (!nextBtn) {
                return { hasButton: false, clicked: false, reason: 'no_button', requiresValidation, validationType };
            }

            // If step requires validation, don't click yet - need to perform action first
            if (requiresValidation && validationType && validationType !== 'none') {
                return { hasButton: true, clicked: false, reason: 'requires_validation', validationType };
            }

            // Click the button
            nextBtn.click();
            return { hasButton: true, clicked: true, reason: 'clicked' };
        });

        if (result.clicked) {
            await this.sleep(1500);
            return true;
        } else if (result.reason === 'requires_validation') {
            // Special handling for pathfinding - keep moving until adjacent
            if (result.validationType === 'adjacent_to_position') {
                console.log(`   🗺️  Pathfinding to adjacent position...`);
                let attempts = 0;
                const maxAttempts = 15; // More attempts for pathfinding

                while (attempts < maxAttempts) {
                    attempts++;

                    // Try to move closer
                    const moved = await this.performRequiredAction();
                    if (!moved) {
                        console.log(`   ⚠️  No valid moves available`);
                        break;
                    }

                    await this.sleep(1500);

                    // Check if validation passed (reached adjacent tile)
                    const validation = await this.page.evaluate(() => {
                        const buttons = Array.from(document.querySelectorAll('button'));
                        const nextBtn = buttons.find(btn => btn.textContent.includes('Suivant'));
                        if (nextBtn) {
                            nextBtn.click();
                            return true;
                        }
                        return false;
                    });

                    if (validation) {
                        console.log(`   ✅ Reached adjacent position after ${attempts} move(s)`);
                        await this.sleep(1500);
                        return true;
                    }
                }

                console.log(`   ⏸️  Stopped pathfinding after ${attempts} attempts`);
                return false;
            } else if (result.validationType === 'movements_depleted') {
                // Special handling for movement depletion - keep moving until done
                console.log(`   🏃 Depleting movements...`);
                let attempts = 0;
                const maxAttempts = 10; // Safety limit

                while (attempts < maxAttempts) {
                    attempts++;

                    // Try to move
                    const moved = await this.performRequiredAction();
                    if (!moved) {
                        console.log(`   ⚠️  No more movement tiles available`);
                        break;
                    }

                    await this.sleep(1500);

                    // Check if validation passed
                    const validation = await this.page.evaluate(() => {
                        const buttons = Array.from(document.querySelectorAll('button'));
                        const nextBtn = buttons.find(btn => btn.textContent.includes('Suivant'));
                        if (nextBtn) {
                            nextBtn.click();
                            return true;
                        }
                        return false;
                    });

                    if (validation) {
                        console.log(`   ✅ Movements depleted after ${attempts} move(s)`);
                        await this.sleep(1500);
                        return true;
                    }
                }

                console.log(`   ⏸️  Stopped after ${attempts} attempts`);
                return false;
            } else {
                // Other validation types - single attempt
                const performed = await this.performRequiredAction();
                if (performed) {
                    // Wait a bit and try clicking next again
                    await this.sleep(1500);
                    const clicked = await this.page.evaluate(() => {
                        const buttons = Array.from(document.querySelectorAll('button'));
                        const nextBtn = buttons.find(btn => btn.textContent.includes('Suivant'));
                        if (nextBtn) {
                            nextBtn.click();
                            return true;
                        }
                        return false;
                    });
                    if (clicked) {
                        await this.sleep(1500);
                        return true;
                    }
                }
                console.log(`   ⏸️  Could not complete validation: ${result.validationType}`);
            }
        } else if (result.reason === 'no_button') {
            console.log('   ℹ️  No next button available');
        }

        return false;
    }

    async takeScreenshot(stepId, suffix = '') {
        try {
            const stepNum = String(this.stepCounter).padStart(3, '0');
            const filename = `step_${stepNum}_${stepId}${suffix ? '_' + suffix : ''}.png`;
            const filepath = `${this.screenshotDir}/${filename}`;
            await this.page.screenshot({ path: filepath, fullPage: false });
            console.log(`   📸 ${filename}`);
            return filepath;
        } catch (error) {
            console.log(`   ⚠️  Screenshot failed: ${error.message}`);
            return null;
        }
    }

    async sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    async runCompleteTest() {
        try {
            await this.initialize();
            await this.login();
            await this.startTutorial();

            // Run tutorial until completion
            console.log('\n' + '='.repeat(60));
            console.log('TESTING TUTORIAL STEPS (running until completion)');
            console.log('='.repeat(60));

            let maxSteps = 100; // Safety limit to prevent infinite loops
            let stepCount = 0;

            while (stepCount < maxSteps) {
                stepCount++;

                const result = await this.analyzeStep();

                if (!result) {
                    console.log('\n⚠️  Could not analyze step, stopping test');
                    break;
                }

                await this.sleep(1000);

                // Check if tutorial ended before trying to advance
                const stillActive = await this.page.evaluate(() => {
                    return document.querySelector('#tutorial-overlay') !== null;
                });

                if (!stillActive) {
                    console.log('\n🎉 Tutorial completed successfully!');

                    // Check for completion/reward screen
                    const completionInfo = await this.page.evaluate(() => {
                        return {
                            url: window.location.href,
                            hasReward: !!document.querySelector('.tutorial-reward, .reward'),
                            bodyText: document.body.innerText.substring(0, 500)
                        };
                    });

                    console.log(`   Final URL: ${completionInfo.url}`);
                    console.log(`   Has reward: ${completionInfo.hasReward}`);

                    // Take final screenshot
                    if (this.screenshotsEnabled) {
                        await this.takeScreenshot('completion', 'final');
                    }
                    break;
                }

                // Try to advance
                const advanced = await this.advanceStep();

                if (!advanced) {
                    console.log('   ⚠️  Could not advance step - tutorial may be stuck');
                    // Take a debug screenshot
                    if (this.screenshotsEnabled) {
                        await this.takeScreenshot(`stuck_step_${stepCount}`, 'debug');
                    }
                    break;
                }

                await this.sleep(2000);
            }

            if (stepCount >= maxSteps) {
                console.log(`\n⚠️  Reached maximum step limit (${maxSteps}) - stopping test`);
            }

            await this.generateReport();

        } catch (error) {
            console.error('❌ Test failed:', error.message);
            this.issues.push({
                type: 'test_failure',
                message: error.message,
                stack: error.stack
            });
        } finally {
            await this.browser.close();
        }
    }

    async generateReport() {
        console.log('\n' + '='.repeat(60));
        console.log('TEST REPORT');
        console.log('='.repeat(60));

        if (this.issues.length === 0) {
            console.log('\n✅ No issues found! Tutorial is coherent.\n');
            return;
        }

        console.log(`\n⚠️  Found ${this.issues.length} issues:\n`);

        // Group by type
        const byType = {};
        this.issues.forEach(issue => {
            if (!byType[issue.type]) {
                byType[issue.type] = [];
            }
            byType[issue.type].push(issue);
        });

        Object.entries(byType).forEach(([type, issues]) => {
            console.log(`\n${type.toUpperCase().replace(/_/g, ' ')} (${issues.length}):`);
            issues.forEach((issue, i) => {
                console.log(`   ${i + 1}. Step: ${issue.step || 'unknown'}`);
                console.log(`      ${issue.message}`);
                if (issue.details) {
                    console.log(`      Details: ${JSON.stringify(issue.details)}`);
                }
            });
        });

        // Save detailed report to validation directory
        const fs = require('fs');
        const reportPath = `${this.screenshotDir}/VALIDATION_REPORT.md`;
        const jsonPath = `${this.screenshotDir}/issues.json`;

        // Create markdown report
        let markdown = `# Tutorial Validation Report\n\n`;
        markdown += `**Date:** ${new Date().toISOString()}\n`;
        markdown += `**Steps Tested:** ${this.stepCounter}\n`;
        markdown += `**Issues Found:** ${this.issues.length}\n\n`;

        if (this.issues.length === 0) {
            markdown += `✅ **All tests passed!** No issues found.\n`;
        } else {
            markdown += `## Issues by Type\n\n`;
            Object.entries(byType).forEach(([type, issues]) => {
                markdown += `### ${type.replace(/_/g, ' ').toUpperCase()} (${issues.length})\n\n`;
                issues.forEach((issue, i) => {
                    markdown += `${i + 1}. **Step:** ${issue.step || 'unknown'}\n`;
                    markdown += `   - ${issue.message}\n`;
                    if (issue.details) {
                        markdown += `   - Details: \`${JSON.stringify(issue.details)}\`\n`;
                    }
                    markdown += `\n`;
                });
            });
        }

        fs.writeFileSync(reportPath, markdown);
        fs.writeFileSync(jsonPath, JSON.stringify(this.issues, null, 2));

        console.log(`\n📄 Reports saved to: ${this.screenshotDir}/`);
        console.log(`   - VALIDATION_REPORT.md`);
        console.log(`   - issues.json\n`);
    }
}

// Run test
const test = new TutorialE2ETest();
test.runCompleteTest();
