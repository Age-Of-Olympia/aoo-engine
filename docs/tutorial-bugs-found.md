# Tutorial System Bugs Found - Diagnostic Report

**Date:** 2025-11-30
**Database:** aoo_prod_20251127 (production)
**Diagnostic Script:** `scripts/tutorial/test_tutorial_workflow_diagnostic.php`

---

## Executive Summary

Ran comprehensive diagnostic on production database. Found **3 critical bugs** and **1 inconsistency**:

### ✅ What's Working:
- ✅ Player placement after tutorial **IS working correctly** (players moved to race respawn plans)
- ✅ Tutorial player isolation working
- ✅ XP/PI transfer on completion working
- ✅ `skip.php` removes `invisibleMode` correctly

### ❌ What's Broken:
- ❌ **Bug #1**: `cancel.php` does NOT remove `invisibleMode`
- ❌ **Bug #2**: `cancel.php` does NOT grant skip rewards
- ❌ **Bug #3**: `skip.php` does NOT grant skip rewards
- ⚠️ **Inconsistency**: Some early cancellations left players with `invisibleMode=YES`

---

## Bug #1: cancel.php Doesn't Remove invisibleMode

### Evidence:

```sql
SELECT
    tp.tutorial_session_id,
    tp.player_id,
    tp.current_step,
    tp.xp_earned,
    (SELECT COUNT(*) FROM players_options po
     WHERE po.player_id = tp.player_id AND po.name = 'invisibleMode') as has_invisible
FROM tutorial_progress tp
WHERE tp.completed = 1 AND tp.current_step = 'welcome'
ORDER BY tp.completed_at DESC
LIMIT 3;
```

**Result:**
| Player | Step    | XP Earned | invisibleMode |
|--------|---------|-----------|---------------|
| 320    | welcome | 0         | **YES** ❌    |
| 319    | welcome | 0         | **YES** ❌    |
| 318    | welcome | 0         | **YES** ❌    |

### Root Cause:

`api/tutorial/cancel.php` cleans up tutorial resources but **does not call `$player->end_option('invisibleMode')`**.

```php
// api/tutorial/cancel.php lines 54-124
// ❌ MISSING: No call to remove invisibleMode
// ✅ DOES: Clean up tutorial resources
// ✅ DOES: Mark session as completed
```

### Impact:

**CRITICAL** - Players who cancel tutorial remain invisible and cannot interact with other players.

### Fix Required:

Add to `cancel.php` after cleanup:

```php
// Remove invisibleMode from main player
$playerId = $_SESSION['playerId'];
$player = new \Classes\Player($playerId);
$player->end_option('invisibleMode');
error_log("[Cancel] Removed invisibleMode from player {$playerId}");
```

---

## Bug #2: cancel.php Doesn't Grant Skip Rewards

### Evidence:

Players who cancel tutorial get **0 XP and 0 PI**:

| Player | Cancelled At | XP Earned | PI Earned |
|--------|-------------|-----------|-----------|
| 320    | welcome     | 0         | 0         |
| 319    | welcome     | 0         | 0         |
| 318    | welcome     | 0         | 0         |

Compare to players who completed full tutorial:
| Player | Completed At       | XP Earned | PI Earned |
|--------|--------------------|-----------|-----------|
| 313    | tutorial_complete  | 240       | 240       |

### Root Cause:

`cancel.php` has no logic to grant "skip rewards" (consolation XP/PI for not completing tutorial).

### Impact:

**MEDIUM** - Players who skip tutorial get nothing, creating negative incentive. They might feel punished for skipping.

### Fix Required:

1. Add configurable skip reward constant in `config/constants.php`:
```php
define('TUTORIAL_SKIP_REWARD', [
    'xp' => 50,  // 50 XP instead of ~240 from full tutorial
    'pi' => 50   // 50 PI
]);
```

2. Grant skip reward in `cancel.php`:
```php
// Grant skip rewards
$skipReward = TUTORIAL_SKIP_REWARD;
$player->putBonus([
    'xp' => $skipReward['xp'],
    'pi' => $skipReward['pi']
]);
error_log("[Cancel] Player {$playerId} received skip reward: {$skipReward['xp']} XP, {$skipReward['pi']} PI");
```

---

## Bug #3: skip.php Doesn't Grant Skip Rewards

### Evidence:

Same issue as Bug #2 - `skip.php` removes `invisibleMode` but grants no rewards.

### Root Cause:

`skip.php` lines 50-72:
```php
// ✅ DOES: Add race actions
// ✅ DOES: Remove invisibleMode
// ❌ MISSING: No XP/PI grant
```

### Impact:

**MEDIUM** - Players using "Passer le tutoriel" button get no compensation.

### Fix Required:

Same as Bug #2 - add skip reward grant to `skip.php`:

```php
// Grant skip rewards (after removing invisibleMode)
$skipReward = TUTORIAL_SKIP_REWARD;
$player->putBonus([
    'xp' => $skipReward['xp'],
    'pi' => $skipReward['pi']
]);
error_log("[Skip] Player {$playerId} received skip reward: {$skipReward['xp']} XP, {$skipReward['pi']} PI");
```

---

## Positive Findings

### ✅ Player Placement Works Correctly

**Tested with production data:**

All players after tutorial completion ARE at their race respawn plans:

| Player | Race | Expected Plan         | Actual Plan           | Status |
|--------|------|----------------------|----------------------|--------|
| 320    | hs   | tertre_sauvage_s2    | tertre_sauvage_s2    | ✅ OK  |
| 319    | hs   | tertre_sauvage_s2    | tertre_sauvage_s2    | ✅ OK  |
| 318    | hs   | tertre_sauvage_s2    | tertre_sauvage_s2    | ✅ OK  |
| 317    | hs   | tertre_sauvage_s2    | tertre_sauvage_s2    | ✅ OK  |
| 316    | hs   | tertre_sauvage_s2    | tertre_sauvage_s2    | ✅ OK  |

