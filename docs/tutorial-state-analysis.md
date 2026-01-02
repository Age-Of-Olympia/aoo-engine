# Tutorial System State Analysis

**Date:** 2025-11-30
**Purpose:** Comprehensive audit of tutorial system states, transitions, and edge cases

## Executive Summary

### Current Issues Identified

1. **Player Creation Strategy**: New players are created on `olympia` plan with `invisibleMode` instead of a dedicated waiting area
2. **Inconsistent Button UX**: Both "Skip Tutorial" and "Cancel" buttons exist with overlapping functionality
3. **Missing State Validation**: Some edge cases may not be properly handled
4. **Placement Logic**: Race-based starting positions not clearly enforced on completion/cancellation

---

## State Machine Definition

### Player States

```
[NEW_PLAYER] → Created, no tutorial session
   ↓
[INVISIBLE_WAITING] → On olympia plan, invisibleMode=true, waiting for tutorial
   ↓
[IN_TUTORIAL] → Active tutorial session, controlling tutorial player
   ↓
[TUTORIAL_COMPLETED] → Tutorial finished successfully
   OR
[TUTORIAL_CANCELLED] → Tutorial abandoned
   ↓
[ACTIVE_PLAYER] → Normal gameplay, invisibleMode=false
```

### Detailed State Definitions

#### 1. NEW_PLAYER
**Characteristics:**
- Player just registered (register.php completed)
- Entry in `players` table exists with ID
- `invisibleMode` = `true` (if new tutorial enabled)
- `coords_id` = Spawned on `olympia` plan (or faction respawn plan)
- No entry in `tutorial_progress` table
- No tutorial session ID in PHP `$_SESSION`

**Current Location:** `register.php:104-132`

**Issues:**
- ❌ Player is placed on `olympia` instead of a isolated "waiting" plan
- ❌ `invisibleMode` makes player invisible to others, but they can still wander olympia
- ✅ Old tutorial action removed properly

#### 2. INVISIBLE_WAITING
**Characteristics:**
- Player has logged in but hasn't started tutorial
- `invisibleMode` = `true`
- No active tutorial session
- `$_SESSION['auto_start_tutorial']` may be set

**Current Location:** `index.php:89-142`

**Behavior:**
- **Brand new players** (never started tutorial): Auto-start with loading overlay
- **Returning players** (abandoned tutorial): Show modal with Resume/Skip options

**Issues:**
- ✅ Logic correctly distinguishes brand new vs returning players
- ⚠️ Modal text says "Tutoriel non terminé" but doesn't explain XP/PI loss on skip
- ⚠️ Players can access game UI while invisible (risky)

#### 3. IN_TUTORIAL
**Characteristics:**
- Active tutorial session exists in `tutorial_progress` table
- `$_SESSION['in_tutorial']` = `true`
- `$_SESSION['tutorial_session_id']` = session UUID
- `$_SESSION['tutorial_player_id']` = tutorial character ID
- Tutorial player exists in `tutorial_players` table
- Tutorial player character exists in `players` table on `tutorial` plan

**Current Location:** `TutorialManager.php:56-103` (startTutorial)

**Behavior:**
- Tutorial player controls separate character on isolated map
- Main player remains on olympia (or respawn plan)
- Progress tracked in `tutorial_progress.current_step`

**Issues:**
- ✅ Isolation works correctly
- ✅ Session management clean
- ⚠️ If browser crashes/closes, resume logic needed

#### 4. TUTORIAL_COMPLETED
**Characteristics:**
- `tutorial_progress.completed` = `1`
- `tutorial_progress.completed_at` = timestamp
- Tutorial player deleted
- Tutorial map instance deleted
- XP/PI transferred to main player (first time only)
- `invisibleMode` removed from main player
- Race actions added to main player

**Current Location:** `TutorialManager.php:423-484` (completeTutorial)

**Expected Final State:**
- Main player at race starting position (NOT olympia)
- `invisibleMode` = `false`
- XP/PI from tutorial added
- Race actions available

**Issues:**
- ❌ **CRITICAL**: Player placement NOT enforced on completion
  - Should move to race starting position (Humans → olympia, Dwarves → mines, etc.)
  - Currently stays at olympia (registration spawn point)
- ✅ XP/PI transfer works correctly
- ✅ Replay detection prevents duplicate rewards
- ✅ Race actions added correctly

