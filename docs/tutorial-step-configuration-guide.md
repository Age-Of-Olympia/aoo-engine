# Tutorial Step Configuration Guide

**Date**: 2025-11-13
**Version**: 1.1 (with interaction modes & prerequisites)

---

## Overview

This guide explains how to configure tutorial steps with the new **interaction mode** and **prerequisite** systems.

### Key Features

1. **Interaction Modes**: Control what the player can click during each step
2. **Prerequisites**: Ensure each step has the resources it needs
3. **Resource Management**: Automatic restoration between steps
4. **Blocked Click Feedback**: Helpful messages when players click in wrong places

---

## Interaction Modes

### Available Modes

| Mode | Behavior | Use Case | Overlay |
|------|----------|----------|---------|
| **`blocking`** | Blocks ALL game interactions | Pure information/explanations | Dark, click shows help |
| **`semi-blocking`** | Blocks MOST interactions, allows specific elements | Guided actions (movement, combat) | Medium, click shows target hint |
| **`open`** | No blocking, tutorial UI visible | Free exploration (rare) | Hidden |

### Default Modes by Step Type

The system uses smart defaults based on `step_type`:

```javascript
// Automatically applied defaults
'info'           → 'blocking'
'welcome'        → 'blocking'
'dialog'         → 'blocking'
'action_intro'   → 'blocking'
'combat_intro'   → 'blocking'

'movement'       → 'semi-blocking'
'movement_limit' → 'semi-blocking'
'action'         → 'semi-blocking'
'combat'         → 'semi-blocking'

'exploration'    → 'open'
```

**You can override** any default by adding `interaction_mode` to step config.

---

## Step Configuration Examples

### Example 1: Blocking Mode (Information Step)

```javascript
{
  step_number: 1,
  step_type: 'welcome',  // Default: blocking
  title: 'Bienvenue dans Olympia!',

  config: {
    text: 'Age of Olympia est un jeu au tour par tour. Lisez les instructions attentivement.',
    tooltip_position: 'center',
    requires_validation: false,

    // Overlay blocks everything, click shows this message:
    blocked_click_message: 'Lisez les instructions et cliquez sur "Suivant" pour continuer.'
  },

  xp_reward: 5
}
```

**Behavior**:
- Dark overlay covers entire screen
- ALL clicks blocked except "Suivant" button
- Clicking overlay → shakes tooltip + shows blocked message
- Perfect for reading/theory

---

### Example 2: Semi-Blocking Mode (Movement Step)

```javascript
{
  step_number: 4,
  step_type: 'movement',  // Default: semi-blocking
  title: 'Votre premier mouvement',

  config: {
    text: 'Cliquez sur une <strong>case adjacente</strong> (en vert) pour vous déplacer.',
    target_selector: '.tile.adjacent',
    target_description: 'cliquez sur une case adjacente',  // Used in blocked message
    tooltip_position: 'bottom',
    requires_validation: true,
    validation_type: 'any_movement',

    // ONLY these elements can be clicked:
    allowed_interactions: [
      '.tile.adjacent',      // Adjacent tiles
      '.go-button'           // Alternative: movement buttons
    ],

    // Prerequisites - ensure player CAN move
    prerequisites: {
      mvt: 1,               // Need at least 1 movement point
      auto_restore: true    // Restore if missing
    },

    // After completing this step, prepare for next:
    prepare_next_step: {
      restore_mvt: 4        // Give 4 movements for next step
    },

    // Custom message when clicking blocked areas:
    blocked_click_message: 'Pour continuer, cliquez sur une case adjacente (en vert).'
  },

  xp_reward: 10
}
```

**Behavior**:
- Overlay blocks MOST interactions
- Only `.tile.adjacent` and `.go-button` are clickable (z-index boost)
- Clicking anywhere else → shakes tooltip + shows custom message
- Prerequisite ensures player has movement to complete step
- After completion, restores 4 movements for next step

---

### Example 3: Movement Depletion Step (Special Case)