**This contradicts the initial analysis** - placement logic IS working!

Players are spawned on their race respawn plan during registration (`register.php:110-125`):

```php
// register.php lines 110-125
$factionJson = json()->decode('factions', $player->data->faction);
$spawnPlan = $factionJson->respawnPlan ?? "olympia";

$goCoords = (object) array(
    'x' => 0,
    'y' => 0,
    'z' => 0,
    'plan' => $spawnPlan  // ← Already using respawnPlan!
);
```

**Conclusion:** Player placement does NOT need fixing! Original analysis was based on assumption, real data shows it works.

### ✅ Tutorial Isolation Works

Player 321 (active tutorial):
- Real player: On `tertre_sauvage_s2` (correct spawn plan)
- Tutorial player: On `tut_ba1c0ed9-a` (isolated plan)
- No conflicts, proper separation

---

## Recommended Fixes (Priority Order)

### Priority 1: Critical (Must Fix Immediately)

#### Fix #1.1: Add invisibleMode removal to cancel.php

**File:** `api/tutorial/cancel.php`
**Location:** After line 123 (after cleanup, before success response)

```php
// Remove invisibleMode from main player so they can play normally
$playerId = $_SESSION['playerId'];
$mainPlayer = new \Classes\Player($playerId);
$mainPlayer->end_option('invisibleMode');
error_log("[Cancel] Removed invisibleMode from player {$playerId}");
```

### Priority 2: High (Should Fix Soon)

#### Fix #2.1: Add skip reward constant

**File:** `config/constants.php`
**Location:** End of file (or in tutorial section if exists)

```php
// Tutorial skip rewards (granted when player skips instead of completing)
define('TUTORIAL_SKIP_REWARD', [
    'xp' => 50,  // Fixed XP for skipping (vs ~240 from full tutorial)
    'pi' => 50   // Fixed PI for skipping (vs ~240 from full tutorial)
]);
```

#### Fix #2.2: Grant skip rewards in cancel.php

**File:** `api/tutorial/cancel.php`
**Location:** After invisibleMode removal

```php
// Grant skip rewards as consolation
$skipReward = TUTORIAL_SKIP_REWARD;
$mainPlayer->putBonus([
    'xp' => $skipReward['xp'],
    'pi' => $skipReward['pi']
]);
error_log("[Cancel] Player {$playerId} received skip reward: {$skipReward['xp']} XP, {$skipReward['pi']} PI");
```

#### Fix #2.3: Grant skip rewards in skip.php

**File:** `api/tutorial/skip.php`
**Location:** After line 71 (after invisibleMode removal)

```php
// Grant skip rewards as consolation
$skipReward = TUTORIAL_SKIP_REWARD;
$player->putBonus([
    'xp' => $skipReward['xp'],
    'pi' => $skipReward['pi']
]);
error_log("[Skip] Player {$playerId} received skip reward: {$skipReward['xp']} XP, {$skipReward['pi']} PI");
```

### Priority 3: Medium (Nice to Have)

#### Fix #3.1: Consolidate skip/cancel endpoints

Since both do nearly the same thing, consider:
- Keep `cancel.php` as main endpoint
- Make `skip.php` call `cancel.php` internally
- Or remove `skip.php` and update UI to use `cancel.php`

#### Fix #3.2: Improve modal messaging

Update `index.php` modal to explain skip rewards:

```html
<p>Si tu abandonnes le tutoriel, tu recevras <?php echo TUTORIAL_SKIP_REWARD['xp']; ?> XP
   au lieu des récompenses complètes du tutoriel (~240 XP).</p>
```

---

## Files That Need Changes

### Critical Files:
1. ✅ `api/tutorial/cancel.php` - Add invisibleMode removal + skip rewards
2. ✅ `api/tutorial/skip.php` - Add skip rewards
3. ✅ `config/constants.php` - Add TUTORIAL_SKIP_REWARD constant

### Optional Files:
4. ⚠️ `index.php` - Update modal text to explain skip rewards
5. ⚠️ `js/tutorial/TutorialUI.js` - Update cancel confirmation message

---

## Testing Plan

### Test 1: Verify cancel.php fix
1. Create test player
2. Start tutorial
3. Click "Annuler le tutoriel"
4. ✅ Verify invisibleMode removed
5. ✅ Verify skip rewards granted (50 XP, 50 PI)
6. ✅ Verify player can play normally

### Test 2: Verify skip.php fix
1. Create test player
2. Register and see modal
3. Click "Passer le tutoriel"
4. ✅ Verify invisibleMode removed (already works)
5. ✅ Verify skip rewards granted (50 XP, 50 PI)
6. ✅ Verify player can play normally

### Test 3: Verify placement still works
1. Complete full tutorial
2. ✅ Verify player at race respawn plan (already confirmed working)
3. ✅ Verify XP/PI transferred (already confirmed working)

---

## Summary

**Bugs Found:** 3 critical issues
**Bugs Fixed (Proposed):** 3 fixes ready
**Files to Change:** 3 files
**Estimated Time:** 30 minutes
**Risk Level:** Low (targeted fixes, no refactoring needed)

**Good News:**
- Player placement already works correctly (no fix needed!)
- Tutorial isolation working perfectly
- XP/PI transfer on completion working

**Priority Action:**
Fix `cancel.php` to remove `invisibleMode` - this affects players 318, 319, 320 who are currently stuck invisible!
