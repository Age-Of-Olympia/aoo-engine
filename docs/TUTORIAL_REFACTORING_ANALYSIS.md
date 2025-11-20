# Tutorial System Refactoring Analysis

**Date:** 2025-11-19
**Status:** ACTION REQUIRED - Critical issues identified

---

## Executive Summary

The tutorial system has **DUPLICATE DATABASE SCHEMAS** from an incomplete migration. Both old (`tutorial_configurations`) and new (`tutorial_steps` + 9 related tables) exist with identical data, causing confusion and maintenance burden.

**Critical Findings:**
1. ✅ **Duplicate Schema**: 2 complete table systems storing same data
2. ⚠️ **Code Duplication**: Enemy cleanup logic copied in 3 places
3. ⚠️ **Unused Columns**: Several DB columns never read after creation
4. ⚠️ **God Class**: TutorialManager doing too many things (865 LOC)
5. ⚠️ **Inconsistent Usage**: One API endpoint still queries old table

---

## 1. DUPLICATE DATABASE SCHEMA

### Problem

**TWO COMPLETE TABLE SYSTEMS EXIST:**

#### Old System (DEPRECATED but still present):
- **`tutorial_configurations`** - Monolithic JSON blob design
  - 27 rows (identical content to new system)
  - Still queried by `api/tutorial/jump-to-step.php:41`
  - Created by migration `Version20251111120000_AddTutorialTables.php`

#### New System (ACTIVELY USED):
- **`tutorial_steps`** - Main step data
- **`tutorial_step_ui`** - UI configuration (1:1)
- **`tutorial_step_validation`** - Validation rules (1:1)
- **`tutorial_step_prerequisites`** - Resource requirements (1:1)
- **`tutorial_step_features`** - Special features (1:1)
- **`tutorial_step_highlights`** - Additional highlights (1:N)
- **`tutorial_step_interactions`** - Allowed interactions (1:N)
- **`tutorial_step_context_changes`** - Context modifications (1:N)
- **`tutorial_step_next_preparation`** - Next step prep (1:N)
  - 27 rows total (same steps as old system)
  - Queried by `TutorialStepRepository` (all queries)

### Evidence

```bash
# Old system: 27 steps
mysql> SELECT COUNT(*) FROM tutorial_configurations;
+----------+
| COUNT(*) |
+----------+
|       27 |
+----------+

# New system: 27 steps
mysql> SELECT COUNT(*) FROM tutorial_steps;
+----------+
| COUNT(*) |
+----------+
|       27 |
+----------+
```

```php
// TutorialStepRepository.php:82 - Uses NEW system
FROM tutorial_steps ts
LEFT JOIN tutorial_step_ui ui ON ts.id = ui.step_id
LEFT JOIN tutorial_step_validation v ON ts.id = v.step_id
...

// api/tutorial/jump-to-step.php:41 - Uses OLD system (BUG!)
$stmt = $conn->prepare("SELECT step_number FROM tutorial_configurations WHERE step_id = ?");
```

### Impact

- **Storage Waste**: Duplicate data across 10 tables
- **Maintenance Burden**: Must update both systems when adding steps
- **Confusion**: Developers unsure which is source of truth
- **Bug Risk**: Inconsistent queries (99% use new, 1% use old)
- **Migration Incomplete**: Old table never removed after migration

### Recommendation

**DELETE `tutorial_configurations` table immediately** after fixing `jump-to-step.php`.

---

## 2. CODE DUPLICATION

### 2.1 Enemy Cleanup Logic (CRITICAL)

**Duplicated in 3 locations:**

1. **`TutorialManager::removeTutorialEnemy()`** (lines 705-756)
2. **`api/tutorial/cancel.php`** (lines 62-100)
3. **`api/tutorial/cancel.php`** (lines 133-166) - Second copy for orphaned cleanup!

