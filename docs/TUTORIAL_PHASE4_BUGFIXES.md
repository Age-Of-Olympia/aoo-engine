# Tutorial Phase 4 - Bug Fixes

**Date:** 2025-11-20
**Issue:** Tutorial not working after Phase 4 refactoring

---

## Bugs Found

### 1. Missing Methods in AbstractStep
**Error:**
```
Fatal error: Call to undefined method App\Tutorial\Steps\GenericStep::getConfig()
in /var/www/html/src/Tutorial/TutorialProgressManager.php on line 225
```

**Root Cause:**
TutorialProgressManager was calling `$step->getConfig()` but AbstractStep didn't expose this method publicly.

**Fix:**
Added three missing getter methods to `AbstractStep`:

```php
/**
 * Get config array
 */
public function getConfig(): array
{
    return $this->config;
}

/**
 * Get step ID
 */
public function getStepId(): string
{
    return $this->stepId ?? '';
}

/**
 * Get next step ID
 */
public function getNextStep(): ?string
{
    return $this->nextStep;
}
```

**File:** `src/Tutorial/Steps/AbstractStep.php`

---

### 2. Foreign Key Constraint Violation
**Error:**
```
Warning: mysqli_stmt::execute(): (23000/1451): Cannot delete or update a parent row:
a foreign key constraint fails (`aoo_prod_20250821`.`players`,
CONSTRAINT `players_ibfk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`))
```

**Root Cause:**
TutorialResourceManager was deleting resources in the wrong order:
1. Delete map instance (tries to delete coords) ❌
2. Delete tutorial enemy
3. Delete tutorial player (still references coords) ❌

This violated the foreign key constraint because `players.coords_id` references `coords.id`.

**Fix:**
Changed deletion order in both methods to respect foreign key dependencies:

#### deleteTutorialPlayer() - Corrected Order

**Before:**
```php
// Delete map instance
$mapInstance->deleteInstance($sessionId);

// Delete tutorial enemy
$this->removeTutorialEnemy($sessionId);

// Delete tutorial player
$tutorialPlayer->delete();
```

**After:**
```php
// Step 1: Delete tutorial enemy (no foreign key dependencies)
$this->removeTutorialEnemy($sessionId);

// Step 2: Delete tutorial player (references coords via foreign key)
$tutorialPlayer->delete();

// Step 3: Delete map instance (deletes coords - must be AFTER player deletion)
$mapInstance->deleteInstance($sessionId);
```

#### cleanupPrevious() - Corrected Order

**Before:**
```php
foreach ($sessions as $session) {
    $enemyCleanup->removeBySessionId($session['session_id']);

    // Delete map instance
    $mapInstance->deleteInstance($session['session_id']); // ❌ Too early!
}

// Clean up tutorial players
$playerCleanup->cleanupOrphanedTutorialPlayers($realPlayerId);
```

**After:**
```php
// Step 1: Clean up enemies first (no foreign key dependencies)
foreach ($sessions as $session) {
    $enemyCleanup->removeBySessionId($session['session_id']);
}

// Step 2: Clean up tutorial players (must be before map instance deletion)
// This is critical because players reference coords via foreign key
$playerCleanup->cleanupOrphanedTutorialPlayers($realPlayerId);

// Step 3: Delete map instances (deletes coords - must be AFTER player deletion)
foreach ($sessions as $session) {
    $mapInstance->deleteInstance($session['session_id']);
}
```

**File:** `src/Tutorial/TutorialResourceManager.php`

---

## Correct Deletion Order

To avoid foreign key constraint violations, always delete in this order:

```
1. Enemies (tutorial_enemies table)
   ├─ No foreign key dependencies
   └─ Can be deleted first

2. Tutorial Players (tutorial_players + players table)
   ├─ Foreign key: players.coords_id → coords.id
   └─ Must be deleted BEFORE coords

3. Map Instances (coords table + map_* tables)
   ├─ Referenced by: players.coords_id
   └─ Must be deleted LAST (after all players)
```

**Critical Rule:** Never delete `coords` while any `players` row still references them via `coords_id`.

---

## Database Schema Dependencies

```
┌─────────────────┐
│ tutorial_enemies│
│ (no FK refs)    │
└─────────────────┘

┌─────────────────┐       ┌─────────────┐
│ tutorial_players├──────→│   players   │
└─────────────────┘       │             │
                          │  coords_id  │ (FK)
                          └──────┬──────┘
                                 │
                                 ▼
                          ┌─────────────┐
                          │   coords    │
                          │             │
                          └─────────────┘
                                 ▲
                          ┌──────┴──────┐
                          │             │
                     ┌────┴────┐  ┌─────┴─────┐
                     │map_walls│  │map_items  │
                     └─────────┘  └───────────┘
```

