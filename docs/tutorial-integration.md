# Tutorial System Integration Guide

## Overview

The tutorial system is now **fully functional** and ready to integrate into Age of Olympia. This guide shows how to add it to the game.

## What's Been Built

### Backend (✅ Complete)
- ✅ Database tables (`tutorial_progress`, `tutorial_configurations`, `tutorial_dialogs`)
- ✅ PHP classes (`TutorialManager`, `TutorialContext`, `TutorialStepFactory`, etc.)
- ✅ API endpoints (`start.php`, `advance.php`, `get-step.php`, `resume.php`)
- ✅ 14 tutorial steps loaded in database (165 XP total)
- ✅ 3 NPC dialogs (Gaïa)

### Frontend (✅ Complete)
- ✅ `TutorialUI.js` - Main controller
- ✅ `TutorialTooltip.js` - Smart tooltips
- ✅ `TutorialHighlighter.js` - Element highlighting
- ✅ `TutorialInit.js` - Initialization & wiring
- ✅ `tutorial.css` - Complete styling

## Quick Start

### 1. Test the Tutorial

Visit: `http://localhost:9000/test_tutorial_ui.php`

This page lets you:
- Start a new tutorial
- Resume tutorial
- Test API endpoints
- See the complete UI in action

### 2. Add to Main Game (index.php)

Add these includes to `index.php` (or your main game page):

```php
<!-- Modal System (required for tutorial prompts) -->
<link href="css/modal.css?v=20251112" rel="stylesheet">
<script src="js/modal.js?v=20251112"></script>

<!-- Tutorial System CSS -->
<link href="css/tutorial/tutorial.css?v=20251112" rel="stylesheet">

<!-- Tutorial System JS -->
<script src="js/tutorial/TutorialUI.js?v=20251112"></script>
<script src="js/tutorial/TutorialTooltip.js?v=20251112"></script>
<script src="js/tutorial/TutorialHighlighter.js?v=20251112"></script>
<script src="js/tutorial/TutorialInit.js?v=20251112"></script>
```

### 3. Add Tutorial Button

Add a button to start/resume tutorial:

```html
<button onclick="window.startTutorial('first_time')" class="tutorial-button">
    🎯 Commencer le tutoriel
</button>
```

Or check for active tutorial automatically:

```javascript
$(document).ready(function() {
    // TutorialInit.js will automatically check and prompt
    // No additional code needed!
});
```

### 4. URL Parameters

Start tutorial via URL:
- `?tutorial=start` - Start new tutorial
- `?tutorial=resume` - Resume existing tutorial

Example: `http://localhost:9000/index.php?tutorial=start`

## Feature Flags

Tutorial is currently enabled for players 1, 2, 3 (Cradek, Dorna, Thyrias).

To enable globally, add to `config/constants.php`:

```php
// Enable tutorial for all players
define('TUTORIAL_V2_ENABLED', true);
```

Or enable for specific test players:

```php
// Only enable for these player IDs
define('TUTORIAL_V2_TEST_PLAYERS', [1, 2, 3, 4, 5]);
```

## Current Tutorial Steps (v1.0.0)

| Step | Title | Type | XP |
|------|-------|------|-----|
| 0 | Bienvenue! | dialog | 5 |
| 1 | Un jeu au tour par tour | welcome | 5 |
| 2 | Vous voici! | info | 5 |
| 3 | La carte en damier | info | 5 |
| 4 | Votre premier mouvement | movement | 10 |
| 5 | Mouvements limités | movement_limit | 20 |
| 6 | Le système de tours | info | 5 |
| 7 | Points d'Action | action_intro | 5 |
| 8 | Actions disponibles | info | 5 |
| 9 | Pratique : Fouiller | action | 15 |
| 10 | Apprendre le combat | dialog | 5 |
| 11 | Le Combat | combat_intro | 5 |
| 12 | Attaquez! | combat | 25 |
| 13 | Tutoriel terminé! | dialog | 50 |

**Total: 165 XP** (enough to reach level 2)

## API Endpoints

### Start Tutorial
```javascript
POST /api/tutorial/start.php
{
  "mode": "first_time",  // or "replay", "practice"
  "version": "1.0.0"
}
```

### Advance Step
```javascript
POST /api/tutorial/advance.php
{
  "session_id": "tut_...",
  "validation_data": { /* step-specific validation */ }
}
```

### Get Current Step
```javascript
GET /api/tutorial/get-step.php?session_id=tut_...
```

### Resume Tutorial
```javascript
GET /api/tutorial/resume.php
// Returns active tutorial session if exists
```

## JavaScript API

### Start Tutorial
```javascript
window.startTutorial('first_time');
```

### Resume Tutorial
```javascript
window.resumeTutorial();
```

### Initialize System
```javascript
window.initTutorial();
```

## Customization

### Add New Tutorial Steps