**Identical 30-line pattern:**
```php
// 1. Query tutorial_enemies
SELECT enemy_player_id, enemy_coords_id FROM tutorial_enemies WHERE ...

// 2. Delete from 7-9 related tables
$conn->delete('players_logs', ['player_id' => $enemyId]);
$conn->delete('players_logs', ['target_id' => $enemyId]);
$conn->delete('players_actions', ['player_id' => $enemyId]);
$conn->delete('players_items', ['player_id' => $enemyId]);
$conn->delete('players_effects', ['player_id' => $enemyId]);
$conn->delete('players_kills', ['player_id' => $enemyId]);
$conn->delete('players_kills', ['target_id' => $enemyId]);
$conn->delete('players_assists', ['player_id' => $enemyId]); // Sometimes missing!
$conn->delete('players_assists', ['target_id' => $enemyId]); // Sometimes missing!

// 3. Delete player
$conn->delete('players', ['id' => $enemyId]);

// 4. Delete coords
$conn->delete('coords', ['id' => $coordsId]);

// 5. Delete tracking record
$conn->delete('tutorial_enemies', ['tutorial_session_id' => $sessionId]);
```

**Inconsistency:**
- TutorialManager version deletes from `players_assists` (lines 732-733)
- cancel.php first version DOES NOT delete from `players_assists` (line 82 stops at players_kills)
- This will cause orphaned records!

**Refactoring:**
```php
// NEW FILE: src/Tutorial/TutorialEnemyCleanup.php
class TutorialEnemyCleanup {
    public static function removeBySessionId(Connection $conn, string $sessionId): int;
    private static function deleteForeignKeyReferences(Connection $conn, int $enemyId): void;
}
```

---

### 2.2 Player Cleanup Logic

**Duplicated in 2 locations:**

1. **`TutorialManager::cleanupPreviousTutorialPlayers()`** (lines 562-618)
2. **`TutorialPlayer::delete()`** (lines 293-367)

Both delete from **30+ tables** in slightly different order:
```php
// 57 lines of:
$conn->delete('players_actions', ['player_id' => $playerId]);
$conn->delete('players_effects', ['player_id' => $playerId]);
$conn->delete('players_items', ['player_id' => $playerId]);
// ... 30 more tables ...
```

**Refactoring:**
```php
// NEW FILE: src/Tutorial/TutorialPlayerCleanup.php
class TutorialPlayerCleanup {
    const FOREIGN_KEY_TABLES = [
        'players_actions',
        'players_effects',
        // ... all 30 tables in dependency order
    ];

    public static function deleteForeignKeyReferences(Connection $conn, int $playerId): void;
}
```

---

## 3. UNUSED/REDUNDANT DATABASE COLUMNS

### 3.1 tutorial_players Table

**Columns that are WRITTEN but NEVER READ:**

| Column | Created | Read | Actual Usage |
|--------|---------|------|--------------|
| `coords_id` | ✅ TutorialPlayer.php:133 | ❌ Never | Stored but unused (player coords via players.coords_id) |
| `race` | ✅ TutorialPlayer.php:134 | ❌ Never | Stored but unused (player race via players.race) |
| `xp` | ✅ TutorialPlayer.php:135 | ⚠️ Only at completion | **Duplicate**: TutorialContext.tutorialXP is source of truth |
| `pi` | ✅ TutorialPlayer.php:135 | ⚠️ Only at completion | **Duplicate**: TutorialContext tracks separately |
| `energie` | ✅ TutorialPlayer.php:134 | ❌ Never | Stored but completely unused |
| `level` | ✅ TutorialPlayer.php:135 | ❌ Never | **Duplicate**: TutorialContext.tutorialLevel is source of truth |

**Evidence:**
```bash
$ grep -r "tutorialPlayer->xp" /var/www/html/src/Tutorial
TutorialPlayer.php:255:    $this->xp = $xp;              # WRITE
TutorialPlayer.php:475:    $xpToTransfer = $this->xp;    # READ (only at completion)

$ grep -r "tutorialPlayer->energie" /var/www/html/src/Tutorial
# NO RESULTS - Never read!

$ grep -r "tutorialPlayer->coords_id\|tutorialPlayer->race" /var/www/html/src/Tutorial
# NO RESULTS - Never read!
```

