# Tutorial Refactoring Quick Start Guide

**READ THIS FIRST** - 5 minute action plan

---

## TL;DR - Do This Now (15 minutes)

```bash
# 1. Fix the bug in jump-to-step.php (5 min)
# Change line 41 from:
#   tutorial_configurations → tutorial_steps

# 2. Test that jump works (2 min)
curl -X POST http://localhost/api/tutorial/jump-to-step.php \
  -d "step_id=first_movement&session_id=test"

# 3. Remove legacy table (1 min with backup)
php scripts/tutorial/migrate_remove_legacy_schema.php --backup

# 4. Run tests (5 min)
make test

# 5. Update CLAUDE.md (2 min)
# Remove all references to tutorial_configurations table
```

**That's it! You've fixed the critical issue.**

---

## What Was Wrong?

### The Problem
You have **TWO COMPLETE DATABASE SCHEMAS** for tutorial steps:

1. ❌ **OLD**: `tutorial_configurations` (single table with JSON blob)
   - 27 rows
   - Created by migration in Nov 2025
   - Still queried by `jump-to-step.php` ← BUG!

2. ✅ **NEW**: `tutorial_steps` + 9 related tables (normalized schema)
   - 27 rows (same data)
   - Used by 99% of code
   - Better design (normalized, flexible)

This happened because:
- Migration created new schema
- Populated both schemas with data
- Never deleted old schema
- One file still uses old schema

---

## Code Duplication Summary

### 🔴 CRITICAL: Enemy Cleanup (duplicated 3×)

**Same 30 lines in:**
1. `TutorialManager::removeTutorialEnemy()` - lines 705-756
2. `api/tutorial/cancel.php` - lines 62-100
3. `api/tutorial/cancel.php` - lines 133-166 (orphaned cleanup)

**Bug:** Version in cancel.php is missing `players_assists` cleanup!

**Fix:**
```php
// Use new service instead:
use App\Tutorial\TutorialEnemyCleanup;

$cleanup = new TutorialEnemyCleanup($conn);
$cleanup->removeBySessionId($sessionId);
```

---

### 🟡 HIGH: Player Cleanup (duplicated 2×)

**Same 60 lines in:**
1. `TutorialManager::cleanupPreviousTutorialPlayers()` - lines 562-618
2. `TutorialPlayer::delete()` - lines 293-367

**Fix:** Create `TutorialPlayerCleanup` service (similar to enemy cleanup)

---

## Unused Database Columns

### tutorial_players table - DELETE THESE:

| Column | Why Unused |
|--------|------------|
| `coords_id` | Never read (get from players.coords_id instead) |
| `race` | Never read (get from players.race instead) |
| `energie` | **NEVER** read anywhere! |
| `level` | Duplicate of TutorialContext.tutorialLevel |
| `xp` | Duplicate of TutorialContext.tutorialXP |
| `pi` | Duplicate state |

**Keep only:**
- `id`, `real_player_id`, `tutorial_session_id`, `player_id`
- `name`, `is_active`, `created_at`, `deleted_at`

---

## Quick Wins (30 min each)

### 1. Extract Constants
```php
// BEFORE
$instancePlan = 'tut_' . substr($sessionId, 0, 10); // Why 10?
$enemyId = -100000 - mt_rand(1, 899999);            // Why this range?

// AFTER (in new TutorialConstants.php)
class TutorialConstants {
    /** @var int Max plan prefix to fit DB limit (varchar 50) */
    const INSTANCE_PLAN_PREFIX_LENGTH = 10;

    /** @var int Min enemy ID (avoid collision with real NPCs) */
    const TUTORIAL_ENEMY_ID_MIN = -100000;
}
```

### 2. Add Error Types
```php
// BEFORE
throw new \RuntimeException("Failed"); // Generic!

// AFTER
throw new TutorialMapInstanceException(
    "Failed to copy plan JSON: {$source} → {$dest}",
    0,
    $previousException
);
```