```javascript
{
  step_number: 5,
  step_type: 'movement_limit',
  title: 'Mouvements limités',

  config: {
    text: '<strong>ATTENTION!</strong> En jeu réel, vous avez <strong>4 mouvements par tour</strong>. Utilisez-les tous!',
    target_selector: '#mvt-display',
    tooltip_position: 'bottom',
    requires_validation: true,
    validation_type: 'movements_depleted',  // Must use ALL movements

    allowed_interactions: [
      '.tile.adjacent'
    ],

    // Give exactly 4 movements, player must deplete them
    prerequisites: {
      mvt: 4,
      auto_restore: true
    },

    // After depleting, restore for next step
    prepare_next_step: {
      restore_mvt: 4,
      restore_actions: 2
    },

    blocked_click_message: 'Déplacez-vous 4 fois pour utiliser tous vos mouvements.'
  },

  xp_reward: 20
}
```

**Behavior**:
- Player starts with exactly 4 movements
- Must move 4 times to complete step
- Can only click on adjacent tiles
- After completion, resources are restored for next step

---

### Example 4: Action Usage Step

```javascript
{
  step_number: 9,
  step_type: 'action',
  title: 'Utilisez l\'action Fouiller',

  config: {
    text: 'Cliquez sur l\'action <strong>Fouiller</strong> pour chercher des objets.',
    target_selector: '.action[data-action="fouiller"]',
    target_description: 'cliquez sur l\'action Fouiller',
    tooltip_position: 'left',
    requires_validation: true,
    validation_type: 'action_used',
    validation_params: {
      action: 'fouiller'
    },

    allowed_interactions: [
      '.action[data-action="fouiller"]'  // Only "Fouiller" button
    ],

    prerequisites: {
      actions: 1,           // Need 1 action point
      auto_restore: true
    },

    prepare_next_step: {
      restore_actions: 2    // Restore for next step
    },

    blocked_click_message: 'Cliquez sur le bouton "Fouiller" dans le panneau d\'actions.'
  },

  xp_reward: 15
}
```

---

### Example 5: Combat Step with Enemy Spawn

```javascript
{
  step_number: 12,
  step_type: 'combat',
  title: 'Votre premier combat',

  config: {
    text: 'Attaquez l\'<strong>Âme d\'entraînement</strong> en cliquant dessus, puis sur l\'icône d\'attaque.',
    target_selector: '.enemy.tutorial',
    target_description: 'cliquez sur l\'ennemi d\'entraînement',
    tooltip_position: 'top',
    requires_validation: true,
    validation_type: 'combat_initiated',

    allowed_interactions: [
      '.enemy.tutorial',          // The tutorial enemy
      '.action[data-action="attack"]'  // Attack button
    ],

    prerequisites: {
      actions: 1,                // Need 1 action to attack
      ensure_enemy: 'tutorial_dummy',  // Spawn enemy if not present
      auto_restore: true
    },

    prepare_next_step: {
      restore_actions: 2,
      // Enemy will be removed after combat
    },

    blocked_click_message: 'Pour combattre, cliquez sur l\'Âme d\'entraînement puis sur l\'icône d\'attaque.'
  },

  xp_reward: 25
}
```

---

### Example 6: Override Default Mode

```javascript
{
  step_number: 7,
  step_type: 'action_intro',  // Default would be 'blocking'
  title: 'Points d\'Action',

  config: {
    text: 'Les points d\'action permettent d\'effectuer des actions. Regardez le panneau.',
    target_selector: '#action-display',
    tooltip_position: 'bottom',
    requires_validation: false,

    // OVERRIDE: Use semi-blocking instead of default blocking
    interaction_mode: 'semi-blocking',

    allowed_interactions: [
      '#action-display'  // Allow clicking on action display to inspect
    ],

    blocked_click_message: 'Observez le panneau d\'actions mis en évidence.'
  },

  xp_reward: 5
}
```

---

## Prerequisites System

### Available Prerequisite Options

