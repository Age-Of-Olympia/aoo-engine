# Tutorial XP/PI Integration - Addendum

**Document Version**: 1.0
**Date**: 2025-11-11
**Status**: Planning Addition
**Parent Document**: tutorial-refactoring-plan.md

---

## Overview

This addendum describes the integration of XP (Experience Points) and PI (Points d'Investissement) rewards throughout the tutorial to create a more engaging learning experience and teach the progression system through practice.

### Key Concept

**Every tutorial step rewards XP**, allowing players to:
1. See their XP bar fill up progressively
2. Understand that actions = XP gain
3. Level up during the tutorial
4. **Invest their first PI to gain an extra movement point** (practical learning)

---

## XP Reward Structure

### XP per Step Category

| Step Category | XP Reward | Rationale |
|--------------|-----------|-----------|
| **Information steps** (text only) | 2 XP | Small reward for reading |
| **Navigation steps** (moving, clicking UI) | 5 XP | Basic interaction |
| **Action steps** (using game actions) | 10 XP | Practicing mechanics |
| **Combat steps** (attacking, defeating enemy) | 15 XP | Complex interaction |
| **Validation steps** (completing challenges) | 20 XP | Major accomplishment |

### Total XP Earned

**Goal**: Player should earn enough XP to:
- Gain **1 level** during tutorial (early in progression)
- Receive **1 PI** to invest
- Experience the full progression loop

**Calculation**:
- 45 steps × average 10 XP = **450 XP total**
- Plus completion bonus: **+50 XP**
- **Grand total: 500 XP**

Assuming first level requires 100 XP:
- Player levels up around step 10
- Receives 1 PI to invest
- Can practice PI investment around step 35

---

## Modified Step Sequence

### Original Progression Section (Steps 32-36)

**OLD**:
```
32. XP intro (explain XP system)
33. PI intro (explain PI system)
34. Training action (how to gain XP)
35. [Next section...]
```

### NEW Enhanced Progression Section (Steps 32-37)

**Step 32: XP Introduction**
```json
{
  "id": 32,
  "type": "xp_intro",
  "title": "Progression : XP",
  "config": {
    "text": "Vous avez remarqué votre barre d'XP? Elle a augmenté à chaque étape! Vous gagnez de l'<strong>XP (Expérience)</strong> en jouant : combattant, explorant, accomplissant des actions. Vous avez actuellement {CURRENT_XP} XP!",
    "target_selector": "#xp-display",
    "tooltip_position": "bottom",
    "requires_validation": false,
    "show_characteristic": "xp",
    "xp_reward": 10,
    "highlight_xp_bar": true
  }
}
```

**Step 33: Level Up Notification**
```json
{
  "id": 33,
  "type": "level_up_intro",
  "title": "Vous avez gagné un niveau!",
  "config": {
    "text": "Félicitations! Vous êtes passé au <strong>niveau {NEW_LEVEL}</strong>! Chaque niveau vous rapporte des <strong>PI (Points d'Investissement)</strong> que vous pouvez utiliser pour améliorer vos caractéristiques.",
    "target_selector": "#level-display",
    "tooltip_position": "center",
    "requires_validation": false,
    "show_level_up_animation": true,
    "xp_reward": 0,
    "trigger_condition": "player_has_pi > 0"
  }
}
```

**Step 34: PI Introduction**
```json
{
  "id": 34,
  "type": "pi_intro",
  "title": "Points d'Investissement (PI)",
  "config": {
    "text": "Vous avez maintenant <strong>{CURRENT_PI} PI</strong>! Les PI vous permettent d'améliorer vos caractéristiques de façon permanente. Vous pouvez investir dans : Mvt, PV, CC, F, PM, etc.",
    "target_selector": "#pi-display",
    "tooltip_position": "bottom",
    "requires_validation": false,
    "show_characteristic": "pi",
    "xp_reward": 5,
    "show_investment_panel": true
  }
}
```

**Step 35: Practice PI Investment - Gain Extra Movement**
```json
{
  "id": 35,
  "type": "pi_investment_practice",
  "title": "Investissez votre premier PI!",
  "config": {
    "text": "Essayons! Investissez 1 PI dans <strong>Mvt (Mouvements)</strong> pour gagner un mouvement permanent supplémentaire. Cliquez sur le bouton '+' à côté de Mvt.",
    "target_selector": "#invest-mvt-button",
    "tooltip_position": "left",
    "requires_validation": true,
    "validation_type": "pi_invested",
    "validation_params": {
      "characteristic": "mvt",
      "amount": 1
    },
    "validation_hint": "Cliquez sur le '+' à côté de Mvt dans le panneau d'investissement",
    "xp_reward": 20,
    "context_changes": {
      "unlock_investment_panel": true,
      "highlight_mvt_investment": true
    },
    "success_message": "Bravo! Vous avez maintenant {NEW_MVT} mouvements par tour au lieu de {OLD_MVT}!"
  }
}
```

**Step 36: Verify Movement Increase**
```json
{
  "id": 36,
  "type": "verify_investment",
  "title": "Voyez la différence!",
  "config": {
    "text": "Regardez votre panneau de ressources : vous avez maintenant <strong>{NEW_MVT} mouvements</strong> disponibles! C'est le pouvoir de l'investissement PI. À chaque niveau, vous pourrez améliorer votre personnage.",
    "target_selector": "#mvt-display",
    "tooltip_position": "bottom",
    "requires_validation": false,
    "xp_reward": 10,
    "context_changes": {
      "restore_mvt": true,
      "show_increased_mvt": true
    },
    "highlight_difference": true
  }
}
```

**Step 37: Training Action**
```json
{
  "id": 37,
  "type": "training_action",
  "title": "Entraînement",
  "config": {
    "text": "L'action <strong>Entraînement</strong> vous permet de gagner de l'XP en vous entraînant. C'est une bonne façon de progresser entre les aventures! Chaque action dans le jeu réel vous rapporte de l'XP.",
    "target_selector": ".action[data-action='entrainement']",
    "tooltip_position": "left",
    "requires_validation": false,
    "xp_reward": 10,
    "show_action_xp_values": true
  }
}
```

---

## Step-by-Step XP Rewards

Here's the complete XP distribution across all 45 steps:

### Section 1: Welcome & World (Steps 1-5)
| Step | Type | Title | XP Reward | Running Total |
|------|------|-------|-----------|---------------|
| 1 | welcome | Bienvenue! | 5 | 5 |
| 2 | game_overview | Un jeu au tour par tour | 5 | 10 |
| 3 | player_location | Vous voici! | 5 | 15 |
| 4 | movement_intro | Votre premier mouvement | 10 | 25 |
| 5 | movement_limit | Mouvements limités | 20 | 45 |

### Section 2: Turn System (Steps 6-10)
| Step | Type | Title | XP Reward | Running Total |
|------|------|-------|-----------|---------------|
| 6 | turn_intro | Le système de tours | 5 | 50 |
| 7 | resource_regen | Régénération des ressources | 10 | 60 |
| 8 | action_intro | Points d'Action | 5 | 65 |
| 9 | action_list | Actions disponibles | 5 | 70 |
| 10 | search_action | Pratiquez: Fouiller | 15 | 85 |

### Section 3: Characteristics (Steps 11-13)
| Step | Type | Title | XP Reward | Running Total |
|------|------|-------|-----------|---------------|
| 11 | characteristics_intro | Vos Caractéristiques | 5 | 90 |
| 12 | pv_intro | Points de Vie (PV) | 5 | 95 |
| 13 | regeneration_intro | Régénération (R) | 5 | 100 |

**🎉 LEVEL UP at step 13!** (100 XP reached)

### Section 4: Combat Basics (Steps 14-22)
| Step | Type | Title | XP Reward | Running Total |
|------|------|-------|-----------|---------------|
| 14 | combat_intro | Le Combat | 5 | 105 |
| 15 | cc_intro | Capacité de Combat (CC) | 5 | 110 |
| 16 | force_intro | Force (F) | 5 | 115 |
| 17 | endurance_intro | Endurance (E) | 5 | 120 |
| 18 | attack_enemy | Attaquez! | 15 | 135 |
| 19 | combat_log | Le Log de Combat | 5 | 140 |
| 20 | finish_combat | Terminez le combat | 25 | 165 |
| 21 | death_resurrection | Mort et Résurrection | 5 | 170 |
| 22 | rest_action | Action Repos | 10 | 180 |

### Section 5: Inventory (Steps 23-25)
| Step | Type | Title | XP Reward | Running Total |
|------|------|-------|-----------|---------------|
| 23 | inventory_intro | L'Inventaire | 5 | 185 |
| 24 | equipment_slots | Emplacements d'équipement | 5 | 190 |
| 25 | equip_item | Équipez un objet | 15 | 205 |

### Section 6: Magic (Steps 26-27) - Conditional
| Step | Type | Title | XP Reward | Running Total |
|------|------|-------|-----------|---------------|
| 26 | magic_intro | La Magie | 5 | 210 |
| 27 | spell_list | Vos sorts | 5 | 215 |

### Section 7: Social (Steps 28-30)
| Step | Type | Title | XP Reward | Running Total |
|------|------|-------|-----------|---------------|
| 28 | missives_intro | Les Missives | 10 | 225 |
| 29 | forum_intro | Le Forum | 5 | 230 |
| 30 | faction_intro | Votre Faction | 5 | 235 |

### Section 8: Progression (Steps 31-37) ⭐ NEW
| Step | Type | Title | XP Reward | Running Total |
|------|------|-------|-----------|---------------|
| 31 | animateur_intro | Votre Animateur | 5 | 240 |
| 32 | xp_intro | Progression : XP | 10 | 250 |
| 33 | level_up_intro | Vous avez gagné un niveau! | 0 | 250 |
| 34 | pi_intro | Points d'Investissement (PI) | 5 | 255 |
| 35 | **pi_investment_practice** | **Investissez votre premier PI!** | **20** | **275** |
| 36 | verify_investment | Voyez la différence! | 10 | 285 |
| 37 | training_action | Entraînement | 10 | 295 |

### Section 9: World Exploration (Steps 38-40)
| Step | Type | Title | XP Reward | Running Total |
|------|------|-------|-----------|---------------|
| 38 | perception_intro | Perception (P) | 5 | 300 |
| 39 | chessboard_view | Vue Damier | 5 | 305 |
| 40 | plans_intro | Les Plans | 5 | 310 |

### Section 10: Advanced (Steps 41-47) - Extended to 47
| Step | Type | Title | XP Reward | Running Total |
|------|------|-------|-----------|---------------|
| 41 | terrain_costs | Coûts de terrain | 5 | 315 |
| 42 | merchant_intro | Les Marchands | 5 | 320 |
| 43 | gold_intro | L'or (Po) | 5 | 325 |
| 44 | gods_intro | Les Dieux | 5 | 330 |
| 45 | pvp_intro | Combat Joueur vs Joueur | 5 | 335 |
| 46 | help_resources | Où trouver de l'aide | 5 | 340 |
| 47 | completion | Tutoriel terminé! | 50 | **390** |

**Total XP: 390** (enough to level up at least once, possibly twice depending on XP curve)

---

## Implementation Changes

### 1. TutorialContext.php - Add XP Tracking

Add to `TutorialContext` class:

```php
private int $tutorialXP = 0;
private int $tutorialLevel = 1;
private int $tutorialPI = 0;

/**
 * Award XP for completing step
 */
public function awardXP(int $amount): void
{
    $this->tutorialXP += $amount;

    // Check for level up
    $xpNeeded = $this->getXPForLevel($this->tutorialLevel + 1);

    if ($this->tutorialXP >= $xpNeeded) {
        $this->levelUp();
    }

    // Update player XP display
    $this->player->data->xp = $this->tutorialXP;
}

/**
 * Level up and gain PI
 */
private function levelUp(): void
{
    $this->tutorialLevel++;
    $this->tutorialPI++;

    // Store level up flag for notification
    $this->tutorialState['pending_level_up'] = true;
    $this->tutorialState['new_level'] = $this->tutorialLevel;
}

/**
 * Invest PI in characteristic
 */
public function investPI(string $characteristic, int $amount): bool
{
    if ($this->tutorialPI < $amount) {
        return false;
    }

    // Deduct PI
    $this->tutorialPI -= $amount;

    // Apply investment (for tutorial, we modify player data temporarily)
    switch ($characteristic) {
        case 'mvt':
            $raceJson = json()->decode('races', $this->player->data->race);
            $baseMvt = $raceJson->mvt ?? 4;
            $this->player->data->mvt = $baseMvt + $amount;
            $this->tutorialState['mvt_investment'] = $amount;
            break;

        // Add other characteristics as needed
    }

    return true;
}

/**
 * Get XP needed for level
 */
private function getXPForLevel(int $level): int
{
    // Simple exponential curve: 100, 250, 450, 700, etc.
    return 100 * $level + 50 * ($level - 1) * ($level - 1);
}

public function getTutorialXP(): int
{
    return $this->tutorialXP;
}

public function getTutorialLevel(): int
{
    return $this->tutorialLevel;
}

public function getTutorialPI(): int
{
    return $this->tutorialPI;
}

public function hasPendingLevelUp(): bool
{
    return $this->tutorialState['pending_level_up'] ?? false;
}

public function clearLevelUpNotification(): void
{
    $this->tutorialState['pending_level_up'] = false;
}
```

### 2. AbstractStep.php - Add XP Reward

Add to `AbstractStep` class:

```php
/**
 * Get XP reward for this step
 */
public function getXPReward(): int
{
    return $this->config['xp_reward'] ?? 0;
}

/**
 * Called when step is completed - now awards XP
 */
public function onComplete(TutorialContext $context): void
{
    $xpReward = $this->getXPReward();

    if ($xpReward > 0) {
        $context->awardXP($xpReward);
    }
}
```

### 3. TutorialManager.php - Handle Level Ups

Modify `advanceStep()` method:

```php
public function advanceStep(array $validationData = []): array
{
    // ... existing validation code ...

    // Execute step completion actions (awards XP)
    $stepInstance->onComplete($this->context);

    // Check for level up
    if ($this->context->hasPendingLevelUp()) {
        // Insert level up step before next step
        $nextStep = $progress['current_step'] + 0.5; // Special flag

        return [
            'success' => true,
            'level_up' => true,
            'new_level' => $this->context->getTutorialLevel(),
            'new_pi' => $this->context->getTutorialPI(),
            'next_step' => $this->getCurrentStepData()
        ];
    }

    // ... rest of method ...
}
```

### 4. TutorialUI.js - Display XP Bar

Add XP bar to tutorial UI:

```javascript
/**
 * Update XP display
 */
updateXPDisplay(context) {
    const xp = context.tutorial_xp || 0;
    const level = context.tutorial_level || 1;
    const xpNeeded = this.getXPForLevel(level + 1);
    const xpProgress = (xp / xpNeeded) * 100;

    if (!$('#tutorial-xp-bar').length) {
        $('#tutorial-controls').before(`
            <div id="tutorial-xp-bar">
                <div class="xp-bar-container">
                    <div class="xp-bar-fill" style="width: ${xpProgress}%"></div>
                </div>
                <div class="xp-text">
                    Niveau ${level} - XP: ${xp} / ${xpNeeded}
                </div>
            </div>
        `);
    } else {
        $('#tutorial-xp-bar .xp-bar-fill').css('width', `${xpProgress}%`);
        $('#tutorial-xp-bar .xp-text').text(`Niveau ${level} - XP: ${xp} / ${xpNeeded}`);
    }
}

/**
 * Show level up animation
 */
showLevelUpAnimation(newLevel, newPI) {
    const $modal = $(`
        <div id="level-up-modal">
            <div class="modal-content level-up">
                <h2>🎉 NIVEAU SUPÉRIEUR!</h2>
                <div class="level-display">Niveau ${newLevel}</div>
                <p>Vous avez gagné <strong>1 Point d'Investissement (PI)</strong>!</p>
                <p>PI disponibles: <strong>${newPI}</strong></p>
                <button class="continue-button">Continuer</button>
            </div>
        </div>
    `);

    $('body').append($modal);
    $modal.fadeIn();

    $('.continue-button').on('click', () => {
        $modal.fadeOut(() => $modal.remove());
        this.next(); // Continue to next step
    });
}

/**
 * Calculate XP needed for level
 */
getXPForLevel(level) {
    return 100 * level + 50 * (level - 1) * (level - 1);
}
```

### 5. New Step Implementation: PIInvestmentStep.php

Create `src/Tutorial/Steps/Progression/PIInvestmentStep.php`:

```php
<?php

namespace App\Tutorial\Steps\Progression;

use App\Tutorial\Steps\AbstractStep;
use App\Tutorial\TutorialContext;

class PIInvestmentStep extends AbstractStep
{
    public function getData(): array
    {
        $currentPI = $this->context->getTutorialPI();
        $oldMvt = $this->getBaseMvt();
        $newMvt = $oldMvt + 1;

        return [
            'title' => $this->config['title'],
            'text' => $this->replacePlaceholders($this->config['text'], [
                'CURRENT_PI' => $currentPI,
                'OLD_MVT' => $oldMvt,
                'NEW_MVT' => $newMvt
            ]),
            'target_selector' => $this->getTargetSelector(),
            'tooltip_position' => $this->getTooltipPosition(),
            'requires_validation' => true,
            'show_investment_panel' => true
        ];
    }

    public function validate(array $data): bool
    {
        $characteristic = $data['characteristic'] ?? null;
        $amount = $data['amount'] ?? 0;

        if ($characteristic !== 'mvt' || $amount !== 1) {
            return false;
        }

        return $this->context->investPI($characteristic, $amount);
    }

    public function onComplete(TutorialContext $context): void
    {
        parent::onComplete($context); // Awards XP

        // Store success message
        $context->getTutorialState()['investment_success'] = true;
    }

    private function getBaseMvt(): int
    {
        $player = $this->context->getPlayer();
        $raceJson = json()->decode('races', $player->data->race);
        return $raceJson->mvt ?? 4;
    }

    private function replacePlaceholders(string $text, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
        return $text;
    }
}
```

---

## Cypress Tests - XP/PI System

### New Test Suite: `tests/cypress/e2e/tutorial/tutorial-progression.cy.js`

```javascript
describe('Tutorial - Progression System (XP/PI)', () => {
  beforeEach(() => {
    cy.resetTutorialProgress();
    cy.loginAsCradek();
    cy.startTutorial('first_time');
  });

  it('should award XP for each completed step', () => {
    cy.waitForTutorialStep(1);

    // XP should be 0 initially
    cy.get('#tutorial-xp-bar .xp-text').should('contain', 'XP: 0');

    cy.completeTutorialStep();
    cy.waitForTutorialStep(2);

    // XP should increase
    cy.get('#tutorial-xp-bar .xp-text').should('contain', 'XP: 5');

    cy.completeTutorialStep();
    cy.waitForTutorialStep(3);

    // XP should continue increasing
    cy.get('#tutorial-xp-bar .xp-text').should('contain', 'XP: 10');
  });

  it('should level up when reaching 100 XP', () => {
    // Complete steps until level up (step 13)
    for (let i = 1; i < 13; i++) {
      cy.completeTutorialStep();
      cy.wait(300);
    }

    cy.waitForTutorialStep(13);
    cy.completeTutorialStep();

    // Should show level up modal
    cy.get('#level-up-modal', { timeout: 3000 }).should('be.visible');
    cy.get('#level-up-modal').should('contain', 'NIVEAU SUPÉRIEUR');
    cy.get('#level-up-modal').should('contain', 'Niveau 2');
    cy.get('#level-up-modal').should('contain', '1 Point d\'Investissement');

    // Continue
    cy.get('.continue-button').click();
    cy.get('#level-up-modal').should('not.exist');
  });

  it('should display PI after leveling up', () => {
    // Level up
    for (let i = 1; i <= 13; i++) {
      cy.completeTutorialStep();
      cy.wait(200);
    }

    cy.get('.continue-button').click();

    // Navigate to PI intro step (34)
    for (let i = 14; i < 34; i++) {
      cy.completeTutorialStep();
      cy.wait(200);
    }

    cy.waitForTutorialStep(34);
    cy.get('#pi-display').should('contain', '1');
  });

  it('should allow investing PI in Mvt characteristic', () => {
    // Fast-forward to PI investment step (35)
    for (let i = 1; i < 35; i++) {
      cy.completeTutorialStep();
      cy.wait(100);
    }

    // Click level up modal continue if present
    cy.get('body').then($body => {
      if ($body.find('.continue-button').length) {
        cy.get('.continue-button').click();
      }
    });

    cy.waitForTutorialStep(35);

    // Investment panel should be visible
    cy.get('#investment-panel').should('be.visible');

    // Check initial Mvt value (4 for dwarves)
    cy.get('#invest-mvt-button').parent().should('contain', '4');

    // Click invest button
    cy.get('#invest-mvt-button').click();
    cy.wait(500);

    // Mvt should increase to 5
    cy.get('#invest-mvt-button').parent().should('contain', '5');

    // PI should decrease to 0
    cy.get('#pi-display').should('contain', '0');

    // Next button should be enabled
    cy.get('#tutorial-next').should('not.be.disabled');
  });

  it('should verify movement increase after PI investment', () => {
    // Fast-forward to verification step (36)
    for (let i = 1; i < 36; i++) {
      cy.completeTutorialStep();
      cy.wait(100);
    }

    cy.waitForTutorialStep(36);

    // Resource display should show 5 movements instead of 4
    cy.checkResource('mvt', '5/5');
  });

  it('should display XP bar throughout tutorial', () => {
    cy.get('#tutorial-xp-bar').should('be.visible');

    // Complete several steps
    for (let i = 0; i < 5; i++) {
      cy.completeTutorialStep();
      cy.wait(300);

      // XP bar should still be visible
      cy.get('#tutorial-xp-bar').should('be.visible');
    }
  });

  it('should show XP progress bar filling up', () => {
    cy.waitForTutorialStep(1);

    // Get initial progress bar width
    cy.get('#tutorial-xp-bar .xp-bar-fill').invoke('width').then(initialWidth => {
      cy.completeTutorialStep();
      cy.wait(500);

      // Progress bar should have increased
      cy.get('#tutorial-xp-bar .xp-bar-fill').invoke('width').should('be.gt', initialWidth);
    });
  });

  it('should explain training action gives XP', () => {
    // Fast-forward to training action step (37)
    for (let i = 1; i < 37; i++) {
      cy.completeTutorialStep();
      cy.wait(100);
    }

    cy.waitForTutorialStep(37);
    cy.verifyTooltipContains('Entraînement');
    cy.verifyTooltipContains('XP');
  });

  it('should track total XP earned by end of tutorial', () => {
    // Complete entire tutorial
    for (let i = 1; i <= 47; i++) {
      cy.completeTutorialStep();
      cy.wait(100);

      // Skip level up modal if present
      cy.get('body').then($body => {
        if ($body.find('.continue-button').length) {
          cy.get('.continue-button').click();
        }
      });
    }

    // Final XP should be around 390
    cy.get('#tutorial-xp-bar .xp-text').should('contain', '390');
  });
});
```

---

## UI/UX Enhancements

### XP Bar Design

Add to `css/tutorial/tutorial.css`:

```css
/* XP Bar */
#tutorial-xp-bar {
    position: fixed;
    top: 60px;
    right: 20px;
    background: rgba(0, 0, 0, 0.8);
    padding: 10px 15px;
    border-radius: 8px;
    min-width: 200px;
    z-index: 10001;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
}

.xp-bar-container {
    width: 100%;
    height: 20px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 5px;
}

.xp-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #4CAF50, #8BC34A);
    transition: width 0.5s ease-out;
    box-shadow: 0 0 10px rgba(76, 175, 80, 0.5);
}

.xp-text {
    color: white;
    font-size: 12px;
    text-align: center;
}

/* Level Up Modal */
#level-up-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 10002;
}

#level-up-modal .modal-content.level-up {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px;
    border-radius: 20px;
    text-align: center;
    max-width: 400px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
    animation: levelUpAppear 0.5s ease-out;
}

@keyframes levelUpAppear {
    from {
        transform: scale(0.5);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

#level-up-modal h2 {
    font-size: 32px;
    margin-bottom: 20px;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
}

.level-display {
    font-size: 48px;
    font-weight: bold;
    margin: 20px 0;
    text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.5);
}

.continue-button {
    background: white;
    color: #667eea;
    border: none;
    padding: 12px 30px;
    font-size: 16px;
    font-weight: bold;
    border-radius: 25px;
    cursor: pointer;
    margin-top: 20px;
    transition: transform 0.2s;
}

.continue-button:hover {
    transform: scale(1.05);
}

/* Investment Panel */
#investment-panel {
    background: rgba(0, 0, 0, 0.9);
    padding: 20px;
    border-radius: 10px;
    margin-top: 20px;
}

.investment-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.investment-row:last-child {
    border-bottom: none;
}

.characteristic-name {
    color: white;
    font-weight: bold;
}

.characteristic-value {
    color: #4CAF50;
    font-size: 18px;
}

#invest-mvt-button {
    background: #4CAF50;
    color: white;
    border: none;
    padding: 5px 15px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 20px;
    font-weight: bold;
}

#invest-mvt-button:hover {
    background: #45a049;
}

#invest-mvt-button:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.investment-highlight {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.7);
    }
    50% {
        box-shadow: 0 0 20px 10px rgba(76, 175, 80, 0);
    }
}
```

### Investment Panel HTML

Add to `TutorialUI.js`:

```javascript
/**
 * Show investment panel for PI investment step
 */
showInvestmentPanel(context) {
    const currentPI = context.tutorial_pi || 0;
    const player = context.player;
    const raceJson = this.getRaceData(player.race);

    const baseMvt = raceJson.mvt;
    const currentMvt = player.data.mvt;
    const investedMvt = context.mvt_investment || 0;

    const $panel = $(`
        <div id="investment-panel">
            <h3>Investissement de PI</h3>
            <p>PI disponibles: <strong>${currentPI}</strong></p>

            <div class="investment-row investment-highlight">
                <div>
                    <div class="characteristic-name">Mvt (Mouvements)</div>
                    <div class="characteristic-description">Déplacements par tour</div>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="characteristic-value">${currentMvt}</div>
                    <button id="invest-mvt-button" ${currentPI < 1 ? 'disabled' : ''}>
                        +
                    </button>
                </div>
            </div>

            <div class="investment-row">
                <div>
                    <div class="characteristic-name">PV (Points de Vie)</div>
                    <div class="characteristic-description">Points de santé</div>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="characteristic-value">${player.data.pv}</div>
                    <button disabled>+</button>
                </div>
            </div>

            <p style="margin-top: 15px; color: #888; font-size: 12px;">
                <em>Pour ce tutoriel, seul Mvt est disponible</em>
            </p>
        </div>
    `);

    $('.tutorial-tooltip .tooltip-content').append($panel);

    // Handle investment click
    $('#invest-mvt-button').on('click', () => {
        this.investPI('mvt', 1);
    });
}

/**
 * Invest PI via API
 */
async investPI(characteristic, amount) {
    try {
        const response = await this.apiCall('/api/tutorial/invest-pi.php', {
            session_id: this.currentSession,
            characteristic: characteristic,
            amount: amount
        });

        if (response.success) {
            // Update UI
            $('#invest-mvt-button').prop('disabled', true);
            $('.characteristic-value').text(response.new_value);
            $('#pi-display').text(response.new_pi);

            // Show success message
            this.tooltip.showSuccess(
                `Investissement réussi! Mvt: ${response.old_value} → ${response.new_value}`
            );

            // Enable next button
            this.navigator.enableNext();
        } else {
            this.tooltip.showError(response.error);
        }
    } catch (error) {
        console.error('Failed to invest PI:', error);
        this.tooltip.showError('Erreur lors de l\'investissement');
    }
}
```

---

## API Endpoint Addition

### New Endpoint: `api/tutorial/invest-pi.php`

```php
<?php
require_once('../../config.php');

use App\Tutorial\TutorialManager;
use Classes\Player;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$sessionId = $input['session_id'] ?? null;
$characteristic = $input['characteristic'] ?? null;
$amount = $input['amount'] ?? 0;

if (!$sessionId || !$characteristic || $amount < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

if (!isset($_SESSION['playerId'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    $player = new Player($_SESSION['playerId']);
    $manager = new TutorialManager($player);
    $manager->resumeTutorial($sessionId);

    $context = $manager->getContext();
    $oldValue = $context->getPlayer()->data->{$characteristic};

    $success = $context->investPI($characteristic, $amount);

    if ($success) {
        $newValue = $context->getPlayer()->data->{$characteristic};

        echo json_encode([
            'success' => true,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'new_pi' => $context->getTutorialPI()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Not enough PI'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
```

---

## Summary

This integration makes the tutorial significantly more engaging by:

1. ✅ **Rewarding every action with XP** - Players see immediate feedback
2. ✅ **Level up during tutorial** - Players experience progression firsthand
3. ✅ **Practice PI investment** - Learn by doing, not just reading
4. ✅ **Gain extra movement** - Tangible benefit from investment
5. ✅ **Visual feedback** - XP bar, level up animation, investment panel

### Total Tutorial Steps: 47 (was 45)
- Added step 33: Level up notification
- Added step 35: PI investment practice
- Renumbered subsequent steps

### Total XP Earned: ~390 XP
- Enough to level up at least once
- Player gains 1+ PI to invest
- Learns progression system through practice

This creates a **virtuous learning loop**:
- Do action → Get XP → See progress → Level up → Get PI → Invest PI → Get stronger → Feel accomplished

Much better than just reading about it!

---

**Next Steps**:
1. Review and approve this integration plan
2. Update main tutorial-refactoring-plan.md with these changes
3. Implement TutorialContext XP/PI methods
4. Create PIInvestmentStep class
5. Add Cypress tests for progression system
6. Design and implement XP bar UI
7. Test complete progression flow