### 3. Fix Session State
```php
// BEFORE
if (sessionStorage.getItem('tutorial_active') === 'true') {
    // What if DB says completed?
}

// AFTER
// Always check DB first, sessionStorage is just UX hint
const dbState = await fetch('/api/tutorial/resume.php');
if (dbState.active) { ... }
```

---

## File Roadmap

### Created for You:
1. ✅ `docs/TUTORIAL_REFACTORING_ANALYSIS.md` - Full 13-section analysis
2. ✅ `scripts/tutorial/migrate_remove_legacy_schema.php` - Automated migration
3. ✅ `src/Tutorial/TutorialEnemyCleanup.php` - Cleanup service

### You Need to Create:
4. ⬜ `src/Tutorial/TutorialPlayerCleanup.php` - Player cleanup service
5. ⬜ `src/Tutorial/TutorialConstants.php` - Named constants
6. ⬜ `src/Tutorial/Exceptions/` - Exception classes

### You Need to Modify:
7. ⬜ `api/tutorial/jump-to-step.php:41` - Change table name
8. ⬜ `api/tutorial/cancel.php` - Use TutorialEnemyCleanup service
9. ⬜ `src/Tutorial/TutorialManager.php` - Use TutorialEnemyCleanup service
10. ⬜ `CLAUDE.md` - Remove tutorial_configurations references

---

## Testing Checklist

After each change:

```bash
# 1. Static analysis
make phpstan

# 2. Unit tests
make test

# 3. Tutorial flow test
php scripts/tutorial/test_tutorial_flow.php

# 4. Database integrity
mysql -h mariadb-aoo4 -u root -ppasswordRoot aoo_prod_20250821 \
  -e "SELECT COUNT(*) FROM tutorial_steps; SELECT COUNT(*) FROM tutorial_configurations;"

# 5. Manual test
# Start tutorial → advance a few steps → cancel → check for orphaned data
```

---

## Phased Approach

### ✅ Phase 1: CRITICAL (1 hour) ← DO THIS NOW
- Fix jump-to-step.php
- Remove tutorial_configurations table
- Extract TutorialEnemyCleanup
- Update CLAUDE.md

### ⏳ Phase 2: HIGH (3 hours) ← This Week
- Extract TutorialPlayerCleanup
- Remove unused DB columns
- Add named constants
- Add exception types

### 📅 Phase 3: MEDIUM (6 hours) ← This Sprint
- Split TutorialManager into 3 services
- Centralize session state

### 🔮 Phase 4: LONG-TERM (15 hours) ← Next Sprint
- Centralize validation
- Comprehensive error handling
- Unit tests
- Event-driven refactor

---

## Common Mistakes to Avoid

❌ **Don't** delete tutorial_configurations without fixing jump-to-step.php first
❌ **Don't** remove DB columns without checking ALL usage (including raw SQL)
❌ **Don't** refactor TutorialManager without tests in place
❌ **Don't** trust sessionStorage as source of truth
❌ **Don't** copy-paste cleanup logic again (use the service!)

✅ **Do** run tests after each change
✅ **Do** create backups before migrations
✅ **Do** update CLAUDE.md when changing architecture
✅ **Do** add comments explaining "why" not just "what"
✅ **Do** use the new cleanup services

---

## Getting Help

- Full analysis: `docs/TUTORIAL_REFACTORING_ANALYSIS.md`
- Migration script: `scripts/tutorial/migrate_remove_legacy_schema.php`
- Example service: `src/Tutorial/TutorialEnemyCleanup.php`

---

## Summary

**Problem:** Incomplete migration left duplicate schemas and duplicated code
**Solution:** Remove old schema, extract duplicated logic into services
**Effort:** ~10 hours total over 2 sprints
**Risk:** Low (well-isolated changes)
**Benefit:** Maintainable, bug-free tutorial system

**START WITH PHASE 1 - IT'S ONLY 1 HOUR!**