#### 5. TUTORIAL_CANCELLED
**Characteristics:**
- `tutorial_progress.completed` = `1` (marked as completed with cancel flag)
- Tutorial player deleted
- Tutorial map instance deleted
- NO XP/PI transferred
- `invisibleMode` removed from main player
- Race actions added to main player

**Current Location:** `api/tutorial/cancel.php:32-144`

**Expected Final State:**
- Main player at race starting position (NOT olympia)
- `invisibleMode` = `false`
- No XP/PI from tutorial
- **Should receive fixed "skip reward"** (configurable by admin)
- Race actions available

**Issues:**
- ❌ **CRITICAL**: No placement logic in cancel flow
- ❌ **MISSING**: No fixed "skip reward" XP/PI grant
- ❌ **MISSING**: Skip reward not configurable by admin
- ✅ Cleanup works correctly
- ⚠️ Cancel and Skip are separate endpoints but do same thing

#### 6. ACTIVE_PLAYER
**Characteristics:**
- `invisibleMode` = `false`
- Player can interact normally
- Other players can see them
- Located at race starting position

**Current Location:** Normal game state

**Issues:**
- ⚠️ If placement wasn't done, player might be in wrong location

---

## Flow Diagrams

### Registration → First Login Flow

```
[User registers]
  ↓
register.php:
  - Create player with Player::put_player()
  - Spawn on olympia (respawnPlan from faction JSON)
  - Add invisibleMode option
  - NO tutorial session created yet
  ↓
[Player redirected to index.php with login]
  ↓
index.php:
  - Check: TutorialFeatureFlag enabled? YES
  - Check: hasCompletedBefore? NO
  - Check: activeSession? NO
  - → isBrandNew = true
  - Set $_SESSION['auto_start_tutorial'] = true
  - Show loading overlay
  ↓
TutorialInit.js (on page load):
  - Check sessionStorage.tutorial_just_started? NO
  - Check $_SESSION['auto_start_tutorial']? YES
  - Call tutorialUI.start()
  ↓
api/tutorial/start.php:
  - Clean up any previous sessions
  - Create tutorial session in tutorial_progress
  - Create tutorial player on isolated 'tutorial' plan
  - Create tutorial enemy
  - Set $_SESSION['in_tutorial'] = true
  - Set $_SESSION['tutorial_player_id']
  - Return reload_required = true
  ↓
[Page reloads]
  ↓
index.php (after reload):
  - TutorialHelper::getActivePlayerId() returns tutorial player ID
  - Render map for tutorial player (isolated map)
  ↓
TutorialInit.js (after reload):
  - Detect sessionStorage.tutorial_active = 'true'
  - Call tutorialUI.resume()
  ↓
api/tutorial/resume.php:
  - Load session from tutorial_progress
  - Load tutorial player
  - Return current step data
  ↓
[Tutorial UI renders first step]
```

### Tutorial Completion Flow (Nominal Case)

```
[Player completes final step validation]
  ↓
TutorialUI.next():
  - Call api/tutorial/advance.php
  ↓
TutorialManager.advanceStep():
  - Detect: No next_step in current step
  - Call completeTutorial()
  ↓
TutorialManager.completeTutorial():
  - Check if replay (hasCompletedBefore)
  - If first time:
    - Transfer XP/PI to main player
    - Remove invisibleMode from main player
    - Add race actions to main player
    ❌ MISSING: Move main player to race starting position
  - Delete tutorial player
  - Delete tutorial map instance
  - Mark session as completed
  - Clear $_SESSION tutorial vars
  ↓
[Completion message shown]
  ↓
[Page reloads]
  ↓
❌ ISSUE: Player still on olympia instead of race start
```

### Tutorial Cancellation Flow

```
[Player clicks "Annuler le tutoriel" in UI OR "Passer le tutoriel" in modal]
  ↓
TutorialUI.cancel():
  - Show confirmation dialog
  - Call api/tutorial/cancel.php
  ↓
api/tutorial/cancel.php:
  - Get tutorial player for session
  - Delete tutorial enemy
  - Delete tutorial player
  - Delete tutorial map instance
  - Mark session as completed
  - Clear $_SESSION tutorial vars
  ❌ MISSING: Move main player to race starting position
  ❌ MISSING: Grant fixed "skip reward" XP/PI
  ↓
[Success message shown]
  ↓
[Page reloads]
  ↓
❌ ISSUE: Player still on olympia
❌ ISSUE: No skip rewards granted
```

### Tutorial Interruption/Resume Flow