**Why Duplicate State is Bad:**
```php
// CURRENT MESS: Two sources of truth
$tutorialXP = $this->context->getTutorialXP();        // Primary source
$xpEarned = $this->tutorialPlayer->xp;                // Secondary source (can diverge!)

// During tutorial:
$this->context->awardXP(10);                          // Updates context
$this->tutorialPlayer->xp += 10;                      // Must manually sync!
$this->tutorialPlayer->updateInDatabase();            // Extra DB write
```

**Recommendation:**
- **DELETE** columns: `coords_id`, `race`, `energie`, `level` (never read)
- **DELETE** columns: `xp`, `pi` (duplicate state, use TutorialContext only)
- **KEEP** only: `id`, `real_player_id`, `tutorial_session_id`, `player_id`, `name`, `is_active`, `created_at`, `deleted_at`

---

### 3.2 tutorial_progress Table

**Columns with Limited Usage:**

| Column | Usage | Keep? |
|--------|-------|-------|
| `tutorial_mode` | enum('first_time','replay','practice') | ⚠️ Only checked for "first_time" completion, never used for replay/practice logic |
| `tutorial_version` | varchar(20) default '1.0.0' | ✅ Used in all step queries |
| `xp_earned` | int default 0 | ⚠️ Only returned to client, could be calculated on-demand |

**Recommendation:**
- **KEEP** `tutorial_mode` and `tutorial_version` (architectural value even if underutilized)
- **CONSIDER** removing `xp_earned` (can be calculated from context.data)

---

## 4. GOD CLASS: TutorialManager

**Current:** 865 lines, 15+ responsibilities

**Responsibilities:**
1. Session lifecycle (start, resume, complete, cancel)
2. Step progression and validation
3. Player creation and cleanup
4. Enemy spawning and removal
5. Map instance coordination
6. XP/PI tracking
7. Progress tracking
8. Step repository coordination
9. Context management
10. Database transaction management

**Impact:**
- Hard to test (tight coupling)
- Hard to maintain (must understand entire tutorial system to change one thing)
- Hard to extend (adding new tutorial feature touches this massive file)
- Violates Single Responsibility Principle

**Refactoring:**

```php
// SPLIT INTO:

// 1. TutorialSessionManager.php (150 LOC)
//    - Session lifecycle only
//    - Start, resume, cancel, isActive

// 2. TutorialProgressManager.php (200 LOC)
//    - Step advancement
//    - Validation coordination
//    - XP/PI tracking

// 3. TutorialResourceManager.php (200 LOC)
//    - Player creation/cleanup
//    - Enemy spawning/removal
//    - Map instance coordination

// 4. Keep TutorialManager.php as facade (100 LOC)
//    - Delegates to above managers
//    - Maintains backward compatibility
```

---

## 5. MAGIC NUMBERS

**Examples:**

```php
// TutorialMapInstance.php:46
$instancePlan = 'tut_' . substr($sessionId, 0, 10);
// Why 10? What if sessionId < 10 chars? Document!

// TutorialManager.php:634
$enemyId = -100000 - mt_rand(1, 899999);
// Why this range? What prevents collision? Document!

// TutorialUI.js:1029
const maxRetries = 50;
// Why 50? How long is that in time? Document!

// TutorialUI.js:1423
await this.delay(100);
// Why 100ms? Document!
```

**Refactoring:**
```php
// Add constants with explanatory comments
class TutorialConstants {
    /**
     * Max length for plan name prefix to stay under DB limit (varchar 50)
     * Format: 'tut_' (4) + session_id_prefix (10) + safety margin = 14 chars
     */
    const INSTANCE_PLAN_PREFIX_LENGTH = 10;

    /**
     * Enemy ID range to avoid collision with:
     * - Real players (positive IDs)
     * - Regular NPCs (-1 to -99999)
     * Range: -100000 to -999999 (900k IDs)
     */
    const TUTORIAL_ENEMY_ID_MIN = -100000;
    const TUTORIAL_ENEMY_ID_RANGE = 899999;
}
```

---

## 6. POOR ERROR HANDLING

