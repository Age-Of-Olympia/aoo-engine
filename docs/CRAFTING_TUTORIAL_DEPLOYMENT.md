# Crafting Tutorial (2.0.0-craft) - Deployment Guide

## Overview
The crafting tutorial is a complete learning experience teaching players to:
1. Gather wood from trees
2. Gather 2 stones from rocks  
3. Navigate inventory and crafting interface
4. Craft a pioche (pickaxe)

**Version**: 2.0.0-craft  
**Steps**: 23  
**XP Reward**: 145 total XP  
**Status**: Production-ready

## Files Involved

### 1. Database Migrations
- **Core Steps Migration**: `src/Migrations/Version20260102000000_AddCraftingTutorial.php`
  - Creates 23 tutorial step records
  - Adds tutorial catalog entry
  - Idempotent (safe to run multiple times)

### 2. Code Modifications
- **CraftView.php** (`src/View/Inventory/CraftView.php`)
  - Added `data-item-name` attribute to craft buttons (line 369)
  - Added tutorial notification before page reload (line 406-408)

- **TutorialUI.js** (`js/tutorial/TutorialUI.js`)  
  - Fixed animation queue for tooltip transitions (TutorialTooltip.js)
  - Fixed craft completion redirect (TutorialUI.js line 1720)
  - Added tutorial notification integration for crafting

- **TutorialTooltip.js** (`js/tutorial/TutorialTooltip.js`)
  - Added `.stop(true, false)` to clear animation queue (line 166)

- **TutorialStepValidationService.php** (`src/Service/TutorialStepValidationService.php`)
  - Updated version regex to accept X.Y.Z-suffix format (line 119)

- **TutorialContext.php** (`src/Tutorial/TutorialContext.php`)
  - Fixed PA restoration to persist to database using `putBonus()` (line 333)

### 3. Configuration
- **CraftView.php modification** adds `data-item-name` attribute to enable reliable craft button targeting
- Tutorial step configurations (UI, validation, interactions, prerequisites) are stored in database tables:
  - `tutorial_step_ui`
  - `tutorial_step_validation`
  - `tutorial_step_interactions`
  - `tutorial_step_prerequisites`

## Deployment Steps

### Step 1: Apply Database Migration
```bash
# From project root
php vendor/bin/doctrine-migrations migrate

# Or manually run the migration
mysql -h localhost -u user -p database < src/Migrations/Version20260102000000_AddCraftingTutorial.php
```

### Step 2: Deploy Code Changes
These files have been modified and must be deployed:
- `src/View/Inventory/CraftView.php` - Added data-item-name attribute and tutorial notification
- `src/Tutorial/TutorialContext.php` - Fixed PA restoration persistence
- `src/Service/TutorialStepValidationService.php` - Updated version validation regex
- `js/tutorial/TutorialTooltip.js` - Fixed animation queue handling
- `js/tutorial/TutorialUI.js` - Fixed craft completion redirect
- `Classes/Ui.php` - Cache version bumped to `20260102o`

### Step 3: Import Detailed Configurations (Optional but Recommended)
The migration handles core step data only. For complete functionality including tooltips and validations, import the detailed configurations:

```bash
# Generate SQL export from your working environment
./scripts/tutorial/export_craft_tutorial.sh > craft_tutorial_complete.sql

# Import to production database
mysql -h localhost -u user -p database < craft_tutorial_complete.sql
```

Or use the tutorial admin interface to manually configure:
- Tooltip positioning and selectors
- Validation types and hints
- Interaction modes and allowed elements
- Prerequisites and PA/MVT restoration

## Detailed Configuration

### Tutorial Steps Configuration

The following tables contain the detailed step configurations that were built during development:

#### tutorial_step_ui
- **target_selector**: CSS selector for tooltip target (e.g., `input[data-item-name="pioche"]`)
- **tooltip_position**: Where tooltip appears (top, bottom, left, right, center, center-top, center-bottom)
- **interaction_mode**: How player interacts (blocking, semi-blocking, open)
- **show_delay**: Delay before showing tooltip (ms)
- **auto_advance_delay**: Delay before auto-advancing (ms)