```
[Player in tutorial, closes browser or navigates away]
  ↓
sessionStorage.tutorial_active = 'true' persists
$_SESSION vars cleared when session expires
  ↓
[Player logs back in]
  ↓
index.php:
  - Check invisibleMode? YES
  - Check isAdmin? NO
  - Check inTutorial (from $_SESSION)? NO (session expired)
  - Check isBrandNew? NO (activeSession exists in DB)
  - Check autoStarting? NO
  - → Show modal "Tutoriel non terminé"
  ↓
[Player clicks "Reprendre le tutoriel"]
  ↓
resume-tutorial-btn click handler:
  - Set $_SESSION['auto_start_tutorial'] = true
  - Reload page
  ↓
index.php (after reload):
  - autoStarting = true
  - Don't show modal
  - Let TutorialInit handle resume
  ↓
TutorialInit.js:
  - Detect sessionStorage.tutorial_active = 'true'
  - Call tutorialUI.resume()
  ↓
api/tutorial/resume.php:
  - Load session from tutorial_progress
  - Load tutorial player
  - Set $_SESSION['in_tutorial'] = true
  - Return reload_required = true
  ↓
[Page reloads to switch to tutorial map]
  ↓
[Tutorial resumes at last step]
```

### Skip from Modal Flow

```
[Returning player with incomplete tutorial]
  ↓
index.php:
  - Show modal "Tutoriel non terminé"
  ↓
[Player clicks "Passer le tutoriel"]
  ↓
skip-tutorial-btn click handler:
  - Call api/tutorial/skip.php
  ↓
api/tutorial/skip.php:
  - Check invisibleMode? YES
  - Add race actions to main player
  - Remove invisibleMode
  ❌ MISSING: Move main player to race starting position
  ❌ MISSING: Grant fixed "skip reward" XP/PI
  ↓
[Success message shown]
  ↓
[Page reloads]
  ↓
❌ ISSUE: Player still on olympia
❌ ISSUE: No skip rewards granted
```

---

## Critical Issues & Recommendations

### Issue #1: Player Placement on Olympia Instead of Waiting Area

**Current Behavior:**
- New players spawn on `olympia` plan with `invisibleMode`
- They can move around olympia while invisible
- Risky: might interact with game elements before tutorial

**Recommended Solution:**
Create a dedicated `waiting` plan:

```php
// In register.php:
if ($useNewTutorial) {
    // Remove old tutorial action
    $player->end_action('tuto/attaquer');

    // Spawn on WAITING plan (not olympia)
    $goCoords = (object) array(
        'x' => 0,
        'y' => 0,
        'z' => 0,
        'plan' => 'waiting'
    );

    $coordsId = View::get_free_coords_id_arround($goCoords);

    // Update player's coordinates
    $sql = 'UPDATE players SET coords_id = ? WHERE id = ?';
    $db->exe($sql, array($coordsId, $player->id));

    // Enable invisibleMode
    $player->add_option('invisibleMode');

    // Enable tutorialWaiting flag to prevent game interactions
    $player->add_option('tutorialWaiting');
}
```

Create `datas/private/plans/waiting.json`:
```json
{
    "player_visibility": false,
    "player_movement": false,
    "biomes": [],
    "description": "Salle d'attente du tutoriel"
}
```

### Issue #2: Missing Player Placement on Completion/Cancellation

**Current Behavior:**
- Player stays on olympia after completing or skipping tutorial
- Should be moved to race-specific starting position

**Recommended Solution:**
Add placement logic to both completion and cancellation flows:

```php
// In TutorialManager.php::completeTutorial() and api/tutorial/cancel.php:

/**
 * Move player to race starting position
 */
private function moveToRaceStartingPosition(int $playerId): void
{
    $player = new \Classes\Player($playerId);
    $player->get_data();

    $raceJson = json()->decode('races', $player->data->race);
    $factionJson = json()->decode('factions', $player->data->faction);

    // Get spawn plan (defaults to olympia)
    $spawnPlan = $factionJson->respawnPlan ?? "olympia";

    // Get spawn coordinates (defaults to 0,0)
    $spawnX = $raceJson->startX ?? $factionJson->startX ?? 0;
    $spawnY = $raceJson->startY ?? $factionJson->startY ?? 0;

    $goCoords = (object) array(
        'x' => $spawnX,
        'y' => $spawnY,
        'z' => 0,
        'plan' => $spawnPlan
    );

    $coordsId = View::get_free_coords_id_arround($goCoords);

    // Update player's coordinates
    $db = new \Classes\Db();
    $sql = 'UPDATE players SET coords_id = ? WHERE id = ?';
    $db->exe($sql, array($coordsId, $playerId));

    // Remove tutorialWaiting flag if it exists
    $player->end_option('tutorialWaiting');

    error_log("[Tutorial] Moved player {$playerId} to race starting position: ({$spawnX}, {$spawnY}) on {$spawnPlan}");
}
```