1. Edit `scripts/tutorial/populate_tutorial_steps.php`
2. Add your step to the `$steps` array
3. Run: `php scripts/tutorial/populate_tutorial_steps.php`

Example step:

```php
[
    'step_number' => 14,
    'step_type' => 'info',
    'title' => 'New Step',
    'config' => [
        'text' => 'This is a new tutorial step!',
        'target_selector' => '#some-element',
        'tooltip_position' => 'bottom',
        'requires_validation' => false
    ],
    'xp_reward' => 10
]
```

### Add New Step Types

1. Create step class in `src/Tutorial/Steps/YourCategory/YourStep.php`
2. Extend `AbstractStep`
3. Register in `TutorialStepFactory`:

```php
private static $stepTypeMap = [
    // ... existing mappings
    'your_type' => YourStep::class,
];
```

### Modify Dialogs

Dialogs are in the **database** (`tutorial_dialogs` table).

To update:

```sql
UPDATE tutorial_dialogs
SET dialog_data = '{"id": "gaia_welcome", ...}'
WHERE dialog_id = 'gaia_welcome';
```

Or use the DialogService:

```php
$dialogService = new DialogService(true);
$dialogService->saveDialog('gaia_welcome', 'Gaïa', $dialogData);
```

## CSS Customization

Edit `css/tutorial/tutorial.css` to change:
- Colors
- Animations
- Positioning
- Responsive breakpoints

## Troubleshooting

### Tutorial Won't Start

1. Check player is in test players list (IDs 1, 2, 3)
2. Check console for errors
3. Test API: `curl http://localhost:9000/api/tutorial/resume.php`

### Steps Not Loading

1. Verify steps in database: `SELECT * FROM tutorial_configurations`
2. Check `scripts/tutorial/populate_tutorial_steps.php` ran successfully
3. Check PHP error logs

### Validation Not Working

1. Implement validation in step class `validate()` method
2. Ensure `requires_validation` is true in step config
3. Pass correct `validation_data` when calling `next()`

## Testing

### Run Tests

```bash
# Test dialog service
php scripts/tutorial/test_dialog_service.php

# Test tutorial classes
php scripts/tutorial/test_tutorial_classes.php

# Test complete flow
php scripts/tutorial/test_tutorial_flow.php
```

### Test in Browser

1. Visit `test_tutorial_ui.php`
2. Click "Start Tutorial"
3. Test all UI interactions
4. Check console for errors

## Next Steps - Expanding the Tutorial

The current 14 steps are just the foundation. The plan calls for **47 steps total**. See `docs/tutorial-refactoring-plan.md` for the complete roadmap.

### Priority Additions

1. **More Combat Steps** (Steps 14-22)
   - Dice rolls explanation
   - Damage calculation
   - Death & resurrection

2. **Resource Management** (Steps 23-27)
   - PV (health)
   - PM (magic)
   - Regeneration

3. **Inventory & Equipment** (Steps 28-30)
   - Inventory management
   - Equipping items
   - Item effects

4. **Social Features** (Steps 31-33)
   - Missives (messages)
   - Forum
   - Factions

5. **Progression System** (Steps 34-37)
   - XP system
   - PI investment
   - Training
   - **Practice investing PI to gain extra movement!**

See `docs/tutorial-xp-pi-integration.md` for the full XP/PI integration plan.

## Database Schema

### tutorial_progress
Tracks player progress through tutorial sessions.

### tutorial_configurations
Stores all tutorial step definitions (JSON config).

### tutorial_dialogs
Stores NPC dialog trees (JSON data).

## File Structure

```
api/tutorial/
  ├── start.php
  ├── advance.php
  ├── get-step.php
  └── resume.php

js/tutorial/
  ├── TutorialUI.js
  ├── TutorialTooltip.js
  ├── TutorialHighlighter.js
  └── TutorialInit.js

css/tutorial/
  └── tutorial.css

src/Tutorial/
  ├── TutorialManager.php
  ├── TutorialContext.php
  ├── TutorialStepFactory.php
  ├── TutorialFeatureFlag.php
  └── Steps/
      ├── AbstractStep.php
      ├── GenericStep.php
      ├── DialogStep.php
      └── Movement/
          └── MovementStep.php

scripts/tutorial/
  ├── populate_tutorial_steps.php
  ├── test_dialog_service.php
  ├── test_tutorial_classes.php
  └── test_tutorial_flow.php
```

## Support

Questions? Check:
- `docs/tutorial-refactoring-plan.md` - Complete architecture
- `docs/tutorial-phase0-first-steps.md` - Implementation guide
- `docs/tutorial-xp-pi-integration.md` - XP/PI system details

---

**Status**: ✅ Phase 0 + Phase 1 + Phase 2 Complete

**Ready for Production**: Yes (with feature flag)

**Last Updated**: 2025-11-12