#### tutorial_step_validation
- **requires_validation**: If true, player must complete action before advancing
- **validation_type**: Type of validation (adjacent_to_position, any_movement, action_used, ui_interaction, ui_panel_opened, etc.)
- **validation_hint**: Hint shown to player if validation fails
- **target_x, target_y**: Target coordinates for movement validation
- **panel_id**: UI panel to detect (for ui_panel_opened validation)

#### tutorial_step_interactions
- **selector**: CSS selector for allowed interactive elements
- **description**: What player can do with this element

#### tutorial_step_prerequisites
- **mvt_required**: Movement points needed for this step
- **pa_required**: Action points needed for this step
- **auto_restore**: Automatically restore resources if insufficient (1 = yes, 0 = no)

### Key Selectors and Validations

| Step | Target | Validation |
|------|--------|-----------|
| craft_walk_to_tree | `.case[data-coords="0,1"]` | adjacent_to_position |
| craft_observe_tree | `#current-player-avatar` | ui_panel_opened |
| craft_fouiller_tree | `.action[data-action="fouiller"]` | action_used |
| craft_walk_to_rock | `.case[data-coords="2,0"]` | adjacent_to_position |
| craft_observe_rock | `#current-player-avatar` | ui_panel_opened |
| craft_fouiller_rock | `.action[data-action="fouiller"]` | action_used |
| craft_observe_rock_2 | `#current-player-avatar` | ui_panel_opened |
| craft_fouiller_rock_2 | `.action[data-action="fouiller"]` | action_used |
| craft_click_ingredient | `.item-case[data-name="Pierre"]` | ui_interaction |
| craft_do_craft | `input[data-item-name="pioche"]` | ui_interaction |

## Testing

### Test in Development
```bash
# Access test database
mysql -h mariadb-aoo4 -u root -ppasswordRoot aoo4_test

# Start tutorial as test player
SELECT * FROM tutorial_progress WHERE tutorial_version = '2.0.0-craft';

# Verify all steps exist
SELECT COUNT(*) FROM tutorial_steps WHERE version = '2.0.0-craft';
# Expected: 23
```

### Verify Deployment
1. Clear browser cache (hard refresh: Ctrl+F5)
2. Start tutorial on new player
3. Complete wood gathering step
4. Complete both stone gathering steps
5. Navigate to artisanat and craft pioche
6. Verify completion and return to game

## Troubleshooting

### Issue: Tooltip appears but points to wrong element
**Solution**: Verify the `target_selector` in `tutorial_step_ui` table matches the actual element

### Issue: Player can't gather resources (No AP error)
**Solution**: Verify `auto_restore = 1` in `tutorial_step_prerequisites` for gather steps

### Issue: Craft button click doesn't advance tutorial
**Solution**: Ensure `data-item-name="pioche"` attribute exists on craft button (CraftView.php line 369)

### Issue: Tutorial loops on same step
**Solution**: Check `validation_type` is correct and `requires_validation = 1` for steps needing completion

## Maintenance

### Updating Tutorial Content
To modify tutorial text, titles, or XP rewards:
1. Use tutorial admin interface OR
2. Directly update `tutorial_steps` table:
   ```sql
   UPDATE tutorial_steps SET title = 'New Title' WHERE step_id = 'craft_welcome';
   ```

### Disabling Tutorial
```sql
UPDATE tutorial_catalog SET is_active = 0 WHERE version = '2.0.0-craft';
```

### Exporting for Backup
```bash
./scripts/tutorial/export_craft_tutorial.sh > backup_$(date +%Y%m%d).sql
```

## Related Documentation
- See `CLAUDE.md` for detailed tutorial system architecture
- See `docs/cypress-testing-guide.md` for tutorial testing with Cypress
- See `tutorials.php` for tutorial access point in UI

## Contact & Support
For issues or improvements to the crafting tutorial, refer to the tutorial development notes in the project wiki.