**Examples:**

```php
// TutorialMapInstance.php:95 - Generic exception
throw new \RuntimeException("Failed to create tutorial map instance");
// What failed? File copy? DB insert? Unclear!

// TutorialPlayer.php:353 - Catch and re-throw without context
try {
    $conn->delete('players', ['id' => $this->playerId]);
} catch (\Exception $e) {
    throw $e; // Lost stack trace, no context added
}

// api/tutorial/cancel.php:98 - Silent failure
} catch (\Exception $e) {
    error_log("[Cancel] Error removing tutorial enemy: " . $e->getMessage());
    // Continues execution - enemy might not be deleted!
}
```

**Refactoring:**
```php
// Create specific exceptions
class TutorialException extends \Exception {}
class TutorialMapInstanceException extends TutorialException {}
class TutorialPlayerCleanupException extends TutorialException {}

// Add context
throw new TutorialMapInstanceException(
    "Failed to copy plan JSON file: {$templatePath} → {$instancePath}",
    0,
    $previousException
);

// Don't swallow errors in critical paths
try {
    $this->removeTutorialEnemy($conn, $sessionId);
} catch (TutorialException $e) {
    error_log("[Cancel] CRITICAL: Enemy cleanup failed: " . $e->getMessage());
    // Re-throw for critical operations
    throw $e;
}
```

---

## 7. INCONSISTENT SESSION STORAGE

**Problem:** Tutorial state stored in 3 places with unclear synchronization:

1. **PHP `$_SESSION`** (volatile, cleared on logout)
   - `in_tutorial` (boolean flag)
   - `tutorial_session_id` (UUID)
   - `tutorial_player_id` (int)
   - `tutorial_consume_movements` (boolean)

2. **JavaScript `sessionStorage`** (volatile, cleared on tab close)
   - `tutorial_active` (string "true")
   - `tutorial_just_started` (string "true")

3. **Database `tutorial_progress.data`** (persistent)
   - Serialized TutorialContext state
   - `unlimited_mvt`, `unlimited_actions`, etc.

**Race Conditions:**
```javascript
// TutorialInit.js:45
if (sessionStorage.getItem('tutorial_active') === 'true') {
    // What if DB says tutorial completed but sessionStorage not cleared?
    // What if PHP session expired but sessionStorage persists?
    tutorialUI.resume(); // Might resume completed tutorial!
}
```

**Recommendation:**
- **Single Source of Truth**: Database `tutorial_progress` table
- **PHP Session**: Only for performance (cache of DB state, validated on each request)
- **sessionStorage**: Only for UX flags, never for state
- **Always validate**: Check DB before trusting session/storage

---

## 8. VALIDATION SCATTERED ACROSS LAYERS

**Current Architecture:**

```
User Action
    ↓
TutorialUI.js (client validation) ← 1st validation layer
    ↓ POST /api/tutorial/advance.php
api/tutorial/advance.php (input validation) ← 2nd validation layer
    ↓
TutorialManager::advanceStep()
    ↓
AbstractStep::validate() ← 3rd validation layer
    ↓
MovementStep::validate() (override) ← 4th validation layer
```

**Issues:**
- Unclear where actual validation happens
- Client validation can be bypassed
- Duplication between client/server
- Hard to test (must test 4 layers)

**Recommendation:**
```php
// SINGLE SOURCE OF TRUTH: Step classes

// Client: NO validation, just data collection
TutorialUI.next({x: 1, y: 2, action: "moved"});

// API: Input sanitization ONLY (not business logic)
$validationData = [
    'x' => filter_var($_POST['x'], FILTER_VALIDATE_INT),
    'y' => filter_var($_POST['y'], FILTER_VALIDATE_INT),
];

// Step: ALL business validation
class MovementStep extends AbstractStep {
    public function validate(array $data): bool {
        // Single place for all movement validation logic
    }
}
```

---

## 9. SUMMARY OF ISSUES