```javascript
prerequisites: {
  // Movement points required
  mvt: 4,                    // Need at least 4 movements

  // Action points required
  actions: 2,                // Need at least 2 actions

  // Auto-restore if missing (default: false)
  auto_restore: true,        // Automatically give resources if player lacks them

  // Ensure entities exist
  ensure_enemy: 'tutorial_dummy',     // Spawn this enemy if not present
  ensure_item: 'baton_de_marche',     // Spawn this item if not present
  ensure_npc: 'gaia_tutorial'         // Ensure NPC is present
}
```

### Prepare Next Step

After a step completes, you can prepare resources for the following step:

```javascript
prepare_next_step: {
  restore_mvt: 4,              // Give 4 movements
  restore_actions: 2,          // Give 2 actions
  spawn_enemy: 'tutorial_dummy',   // Spawn enemy for next step
  spawn_item: 'baton_de_marche',   // Spawn item for next step
  remove_enemy: 'tutorial_dummy'   // Remove enemy after combat
}
```

---

## Step Coherence Rules

**CRITICAL**: Steps must be coherent with each other!

### Rule 1: Resource Chain
If step N depletes a resource, step N+1 must restore it OR not need it:

```javascript
// ✅ GOOD: Step 5 depletes, Step 6 doesn't need movement
Step 5: { validation: 'movements_depleted' }  // Player ends with 0 mvt
Step 6: { step_type: 'info' }                 // Just reading, no mvt needed

// ✅ GOOD: Step 5 depletes, Step 6 restores
Step 5: {
  validation: 'movements_depleted',
  prepare_next_step: { restore_mvt: 4 }  // Restores for Step 6
}
Step 6: { prerequisites: { mvt: 1 } }    // Has movement available

// ❌ BAD: Step 5 depletes, Step 6 needs movement but no restoration
Step 5: { validation: 'movements_depleted' }  // Player ends with 0 mvt
Step 6: { prerequisites: { mvt: 1 } }         // ERROR: No movement!
```

### Rule 2: Entity Lifecycle
If step spawns an entity, later step should remove it:

```javascript
// ✅ GOOD: Spawn enemy, use it, remove it
Step 10: { prepare_next_step: { spawn_enemy: 'tutorial_dummy' } }
Step 11: { validation: 'combat_initiated' }  // Fight the enemy
Step 12: { prepare_next_step: { remove_enemy: 'tutorial_dummy' } }

// ❌ BAD: Spawn enemy but never remove
Step 10: { prepare_next_step: { spawn_enemy: 'tutorial_dummy' } }
// ... enemy stays forever
```

### Rule 3: Sequential Logic
Steps should teach concepts in logical order:

```javascript
// ✅ GOOD: Teach unlimited movement, then limits
Step 3: 'Your first movement' (unlimited)
Step 4: 'Practice moving' (unlimited)
Step 5: 'Movement limits' (limited to 4)

// ❌ BAD: Teach limits before basics
Step 3: 'Movement limits' (limited)  // Player doesn't even know how to move yet!
Step 4: 'Your first movement'        // Too late
```

---

## Blocked Click Messages

### Message Guidelines

**Good messages**:
- ✅ Tell the player EXACTLY what to do
- ✅ Use action verbs (click, select, move, attack)
- ✅ Reference visual cues (en vert, en surbrillance)

**Examples**:
```javascript
// ✅ GOOD
blocked_click_message: 'Cliquez sur une case adjacente (en vert) pour vous déplacer.'
blocked_click_message: 'Pour continuer, cliquez sur le bouton "Fouiller" dans le panneau d\'actions.'
blocked_click_message: 'Attaquez l\'Âme d\'entraînement en cliquant dessus.'

// ❌ BAD - too vague
blocked_click_message: 'Suivez les instructions.'
blocked_click_message: 'Faites ce qui est demandé.'
blocked_click_message: 'Continuez le tutoriel.'
```

### Using target_description

The `target_description` is used to generate automatic messages:

```javascript
config: {
  target_description: 'cliquez sur une case adjacente',
  // Automatically generates: "Pour continuer, cliquez sur une case adjacente."
}
```

If you don't provide `blocked_click_message`, the system uses:
```
"Pour continuer, {target_description}."
```