### Issue #3: Missing Skip Reward System

**Current Behavior:**
- Players who skip tutorial get nothing
- No compensation for missing tutorial XP/PI

**Recommended Solution:**

1. Add admin-configurable skip reward constant:

```php
// In config/constants.php:
define('TUTORIAL_SKIP_REWARD', [
    'xp' => 50,  // Fixed XP for skipping (instead of ~300 from tutorial)
    'pi' => 50   // Fixed PI for skipping
]);
```

2. Grant skip reward in both skip and cancel flows:

```php
// In api/tutorial/skip.php and api/tutorial/cancel.php:

// Grant skip reward
$skipReward = TUTORIAL_SKIP_REWARD;
$player->putBonus([
    'xp' => $skipReward['xp'],
    'pi' => $skipReward['pi']
]);

error_log("[Tutorial Skip] Player {$playerId} received skip reward: {$skipReward['xp']} XP, {$skipReward['pi']} PI");
```

3. Update modal text to explain:

```html
<p>Si tu passes le tutoriel, tu recevras <?php echo TUTORIAL_SKIP_REWARD['xp']; ?> XP
   et <?php echo TUTORIAL_SKIP_REWARD['pi']; ?> PI (au lieu des récompenses complètes du tutoriel).</p>
```

### Issue #4: Duplicate Skip/Cancel Buttons

**Current Behavior:**
- "Annuler le tutoriel" button in tutorial UI
- "Passer le tutoriel" button in modal
- Both do essentially the same thing (cancel tutorial)

**Recommended Solution:**

**Keep**: "Annuler" button in active tutorial UI (for aborting mid-tutorial)
**Remove**: Separate "Passer" endpoint
**Consolidate**: Both buttons call same `cancel.php` endpoint

Update modal buttons:
```html
<button id="resume-tutorial-btn">Reprendre le tutoriel</button>
<button id="cancel-tutorial-btn">Abandonner le tutoriel (et recevoir <?php echo TUTORIAL_SKIP_REWARD['xp']; ?> XP)</button>
```

Update button handler:
```javascript
$('#cancel-tutorial-btn').click(async function() {
    if (!confirm('Êtes-vous sûr ? Tu abandonneras le tutoriel et recevras seulement ' + TUTORIAL_SKIP_REWARD_XP + ' XP au lieu de la récompense complète.')) {
        return;
    }

    // Call same cancel endpoint
    await $.post('/api/tutorial/cancel.php', {});
    window.location.reload();
});
```

Delete `api/tutorial/skip.php` entirely (consolidate into `cancel.php`).

### Issue #5: Unclear Modal Messaging

**Current Behavior:**
- Modal says "Tutoriel non terminé" but doesn't explain consequences

**Recommended Solution:**
Improve modal messaging:

```html
<div>
    <h2>Bienvenue !</h2>
    <p>Tu as commencé le tutoriel mais ne l'as pas terminé.</p>
    <p><strong>Options :</strong></p>
    <ul style="text-align: left; display: inline-block;">
        <li><strong>Reprendre :</strong> Continue le tutoriel où tu l'as laissé (recommandé)</li>
        <li><strong>Abandonner :</strong> Commence le jeu immédiatement
            <br><small>Tu recevras <?php echo TUTORIAL_SKIP_REWARD['xp']; ?> XP au lieu de la récompense complète (~300 XP)</small>
        </li>
    </ul>
</div>
```

---

## Testing Plan

### Test Suite 1: Registration & First Login

**Test 1.1: New Player Registration**
- ✅ Player created in `players` table
- ✅ `invisibleMode` enabled
- ❌ Player spawned on `waiting` plan (not olympia)
- ✅ No tutorial session created yet

**Test 1.2: Brand New First Login**
- ✅ Loading overlay shown
- ✅ `auto_start_tutorial` flag set
- ✅ Tutorial auto-starts
- ✅ Tutorial player created on `tutorial` plan
- ✅ Page reloads to tutorial map