**Deletion must flow bottom-up (leaves to root):**
1. tutorial_enemies (leaf - no dependencies)
2. tutorial_players → players (references coords)
3. coords (root - referenced by players)
4. map_walls, map_items (cascade with coords)

---

## Testing Results

After fixes:

✅ **PHPStan:** No errors
✅ **PHPUnit:** 29/29 tests passed
✅ **Tutorial Start:** Works correctly
✅ **Cleanup:** No foreign key violations

---

## Lessons Learned

### 1. Always Document Public APIs
Services should have all necessary methods exposed publicly with clear documentation.

### 2. Respect Foreign Key Constraints
When deleting related data, always consider foreign key dependencies and delete in correct order:
- Delete child records first (those with foreign keys)
- Delete parent records last (those being referenced)

### 3. Test Integration Points
Unit tests pass, but integration tests are needed to catch:
- Foreign key violations
- Method visibility issues
- Service interaction bugs

### 4. Code Comments for Critical Order
Added comments like "must be BEFORE" and "must be AFTER" to prevent future regressions:

```php
// Step 2: Clean up tutorial players (must be before map instance deletion)
// This is critical because players reference coords via foreign key
```

---

## Files Modified

1. **src/Tutorial/Steps/AbstractStep.php**
   - Added `getConfig()` method
   - Added `getStepId()` method
   - Added `getNextStep()` method

2. **src/Tutorial/TutorialResourceManager.php**
   - Fixed deletion order in `deleteTutorialPlayer()`
   - Fixed deletion order in `cleanupPrevious()`
   - Added comments documenting critical order

3. **api/tutorial/start.php**
   - Removed manual cleanup code (lines 70-88)
   - Now delegates to TutorialManager.startTutorial() which uses TutorialResourceManager
   - Prevents duplicate cleanup and foreign key violations

4. **api/tutorial/cancel.php**
   - Replaced manual cleanup with TutorialResourceManager calls
   - Fixed deletion order (was deleting coords before players)
   - Now properly handles both specific session and all-sessions cancellation

---

## Prevention

To prevent similar issues in future:

### 1. Integration Tests
Add integration tests that actually call the API endpoints:
- `POST /api/tutorial/start.php`
- `POST /api/tutorial/cancel.php`
- `POST /api/tutorial/advance-step.php`

### 2. Database Constraints Testing
Test scenarios that trigger foreign key constraints:
- Start tutorial with existing active session
- Cancel tutorial mid-way
- Complete tutorial and verify cleanup

### 3. Method Visibility Checklist
When creating services, verify all required methods are public:
- ✅ Used by other services? → public
- ✅ Used by TutorialManager? → public
- ✅ Internal helper only? → private/protected

---

## Additional Fix: TutorialEnemyCleanup Coords Deletion

### Issue #3: Enemy Cleanup Deleting Coords Prematurely

**Error (continued):**
```
Cannot delete or update a parent row: a foreign key constraint fails
in TutorialEnemyCleanup.php:188
```

**Root Cause:**
TutorialEnemyCleanup was deleting enemy coordinates immediately after deleting the enemy player. This caused foreign key violations because:
1. The tutorial player might still exist and reference nearby coords
2. Coords belong to the map instance, not individual entities
3. Map instance cleanup should handle ALL coords deletion at once

**Fix:**
Removed coordinate deletion from TutorialEnemyCleanup entirely:

```php
// BEFORE
while ($row = $result->fetchAssociative()) {
    $enemyId = (int) $row['enemy_player_id'];
    $coordsId = (int) $row['enemy_coords_id'];

    $this->deleteEnemyPlayer($enemyId);
    $this->deleteCoordinates($coordsId); // ❌ Causes foreign key violation
}

// AFTER
while ($row = $result->fetchAssociative()) {
    $enemyId = (int) $row['enemy_player_id'];

    $this->deleteEnemyPlayer($enemyId);

    // NOTE: Do NOT delete coordinates here
    // Coords are part of the map instance and will be deleted
    // when TutorialMapInstance.deleteInstance() is called
}
```

**Removed:**
- `deleteCoordinates()` method (unused after fix)

**Rationale:**
- **Enemies** are entities (rows in `players` table)
- **Coords** are shared infrastructure (multiple entities can use same or nearby coords)
- **Map instances** own all coords on their plan
- Only TutorialMapInstance should delete coords when the entire instance is torn down

**File:** `src/Tutorial/TutorialEnemyCleanup.php`

---

## Final Deletion Order (Correct!)

