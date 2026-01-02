/**
 * Tutorial Step Navigator
 * Debug tool for jumping between tutorial steps
 */
class TutorialStepNavigator {
    constructor(tutorialUI) {
        this.tutorialUI = tutorialUI;
        this.isVisible = false;
        this.allSteps = [];
        this.init();
    }

    init() {
        // Create navigator UI
        this.createUI();

        // Load all steps
        this.loadSteps();

        // Setup keyboard shortcut (press ` key to toggle)
        document.addEventListener('keydown', (e) => {
            if (e.key === '`' || e.key === '²') {  // ` or ²
                e.preventDefault();
                this.toggle();
            }
        });

        console.log('[StepNavigator] Initialized - Press ` or ² to toggle');
    }

    createUI() {
        // Create navigator container
        const nav = document.createElement('div');
        nav.id = 'tutorial-step-navigator';
        nav.className = 'hidden';
        nav.innerHTML = `
            <h3>
                📋 Step Navigator
                <button class="step-nav-toggle" onclick="window.tutorialStepNavigator.toggle()">✕</button>
            </h3>
            <div class="step-nav-list" id="step-nav-list">
                <div style="text-align: center; color: #999;">Loading steps...</div>
            </div>
        `;
        document.body.appendChild(nav);

        // Create toggle button
        const toggleBtn = document.createElement('button');
        toggleBtn.id = 'tutorial-step-nav-toggle-btn';
        toggleBtn.textContent = '📋 Steps';
        toggleBtn.onclick = () => this.toggle();
        document.body.appendChild(toggleBtn);

        this.navElement = nav;
        this.toggleButton = toggleBtn;
    }

    async loadSteps() {
        try {
            // Fetch all tutorial configurations
            const response = await fetch('/api/tutorial/get-all-steps.php');
            if (!response.ok) {
                // Fallback: use stepData if API not available
                this.renderStepsFromSession();
                return;
            }

            const data = await response.json();
            if (data.success) {
                this.allSteps = data.steps;
                this.renderSteps();
            }
        } catch (error) {
            console.error('[StepNavigator] Error loading steps:', error);
            this.renderStepsFromSession();
        }
    }

    renderStepsFromSession() {
        // Fallback: just show current step info
        const listEl = document.getElementById('step-nav-list');
        if (!listEl) return;

        if (!this.tutorialUI || !this.tutorialUI.stepData) {
            listEl.innerHTML = '<div style="color: #999;">No tutorial active</div>';
            return;
        }

        const currentStep = this.tutorialUI.stepData;
        listEl.innerHTML = `
            <div class="step-nav-item current">
                <div class="step-num">Step ${currentStep.step_number || '?'}</div>
                <div class="step-id">${currentStep.step_id || 'Unknown'}</div>
                <div class="step-status">▶ Current Step</div>
            </div>
            <div style="margin-top: 10px; padding: 10px; background: #222; border-radius: 4px; font-size: 11px;">
                <div style="color: #999; margin-bottom: 5px;">Step Info:</div>
                <div style="color: #ddd;">
                    Validation: ${currentStep.config?.validation_type || 'none'}<br>
                    Mode: ${currentStep.config?.interaction_mode || 'blocking'}
                </div>
            </div>
        `;
    }

    renderSteps() {
        const listEl = document.getElementById('step-nav-list');
        if (!listEl || this.allSteps.length === 0) {
            this.renderStepsFromSession();
            return;
        }

        const currentStepNum = this.tutorialUI?.stepData?.step_number || 0;

        listEl.innerHTML = this.allSteps.map(step => {
            const isCurrent = step.step_number === currentStepNum;
            const isCompleted = step.step_number < currentStepNum;

            let className = 'step-nav-item';
            if (isCurrent) className += ' current';
            if (isCompleted) className += ' completed';

            const icon = isCurrent ? '▶' : (isCompleted ? '✓' : '○');
            const status = isCurrent ? 'Current Step' : (isCompleted ? 'Completed' : 'Upcoming');

            return `
                <div class="${className}" onclick="window.tutorialStepNavigator.jumpTo(${step.step_number})">
                    <div class="step-num">${icon} Step ${step.step_number}</div>
                    <div class="step-id">${step.step_id}</div>
                    <div class="step-status">${status}</div>
                </div>
            `;
        }).join('');
    }

    async jumpTo(stepNumber) {
        console.log(`[StepNavigator] Jumping to step ${stepNumber}`);

        try {
            const response = await fetch('/api/tutorial/jump-to-step.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ step_number: stepNumber })
            });

            const data = await response.json();

            if (data.success) {
                console.log('[StepNavigator] Jump successful, reloading page...');
                // Reload to apply new step
                window.location.reload();
            } else {
                console.error('[StepNavigator] Jump failed:', data.error);
                alert(`Failed to jump to step: ${data.error}`);
            }
        } catch (error) {
            console.error('[StepNavigator] Jump error:', error);
            alert(`Error jumping to step: ${error.message}`);
        }
    }

    toggle() {
        this.isVisible = !this.isVisible;

        if (this.isVisible) {
            this.navElement.classList.remove('hidden');
            this.toggleButton.classList.add('hidden');
            // Refresh steps when showing
            this.loadSteps();
        } else {
            this.navElement.classList.add('hidden');
            this.toggleButton.classList.remove('hidden');
        }

        console.log('[StepNavigator]', this.isVisible ? 'Shown' : 'Hidden');
    }

    show() {
        if (!this.isVisible) this.toggle();
    }

    hide() {
        if (this.isVisible) this.toggle();
    }
}

// Make it globally accessible
window.TutorialStepNavigator = TutorialStepNavigator;