**Test 1.3: Tutorial Player Isolation**
- ✅ Tutorial player on separate plan instance
- ✅ Main player still on waiting plan
- ✅ Tutorial player has basic actions
- ✅ Tutorial enemy spawned

### Test Suite 2: Tutorial Completion

**Test 2.1: First-Time Completion**
- ✅ All steps completed
- ✅ XP/PI transferred to main player
- ✅ `invisibleMode` removed from main player
- ✅ Race actions added to main player
- ❌ Main player moved to race starting position
- ✅ Tutorial player deleted
- ✅ Tutorial map instance deleted
- ✅ Session marked as completed

**Test 2.2: Replay Completion**
- ✅ All steps completed
- ✅ NO XP/PI transferred (already got rewards)
- ✅ Message indicates replay
- ✅ Tutorial player deleted
- ✅ Tutorial map instance deleted

**Test 2.3: XP/PI Amounts**
- Verify exact XP earned matches sum of step rewards
- Verify PI earned equals XP earned (1:1 ratio)

### Test Suite 3: Tutorial Cancellation

**Test 3.1: Cancel from Active Tutorial**
- Click "Annuler" button in UI
- Confirm cancellation
- ❌ Fixed skip reward granted
- ❌ Main player moved to race starting position
- ✅ `invisibleMode` removed
- ✅ Race actions added
- ✅ Tutorial resources cleaned up

**Test 3.2: Cancel from Modal (Returning Player)**
- Login with incomplete tutorial
- Modal shows
- Click "Abandonner"
- ❌ Fixed skip reward granted
- ❌ Main player moved to race starting position
- ✅ `invisibleMode` removed
- ✅ Race actions added

### Test Suite 4: Interruption & Resume

**Test 4.1: Close Browser Mid-Tutorial**
- Start tutorial, complete 5 steps
- Close browser
- Clear PHP session (simulate expiration)
- Login again
- ✅ Modal shows "Tutoriel non terminé"
- ✅ Resume option available

**Test 4.2: Resume from Modal**
- Click "Reprendre le tutoriel"
- ✅ Tutorial resumes at step 6
- ✅ Progress preserved (XP, step number)
- ✅ Tutorial player restored

**Test 4.3: Resume from Auto-Start**
- Have active session in DB
- Have `sessionStorage.tutorial_active = 'true'`
- ✅ Auto-resume on page load
- ✅ No modal shown

### Test Suite 5: Edge Cases

**Test 5.1: Multiple Tab Confusion**
- Open tutorial in two tabs
- Advance in tab A
- Reload tab B
- ✅ Tab B syncs to correct step

**Test 5.2: Session Expired During Tutorial**
- Start tutorial
- Wait for PHP session to expire (or delete cookie)
- Try to advance step
- ✅ Error handling graceful
- ✅ User prompted to log in

**Test 5.3: Database Cleanup After Cancel**
- Cancel tutorial
- Check `tutorial_players` table
- Check `tutorial_enemies` table
- Check `coords` table for orphaned tutorial coords
- ✅ All cleaned up properly

**Test 5.4: Race Action Duplication**
- Complete tutorial (get race actions)
- Replay tutorial (should not duplicate actions)
- ✅ `have_action()` check prevents duplicates

---

## Implementation Priority

### Phase 1: Critical Fixes (Must-Have)
1. ✅ **Issue #2**: Add player placement on completion/cancellation
2. ✅ **Issue #3**: Add skip reward system
3. ⚠️ **Issue #1**: Move new players to waiting plan (optional but recommended)

### Phase 2: UX Improvements (Should-Have)
4. ✅ **Issue #4**: Consolidate skip/cancel buttons
5. ✅ **Issue #5**: Improve modal messaging

### Phase 3: Testing & Validation (Must-Have)
6. ✅ Write automated tests for all flows
7. ✅ Manual E2E testing with screenshots
8. ✅ Load testing (multiple concurrent tutorial players)

---

## Summary

The tutorial system has a solid foundation but needs **4 critical fixes**:

1. **Player placement**: Move to race starting position on completion/cancellation
2. **Skip rewards**: Grant fixed XP/PI when skipping instead of nothing
3. **Waiting plan**: Isolate new players before tutorial starts (recommended)
4. **UI consolidation**: Merge skip/cancel flows and improve messaging

With these fixes, the tutorial workflow will be smooth and handle all edge cases properly.
