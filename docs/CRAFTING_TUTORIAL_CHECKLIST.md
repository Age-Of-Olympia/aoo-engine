# Crafting Tutorial (2.0.0-craft) - Deployment Checklist

## Pre-Production Review Checklist

- [ ] **Database Migration Created**
  - [ ] `src/Migrations/Version20260102000000_AddCraftingTutorial.php` exists
  - [ ] Creates 23 tutorial steps
  - [ ] Adds tutorial_catalog entry
  - [ ] Migration is idempotent

- [ ] **Code Changes Applied**
  - [ ] `src/View/Inventory/CraftView.php` - data-item-name attribute added
  - [ ] `src/Tutorial/TutorialContext.php` - PA restoration uses putBonus()
  - [ ] `src/Service/TutorialStepValidationService.php` - version validation regex updated
  - [ ] `js/tutorial/TutorialTooltip.js` - animation queue fix applied
  - [ ] `js/tutorial/TutorialUI.js` - craft completion redirect fixed
  - [ ] `Classes/Ui.php` - cache version updated

- [ ] **Configuration Exported**
  - [ ] `scripts/tutorial/export_craft_tutorial.sh` created and executable
  - [ ] Export script runs without errors
  - [ ] Tutorial configurations can be imported to new environment

- [ ] **Documentation Complete**
  - [ ] `docs/CRAFTING_TUTORIAL_DEPLOYMENT.md` created
  - [ ] `docs/CRAFTING_TUTORIAL_CHECKLIST.md` (this file) created
  - [ ] All code changes documented with file paths and line numbers

## Production Deployment Checklist

### Phase 1: Backup & Preparation
- [ ] Backup current production database
- [ ] Backup current codebase
- [ ] Test migration in staging environment first
- [ ] Test export/import of configurations in staging

### Phase 2: Database Migration
- [ ] Run Doctrine migration: `php vendor/bin/doctrine-migrations migrate`
- [ ] Verify 23 tutorial steps created in database
- [ ] Verify tutorial_catalog entry for 2.0.0-craft exists
- [ ] Verify tutorial step configurations imported if using export

### Phase 3: Code Deployment  
- [ ] Deploy all modified PHP files
- [ ] Deploy all modified JavaScript files
- [ ] Clear CDN/HTTP caches
- [ ] Verify cache version bumped in Ui.php

### Phase 4: Verification
- [ ] Create test account
- [ ] Start crafting tutorial on test account
- [ ] Complete full tutorial flow:
  - [ ] Wood gathering (walk, observe, fouiller)
  - [ ] First stone gathering
  - [ ] Second stone gathering
  - [ ] Inventory navigation
  - [ ] Artisanat interface
  - [ ] Craft pioche
  - [ ] Return to game
- [ ] Verify tooltips point to correct elements
- [ ] Verify PA/MVT restoration works
- [ ] Verify tutorial completion provides XP
- [ ] Test with multiple browser types

### Phase 5: Rollback Plan
If issues occur:
- [ ] Restore database from backup
- [ ] Restore code from backup
- [ ] Clear caches again
- [ ] Revert cache version in Ui.php

## Changes Summary

### Files Modified (6 total)

1. **src/View/Inventory/CraftView.php**
   - Line 369: Added `data-item-name` attribute to craft button
   - Line 406-408: Added tutorial notification before page reload

2. **src/Tutorial/TutorialContext.php**
   - Line 333: Changed PA restoration to use `putBonus(['a' => $bonusNeeded])`

3. **src/Service/TutorialStepValidationService.php**
   - Line 119: Updated version regex to accept X.Y.Z-suffix format
   - Line 120: Updated error message to reflect new format

4. **js/tutorial/TutorialTooltip.js**
   - Line 166: Added `.stop(true, false)` to clear animation queue

5. **js/tutorial/TutorialUI.js**
   - Line 1720: Changed `location.reload()` to `location.href = 'index.php'`

6. **Classes/Ui.php**
   - Line 50: Cache version bumped from 20260102n to 20260102o

### Files Created (3 total)