| Issue | Severity | LOC Impact | Files Affected |
|-------|----------|-----------|----------------|
| Duplicate Schema | 🔴 CRITICAL | ~500 LOC | 1 table, 3 migrations, 2 files |
| Enemy Cleanup Duplication | 🔴 CRITICAL | ~90 LOC (3×30) | 2 files |
| Player Cleanup Duplication | 🟡 HIGH | ~120 LOC (2×60) | 2 files |
| Unused DB Columns | 🟡 HIGH | ~100 LOC | 1 table, 5 files |
| God Class | 🟡 HIGH | 865 LOC | 1 file |
| Magic Numbers | 🟢 MEDIUM | ~20 instances | 6 files |
| Poor Error Handling | 🟢 MEDIUM | ~50 LOC | 8 files |
| Session Inconsistency | 🟡 HIGH | ~30 LOC | 6 files |
| Scattered Validation | 🟢 MEDIUM | ~200 LOC | 8 files |

---

## 10. REFACTORING PRIORITY

### Phase 1: CRITICAL (Do Immediately)

1. **Fix `jump-to-step.php` to use `tutorial_steps` table** (5 min)
2. **Delete `tutorial_configurations` table** (1 min)
3. **Extract `TutorialEnemyCleanup` service** (30 min)
   - Fixes inconsistency bug (missing players_assists cleanup)
   - Removes 90 LOC duplication
4. **Update CLAUDE.md** with correct schema (10 min)

**Time:** ~1 hour
**Risk:** Low
**Impact:** Eliminates critical bugs and confusion

---

### Phase 2: HIGH PRIORITY (Do This Week)

5. **Extract `TutorialPlayerCleanup` service** (1 hour)
   - Removes 120 LOC duplication
6. **Remove unused columns from `tutorial_players`** (30 min)
   - Delete: coords_id, race, energie, level, xp, pi
   - Update TutorialPlayer class
7. **Add named constants for magic numbers** (30 min)
8. **Add specific exception types** (30 min)

**Time:** ~3 hours
**Risk:** Low (well-isolated changes)
**Impact:** Cleaner code, easier maintenance

---

### Phase 3: MEDIUM PRIORITY (Do This Sprint)

9. **Split TutorialManager into 3 managers** (4 hours)
   - TutorialSessionManager
   - TutorialProgressManager
   - TutorialResourceManager
10. **Centralize session state in DB** (2 hours)
    - Add validation layer
    - Remove reliance on sessionStorage for state

**Time:** ~6 hours
**Risk:** Medium (touches core architecture)
**Impact:** Much easier to extend/maintain

---

### Phase 4: LONG-TERM (Future)

11. **Centralize validation in Step classes** (4 hours)
12. **Add comprehensive error handling** (3 hours)
13. **Add unit tests for all Step validation** (8 hours)
14. **Consider event-driven architecture** (research)

**Time:** ~15 hours
**Risk:** Medium-High
**Impact:** Robust, testable system

---

## 11. MIGRATION SCRIPTS

See `scripts/tutorial/migrate_remove_legacy_schema.php` for automated cleanup.

---

## 12. POSITIVE ASPECTS (What NOT to Change)

✅ **Clean separation** of TutorialContext from main game state
✅ **Isolated map instances** prevent player interference
✅ **Normalized step schema** allows flexible configuration
✅ **Comprehensive cleanup** prevents data leaks
✅ **Auto-resume** functionality works well
✅ **Repository pattern** for step data access
✅ **Factory pattern** for step instantiation

**Don't refactor these - they're working well!**

---

## 13. CONCLUSION

The tutorial system is **architecturally sound** but has **technical debt from incomplete migration**. The duplicate schema is the most critical issue and must be fixed immediately. Code duplication and god class issues should be addressed in the next sprint.

**Recommended Action Plan:**
1. Execute Phase 1 immediately (1 hour)
2. Schedule Phase 2 for this week (3 hours)
3. Schedule Phase 3 for this sprint (6 hours)
4. Plan Phase 4 for next sprint (15 hours)

**Total Effort:** ~25 hours over 2 sprints
**Risk Level:** Low-Medium
**Benefit:** Maintainable, bug-free tutorial system
