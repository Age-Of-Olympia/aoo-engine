# Tutorial System Overview

This document provides a comprehensive overview of the Age of Olympia tutorial system for developers.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Database Schema](#database-schema)
3. [Backend Components](#backend-components)
4. [Frontend Components](#frontend-components)
5. [Step Types and Validation](#step-types-and-validation)
6. [Admin Panel](#admin-panel)
7. [Race-Adaptive Features](#race-adaptive-features)
8. [Common Tasks](#common-tasks)

---

## Architecture Overview

The tutorial system guides new players through game mechanics using a step-by-step approach with tooltips, highlights, and validation.

### Key Concepts

| Concept | Description |
|---------|-------------|
| **Step** | A single instruction/task in the tutorial (e.g., "Move to a tile") |
| **Session** | A player's tutorial progress, tracked in `tutorial_progress` |
| **Tutorial Player** | A temporary character created for the tutorial on an isolated map |
| **Validation** | Checks if the player completed the required action |
| **Interaction Mode** | Controls what the player can click (`blocking`, `semi-blocking`, `open`) |

### Flow Overview

```
Player clicks "Start Tutorial"
       ↓
API creates tutorial session + tutorial player
       ↓
Player is switched to tutorial player (isolated map)
       ↓
Frontend loads first step → shows tooltip/highlight
       ↓
Player performs action → Backend validates
       ↓
If valid → advance to next step, award XP
       ↓
Repeat until completion or cancel
       ↓
Player switched back to main account
```

---

## Database Schema

### Core Tables

```
tutorial_steps              # Step definitions (what to show/do)
tutorial_step_ui            # UI config (tooltip position, selectors)
tutorial_step_validation    # Validation rules (what counts as "done")
tutorial_step_prerequisites # Resource requirements (MVT, PA needed)
tutorial_step_features      # Special features (celebration, rewards)
tutorial_step_highlights    # Additional elements to highlight (1:N)
tutorial_step_interactions  # Allowed clicks in semi-blocking mode (1:N)
tutorial_step_context_changes    # State changes on completion (1:N)
tutorial_step_next_preparation   # Setup for next step (1:N)

tutorial_progress           # Player session tracking
tutorial_players            # Temporary tutorial characters
tutorial_enemies            # Spawned enemies for combat training
tutorial_dialogs            # Dialog configurations
```

### Key Relationships

```
tutorial_steps (1) ←→ (1) tutorial_step_ui
tutorial_steps (1) ←→ (1) tutorial_step_validation
tutorial_steps (1) ←→ (1) tutorial_step_prerequisites
tutorial_steps (1) ←→ (1) tutorial_step_features
tutorial_steps (1) ←→ (N) tutorial_step_highlights
tutorial_steps (1) ←→ (N) tutorial_step_interactions
tutorial_steps (1) ←→ (N) tutorial_step_context_changes
tutorial_steps (1) ←→ (N) tutorial_step_next_preparation
```

### Example: Get Complete Step Configuration

```sql
SELECT s.*, ui.*, v.*, p.*, f.*
FROM tutorial_steps s
LEFT JOIN tutorial_step_ui ui ON s.id = ui.step_id
LEFT JOIN tutorial_step_validation v ON s.id = v.step_id
LEFT JOIN tutorial_step_prerequisites p ON s.id = p.step_id
LEFT JOIN tutorial_step_features f ON s.id = f.step_id
WHERE s.step_id = 'first_move' AND s.version = '1.0.0';
```

---

## Backend Components

### Directory Structure

```
src/Tutorial/
├── TutorialManager.php         # Main orchestrator
├── TutorialContext.php         # Session state holder
├── TutorialHelper.php          # Utility functions
├── TutorialStepRepository.php  # Database access
├── TutorialFeatureFlag.php     # Feature toggles
├── TutorialMapInstance.php     # Map setup
└── Steps/
    ├── AbstractStep.php        # Base class for all steps
    ├── InfoStep.php            # Information-only steps
    ├── Movement/
    │   └── MovementStep.php    # Movement validation
    ├── ActionStep.php          # Action usage validation
    ├── UIInteractionStep.php   # UI interaction validation
    └── CombatStep.php          # Combat validation
```

### Key Classes

#### TutorialManager

Main entry point for tutorial operations:

```php
$manager = new TutorialManager($context);
$manager->startTutorial();           // Begin tutorial
$manager->advanceStep();             // Move to next step
$manager->validateCurrentStep();     // Check if current step is done
$manager->completeTutorial();        // End tutorial successfully
```

#### TutorialContext

Holds session state:

```php
$context->getPlayer();               // Get tutorial player
$context->getCurrentStepId();        // Current step identifier
$context->getTutorialXP();           // Accumulated XP
$context->setContextValue($key, $v); // Store state
```

#### TutorialHelper

Utility functions:

```php
TutorialHelper::getActivePlayerId();     // Tutorial or main player ID
TutorialHelper::isTutorialActive();      // Is tutorial in progress?
TutorialHelper::startTutorialMode();     // Switch to tutorial player
TutorialHelper::exitTutorialMode();      // Switch back to main player
```

### API Endpoints

```
POST /api/tutorial/start.php       # Start tutorial
POST /api/tutorial/advance.php     # Advance to next step
GET  /api/tutorial/get-step.php    # Get step configuration
POST /api/tutorial/validate.php    # Validate step completion
POST /api/tutorial/cancel.php      # Cancel tutorial
POST /api/tutorial/complete.php    # Complete tutorial

GET  /api/races/get.php            # Get race data (public)
```

---

## Frontend Components

### JavaScript Files

```
js/tutorial/
├── TutorialUI.js              # Main controller
├── TutorialTooltip.js         # Tooltip display and positioning
├── TutorialHighlighter.js     # Element highlighting with pulse
├── TutorialPositionManager.js # Position calculations
├── TutorialGameIntegration.js # Game event hooks
└── TutorialInit.js            # Initialization
```

### CSS

```
css/tutorial/tutorial.css      # All tutorial styles
```

### Key Frontend Methods

```javascript
// TutorialUI
tutorialUI.start();                    // Begin tutorial
tutorialUI.renderStep(stepData);       // Display step
tutorialUI.validateStep();             // Check completion
tutorialUI.advanceToNextStep();        // Move forward

// TutorialTooltip
tooltip.show(title, text, selector, position);
tooltip.hide();

// TutorialHighlighter
highlighter.highlight(selector);
highlighter.clear();
```

---

## Step Types and Validation

### Available Step Types

| Type | Purpose | Validation |
|------|---------|------------|
| `info` | Display information | None (click "Next") |
| `welcome` | Welcome message | None |
| `dialog` | NPC dialog | None |
| `movement` | Player movement | Position or movement count |
| `movement_limit` | Exhaust movements | All MVT used |
| `action` | Use an action | Action executed |
| `action_intro` | Explain actions | None |
| `ui_interaction` | Click UI element | Element clicked |
| `combat` | Attack enemy | Attack performed |
| `combat_intro` | Explain combat | None |
| `exploration` | Free exploration | None |

### Validation Types

| Type | Description | Parameters |
|------|-------------|------------|
| `any_movement` | Player moved at all | None |
| `movements_depleted` | All MVT used | None |
| `specific_count` | Moved X times | `movement_count` |
| `position` | At exact coordinates | `target_x`, `target_y` |
| `adjacent_to_position` | Next to coordinates | `target_x`, `target_y` |
| `action_used` | Used specific action | `action_name` |
| `ui_panel_opened` | Panel is visible | `panel_id` |
| `ui_interaction` | Element clicked | `element_clicked` |

### Interaction Modes

| Mode | Overlay | Clicks Allowed |
|------|---------|----------------|
| `blocking` | Dark | Only tutorial controls |
| `semi-blocking` | Medium | Specific elements (defined in `tutorial_step_interactions`) |
| `open` | None | Everything |

---

## Admin Panel

### Access

```
/admin/tutorial-step-editor.php
```

### Features

- Create/edit/delete tutorial steps
- Configure validation rules
- Set prerequisites (MVT, PA requirements)
- Preview tooltips
- Import/export step configurations

### Key Fields

| Field | Table | Description |
|-------|-------|-------------|
| `tooltip_position` | `tutorial_step_ui` | `top`, `bottom`, `left`, `right`, `center`, `center-top`, `center-bottom` |
| `mvt_required` | `tutorial_step_prerequisites` | Number or `-1` for race max |
| `interaction_mode` | `tutorial_step_ui` | `blocking`, `semi-blocking`, `open` |
| `validation_type` | `tutorial_step_validation` | See validation types above |

---

## Race-Adaptive Features

### Dynamic Movement Points

The tutorial adapts to the player's race:

| Race | Max MVT |
|------|---------|
| Nain | 4 |
| Elfe | 5 |
| Homme-Sauvage | 6 |

### Using `{max_mvt}` Placeholder

In step text, use `{max_mvt}` to display the race-specific value:

```
"Vous avez {max_mvt} mouvements par tour."
```

Renders as:
- Nain: "Vous avez 4 mouvements par tour."
- Elfe: "Vous avez 5 mouvements par tour."

### Using `-1` for Race Max

In `tutorial_step_prerequisites.mvt_required`, use `-1` to mean "use race max":

```sql
INSERT INTO tutorial_step_prerequisites (step_id, mvt_required)
VALUES (123, -1);  -- Will give 4 to Nain, 5 to Elfe, etc.
```

### Race Data API

```bash
curl "http://localhost/api/races/get.php?name=nain"
# {"success":true,"race":{"name":"Nain","mvt":4,"pv":50,"pa":2,"bgColor":"#FF0000"}}
```

---

## Common Tasks

### Adding a New Tutorial Step

1. **Add to database** via admin panel or SQL:

```sql
INSERT INTO tutorial_steps (version, step_id, next_step, step_number, step_type, title, text, xp_reward, is_active)
VALUES ('1.0.0', 'my_new_step', 'next_step_id', 15.0, 'info', 'My Title', 'My text here', 5, 1);

-- Get the new step's ID
SET @step_id = LAST_INSERT_ID();

-- Add UI config
INSERT INTO tutorial_step_ui (step_id, tooltip_position, interaction_mode)
VALUES (@step_id, 'center', 'blocking');

-- Add validation if needed
INSERT INTO tutorial_step_validation (step_id, requires_validation, validation_type)
VALUES (@step_id, 0, NULL);
```

2. **Update the previous step's `next_step`** to point to your new step.

### Testing a Specific Step

1. Reset the test database
2. Use admin panel to deactivate steps before the one you want to test
3. Or directly update `tutorial_progress.current_step` in the database

### Debugging Step Validation

Check the API response:

```bash
curl -X POST "http://localhost/api/tutorial/validate.php" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d "step_id=my_step"
```

### Player Isolation

Tutorial players exist on an isolated map (`plan='tutorial'`) with `player_visibility: false`. This means:

- Other players are not visible
- Other players don't block movement
- Resources are shared but enemies are per-session

---

## Related Documentation

- [Cypress Testing Guide](cypress-testing-guide.md) - How to run E2E tests
- [Step Configuration Guide](tutorial-step-configuration-guide.md) - Detailed step options
- [CLAUDE.md](../CLAUDE.md) - Project overview and tutorial section

---

## Quick Reference

### File Locations

| Component | Location |
|-----------|----------|
| Backend classes | `src/Tutorial/` |
| Step classes | `src/Tutorial/Steps/` |
| API endpoints | `api/tutorial/` |
| JavaScript | `js/tutorial/` |
| CSS | `css/tutorial/tutorial.css` |
| Admin panel | `admin/tutorial-step-editor.php` |
| Test files | `cypress/e2e/tutorial-*.cy.js` |

### Common Commands

```bash
# Run tutorial E2E test
/var/www/html/scripts/testing/reset_test_database.sh && \
CYPRESS_CONTAINER=true xvfb-run --auto-servernum npx cypress run \
  --spec "cypress/e2e/tutorial-production-ready.cy.js" \
  --browser electron

# Check step in database
mysql -h mariadb-aoo4 -u root -ppasswordRoot aoo4 \
  --default-character-set=utf8mb4 \
  -e "SELECT * FROM tutorial_steps WHERE step_id = 'first_move';"

# Get race data
curl "http://localhost/api/races/get.php?name=elfe"
```