---

## Complete Step Template

```javascript
{
  // === BASIC INFO ===
  step_number: 0,
  step_type: 'movement',       // See type defaults above
  title: 'Step Title',

  // === CONFIGURATION ===
  config: {
    // Text content
    text: 'Instructions with <strong>HTML</strong> and {PLACEHOLDERS}.',

    // UI targeting
    target_selector: '.element-to-highlight',
    target_description: 'cliquez sur l\'élément',  // For auto-messages
    tooltip_position: 'bottom',  // top, bottom, left, right, center

    // Validation
    requires_validation: true,
    validation_type: 'movement',  // Or action_used, combat_initiated, etc.
    validation_params: { ... },    // Type-specific params
    validation_hint: 'Hint shown if validation fails',

    // === INTERACTION MODE (optional override) ===
    interaction_mode: 'semi-blocking',  // blocking, semi-blocking, or open

    // === ALLOWED INTERACTIONS (semi-blocking only) ===
    allowed_interactions: [
      '.clickable-element-1',
      '.clickable-element-2'
    ],

    // === PREREQUISITES ===
    prerequisites: {
      mvt: 1,
      actions: 1,
      auto_restore: true,
      ensure_enemy: 'tutorial_dummy',
      ensure_item: 'item_name'
    },

    // === BLOCKED CLICK MESSAGE ===
    blocked_click_message: 'Custom message when clicking blocked areas.',

    // === CONTEXT CHANGES (during step) ===
    context_changes: {
      unlimited_mvt: false,
      set_mvt_limit: 4
    },

    // === PREPARE NEXT STEP (after completion) ===
    prepare_next_step: {
      restore_mvt: 4,
      restore_actions: 2,
      spawn_enemy: 'tutorial_dummy'
    }
  },

  // === XP REWARD ===
  xp_reward: 10
}
```

---

## Testing Your Steps

### Checklist

- [ ] Can the player complete this step with the given prerequisites?
- [ ] Does this step restore resources for the next step?
- [ ] Is the interaction mode appropriate for this step type?
- [ ] Are `allowed_interactions` specific enough (not too broad)?
- [ ] Is the blocked click message helpful and actionable?
- [ ] Does the tooltip highlight the right element?
- [ ] Is the XP reward appropriate for difficulty?

### Testing Flow

1. **Test the step alone**: Can it be completed?
2. **Test the sequence**: Step N → Step N+1 → Step N+2
3. **Test clicking wrong things**: Does blocked message help?
4. **Test resource depletion**: What happens when resources run out?

---

## Common Patterns

### Pattern 1: Info → Practice → Validation

```javascript
// Step N: Explain concept (blocking)
{ step_type: 'info', text: 'Le mouvement fonctionne comme ceci...', interaction_mode: 'blocking' }

// Step N+1: Practice freely (semi-blocking)
{ step_type: 'movement', prerequisites: { mvt: 4 }, allowed_interactions: ['.tile'] }

// Step N+2: Validate understanding (semi-blocking, strict)
{ step_type: 'movement_limit', validation: 'movements_depleted', prerequisites: { mvt: 4 } }
```

### Pattern 2: Spawn → Use → Cleanup

```javascript
// Step N: Prepare enemy
{ prepare_next_step: { spawn_enemy: 'tutorial_dummy' } }

// Step N+1: Combat intro
{ step_type: 'combat_intro', interaction_mode: 'blocking' }

// Step N+2: Fight enemy
{ step_type: 'combat', prerequisites: { ensure_enemy: 'tutorial_dummy' } }

// Step N+3: Cleanup
{ prepare_next_step: { remove_enemy: 'tutorial_dummy' } }
```

---

## Summary

✅ **Use defaults**: Let step_type determine interaction mode automatically
✅ **Override when needed**: Explicit `interaction_mode` for special cases
✅ **Think sequentially**: Ensure step N prepares for step N+1
✅ **Test blocked clicks**: Make sure messages are helpful
✅ **Validate coherence**: Resources must flow logically through steps

Happy tutoring! 🎓