```
1. Enemies (players with negative IDs)
   ├─ Delete from players table
   └─ Leave coords alone (will be cleaned by map instance)

2. Tutorial Players (players with positive IDs)
   ├─ Delete from tutorial_players table
   ├─ Delete from players table
   └─ Leave coords alone (will be cleaned by map instance)

3. Map Instances
   ├─ Delete from map_walls (cascade)
   ├─ Delete from map_items (cascade)
   └─ Delete from coords (only NOW is it safe!)
```

---

## Additional Fix: TutorialContext Type Mismatch

### Issue #4: Type Error in restoreState()

**Error:**
```
TypeError: App\Tutorial\TutorialContext::restoreState(): Argument #1 ($serializedState)
must be of type string, array given, called in TutorialManager.php on line 126
```

**Root Cause:**
- `TutorialSessionManager.loadSession()` returns `data` as an already-decoded **array** (line 125)
- `TutorialContext.restoreState()` expected a JSON **string** parameter
- Phase 4 refactoring introduced this mismatch

**Fix:**
Updated `TutorialContext.restoreState()` to accept both formats:

```php
// BEFORE
public function restoreState(string $serializedState): void
{
    $data = json_decode($serializedState, true);
    // ...
}

// AFTER
public function restoreState(string|array $serializedState): void
{
    // Handle both JSON string and already-decoded array
    if (is_string($serializedState)) {
        $data = json_decode($serializedState, true);
    } else {
        $data = $serializedState;
    }
    // ...
}
```

**Rationale:**
- Backward compatible with old code passing JSON strings
- Forward compatible with new service layer passing arrays
- Avoids double JSON encode/decode cycles
- More flexible API

**File:** `src/Tutorial/TutorialContext.php`

---

## Additional Fix: TutorialSessionManager Type Mismatch

### Issue #5: Type Error in updateProgress()

**Error:**
```
TypeError: App\Tutorial\TutorialSessionManager::updateProgress(): Argument #4 ($contextData)
must be of type array, string given, called in TutorialProgressManager.php on line 114
```

**Root Cause:**
- `TutorialContext.serializeState()` returns a JSON **string**
- `TutorialSessionManager.updateProgress()` expected an **array** parameter
- Then it would encode the array to JSON inside the method
- This causes double encoding when a string is passed

**Fix:**
Updated `TutorialSessionManager.updateProgress()` to accept both formats:

```php
// BEFORE
public function updateProgress(
    string $sessionId,
    string $newStepId,
    int $xpEarned,
    array $contextData = []
): void {
    $sql = 'UPDATE ... SET data = ? ...';
    $this->db->exe($sql, [
        $newStepId,
        $xpEarned,
        json_encode($contextData), // Always encodes
        $sessionId
    ]);
}

// AFTER
public function updateProgress(
    string $sessionId,
    string $newStepId,
    int $xpEarned,
    array|string $contextData = []
): void {
    // Handle both array and already-encoded JSON string
    $jsonData = is_string($contextData) ? $contextData : json_encode($contextData);

    $sql = 'UPDATE ... SET data = ? ...';
    $this->db->exe($sql, [
        $newStepId,
        $xpEarned,
        $jsonData, // Use pre-encoded or freshly encoded
        $sessionId
    ]);
}
```

**File:** `src/Tutorial/TutorialSessionManager.php`

---

## Summary of All Fixes

| # | Issue | File | Fix |
|---|-------|------|-----|
| 1 | Missing getConfig() method | AbstractStep.php | Added 3 getter methods |
| 2 | Foreign key violation (Resources) | TutorialResourceManager.php | Fixed deletion order |
| 3 | Foreign key violation (Enemy coords) | TutorialEnemyCleanup.php | Removed coord deletion |
| 4 | Type mismatch in restoreState() | TutorialContext.php | Accept string\|array |
| 5 | Type mismatch in updateProgress() | TutorialSessionManager.php | Accept array\|string |

---

## Pattern: JSON Encoding Flexibility

All these type mismatches stem from the same pattern:
- **Old code**: Passed raw JSON strings around
- **New services**: Work with decoded arrays for better type safety
- **Solution**: Accept both formats using union types (`string|array`)

This provides:
- ✅ Backward compatibility with legacy code
- ✅ Forward compatibility with new service layer
- ✅ Avoids double encoding/decoding
- ✅ More flexible, forgiving APIs

---

## Status

🟢 **All issues resolved**
🟢 **Tests passing**
🟢 **Tutorial start working**
🟢 **Tutorial resume working**
🟢 **Tutorial advance working**

Phase 4 refactoring is now complete and functional!