1. **src/Migrations/Version20260102000000_AddCraftingTutorial.php**
   - Database migration for core step data
   - Creates 23 tutorial steps
   - Adds tutorial catalog entry

2. **scripts/tutorial/export_craft_tutorial.sh**
   - Export script for detailed configurations
   - Exports UI, validation, interaction, and prerequisite configs
   - Executable bash script

3. **docs/CRAFTING_TUTORIAL_DEPLOYMENT.md**
   - Comprehensive deployment guide
   - Configuration reference tables
   - Troubleshooting section

## Database Tables Populated

| Table | Records | Purpose |
|-------|---------|---------|
| tutorial_catalog | 1 | Tutorial version entry for 2.0.0-craft |
| tutorial_steps | 23 | Core step definitions |
| tutorial_step_ui | ~23 | Tooltip positioning and selectors |
| tutorial_step_validation | ~23 | Validation rules and hints |
| tutorial_step_interactions | ~60 | Allowed interactive elements |
| tutorial_step_prerequisites | ~10 | MVT/PA requirements and restoration |

## Key Selectors Added

- Wood gathering: `.case[data-coords="0,1"]` (tree)
- Stone gathering: `.case[data-coords="2,0"]` (rock)
- Crafting: `input[data-item-name="pioche"]` (craft button)
- Actions panel: `#current-player-avatar` (player avatar)
- Fouiller action: `.action[data-action="fouiller"]`
- Stone ingredient: `.item-case[data-name="Pierre"]`

## Testing Commands

```bash
# Test in development environment
cd /var/www/html

# Run database migration
php vendor/bin/doctrine-migrations migrate

# Generate configuration export
./scripts/tutorial/export_craft_tutorial.sh

# Verify tutorial data
mysql -u root -p aoo4_test -e "SELECT COUNT(*) as craft_steps FROM tutorial_steps WHERE version='2.0.0-craft';"
# Expected output: 23

# Test tutorial creation
mysql -u root -p aoo4_test -e "SELECT * FROM tutorial_catalog WHERE version='2.0.0-craft';"
# Expected: 1 row for 2.0.0-craft
```

## Known Issues & Solutions

| Issue | Status | Solution |
|-------|--------|----------|
| PA not restoring for second stone | FIXED | Uses `putBonus(['a' => amount])` now |
| Tooltip disappears on step advance | FIXED | Added `.stop(true, false)` for animation queue |
| Craft button redirect stays on inventory | FIXED | Changed to `location.href = 'index.php'` |
| Version validation rejects -craft suffix | FIXED | Updated regex to `/^\d+\.\d+\.\d+(-[a-zA-Z0-9]+)?$/` |
| Wrong craft button targeted | FIXED | Added `data-item-name` attribute to buttons |

## Rollback Instructions

If you need to rollback after deployment:

```bash
# Restore database
mysql -u root -p database < backup_database.sql

# Restore code
git revert HEAD~N  # Where N is number of commits to revert
# Or manually restore from backup

# Clear caches
# - Browser: Hard refresh (Ctrl+F5)
# - Server: Clear cache directories
# - CDN: Purge cache

# Disable tutorial if needed
mysql -u root -p database -e "UPDATE tutorial_catalog SET is_active = 0 WHERE version = '2.0.0-craft';"
```

## Post-Deployment Validation

After deployment, verify:
1. Tutorial appears in player's tutorial selection screen
2. Tutorial can be started successfully
3. All tooltips display with correct positioning
4. All steps advance properly
5. PA/MVT resources are restored when needed
6. Crafting completes successfully
7. Player returns to main game screen after completion
8. XP rewards are granted correctly

## Support & Maintenance

For issues:
- Check troubleshooting section in CRAFTING_TUTORIAL_DEPLOYMENT.md
- Review tutorial step configurations in admin interface
- Verify cache is cleared (Ui.php version)
- Check browser console for JavaScript errors

For updates:
- Modify tutorial text via admin interface or SQL
- Re-export configurations when changes are made
- Keep backup of all tutorial data
- Document any customizations made for future deployments

---

**Last Updated**: 2025-01-02  
**Tutorial Version**: 2.0.0-craft  
**Status**: Production Ready ✓
