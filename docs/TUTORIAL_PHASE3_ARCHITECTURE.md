# Tutorial System Phase 3 - Service Architecture

**Date:** 2025-11-20
**Status:** COMPLETED - New services created
**Next Step:** Refactor TutorialManager to use new services (Phase 4)

---

## Overview

Phase 3 splits the monolithic `TutorialManager` (865 lines, 15+ responsibilities) into three focused services following the Single Responsibility Principle.

---

## New Service Architecture

### 1. TutorialSessionManager (330 lines)
**File:** `src/Tutorial/TutorialSessionManager.php`

**Single Responsibility:** Session Lifecycle Management

**Methods:**
- `createSession()` - Create new tutorial session
- `loadSession()` - Load existing session by ID
- `getActiveSession()` - Get active session for player
- `updateProgress()` - Update current step and XP
- `completeSession()` - Mark session as completed
- `cancelSession()` - Cancel/abandon session
- `hasCompletedBefore()` - Check if player completed before
- `isSessionActive()` - Validate session exists and is active
- `generateSessionId()` - Generate UUID v4
- `getStatistics()` - Get session statistics for debugging

**Database Tables:**
- `tutorial_progress` (primary)

**Dependencies:**
- `Classes\Db`
- `TutorialException`

**Key Features:**
- Clean API for session operations
- Proper exception handling with context
- Session validation methods
- Statistics/analytics support

---

### 2. TutorialProgressManager (240 lines)
**File:** `src/Tutorial/TutorialProgressManager.php`

**Single Responsibility:** Step Progression & Validation

**Methods:**
- `advanceStep()` - Validate current step, move to next
- `getCurrentStepForClient()` - Get step data for rendering
- `jumpToStep()` - Debug/testing step navigation
- `calculateStepPosition()` - Get step number for display
- `applyStepPrerequisites()` - Set up step requirements (private)
- `prepareStepForClient()` - Format step data (private)
- `loadStep()` - Load step from repository (private)

**Dependencies:**
- `TutorialContext` - Player state
- `TutorialStepRepository` - Step data access
- `TutorialSessionManager` - Session updates
- `TutorialStepFactory` - Step instantiation

**Key Features:**
- Coordinates step validation
- Awards XP on step completion
- Applies context changes
- Handles step prerequisites
- Prepares client-ready step data

---

### 3. TutorialResourceManager (280 lines)
**File:** `src/Tutorial/TutorialResourceManager.php`

**Single Responsibility:** Resource Lifecycle Management

**Methods:**
- `createTutorialPlayer()` - Create player + map instance + enemy
- `deleteTutorialPlayer()` - Delete player and all resources
- `cleanupPrevious()` - Clean up orphaned players/enemies
- `getTutorialPlayer()` - Load tutorial player for session
- `spawnTutorialEnemy()` - Create enemy NPC (private)
- `removeTutorialEnemy()` - Delete enemy NPC (private)

**Resources Managed:**
- Tutorial players (temporary characters)
- Tutorial enemies (training NPCs)
- Map instances (isolated tutorial maps)

**Dependencies:**
- `TutorialPlayer`
- `TutorialMapInstance`
- `TutorialEnemyCleanup` (from Phase 1)
- `TutorialPlayerCleanup` (from Phase 2)
- `TutorialConstants` (from Phase 2)

**Key Features:**
- Complete resource lifecycle
- Atomic creation (all-or-nothing)
- Comprehensive cleanup
- Isolation between concurrent tutorials

---

## Service Interaction Diagram

```
┌─────────────────────┐
│  TutorialManager    │  (Facade - coordinates services)
│  (To be refactored) │
└──────────┬──────────┘
           │
           ├─────────────┬───────────────┬─────────────────┐
           │             │               │                 │
           ▼             ▼               ▼                 ▼
┌──────────────────┐ ┌──────────┐ ┌──────────────┐ ┌──────────────┐
│ SessionManager   │ │Progress  │ │  Resource    │ │   Context    │
│                  │ │Manager   │ │  Manager     │ │              │
├──────────────────┤ ├──────────┤ ├──────────────┤ ├──────────────┤
│• createSession   │ │• advance │ │• createPlayer│ │• state       │
│• loadSession     │ │  Step    │ │• deleteplayer│ │• tracking    │
│• updateProgress  │ │• validate│ │• spawnEnemy  │ │• progression │
│• complete        │ │• getStep │ │• cleanup     │ │              │
└──────────────────┘ └──────────┘ └──────────────┘ └──────────────┘
         │                 │               │
         │                 │               │
         ▼                 ▼               ▼
┌──────────────────────────────────────────────────┐
│           Database (tutorial_progress,           │
│   tutorial_players, tutorial_enemies, etc.)      │
└──────────────────────────────────────────────────┘
```

---

## Refactoring Guide (Phase 4)

### Step 1: Update TutorialManager Constructor

**Before:**
```php
public function __construct(Player $player, string $mode = 'first_time')
{
    $this->context = new TutorialContext($player, $mode);
    $this->sessionId = $this->generateSessionId();
    $this->db = new Db();
    $this->stepRepository = new TutorialStepRepository();
}
```

**After:**
```php
private TutorialSessionManager $sessionManager;
private TutorialProgressManager $progressManager;
private TutorialResourceManager $resourceManager;

public function __construct(Player $player, string $mode = 'first_time')
{
    $this->context = new TutorialContext($player, $mode);
    $this->stepRepository = new TutorialStepRepository();

    // Initialize service layer
    $this->sessionManager = new TutorialSessionManager();
    $this->resourceManager = new TutorialResourceManager();
    $this->progressManager = new TutorialProgressManager(
        $this->context,
        $this->stepRepository,
        $this->sessionManager
    );
}
```

---

### Step 2: Refactor startTutorial()

**Before** (60 lines):
```php
public function startTutorial(string $version = '1.0.0'): array
{
    // 1. Get player data
    // 2. Cleanup previous sessions
    // 3. Create tutorial player + map + enemy
    // 4. Insert into tutorial_progress
    // 5. Load first step
    // 6. Return session data
}
```

**After** (20 lines):
```php
public function startTutorial(string $version = '1.0.0'): array
{
    $player = $this->context->getPlayer();

    // Cleanup previous sessions
    $this->resourceManager->cleanupPrevious($player->id);

    // Create session
    $firstStep = $this->stepRepository->getStepByNumber(0, $version);
    $totalSteps = $this->stepRepository->getTotalSteps($version);
    $session = $this->sessionManager->createSession(
        $player->id,
        $this->context->getMode(),
        $version,
        $totalSteps,
        $firstStep['step_id']
    );

    // Create resources
    $tutorialPlayer = $this->resourceManager->createTutorialPlayer(
        $player->id,
        $session['session_id']
    );

    // Get first step data
    $stepData = $this->progressManager->getCurrentStepForClient(
        $firstStep['step_id'],
        $version,
        true // apply prerequisites
    );

    return array_merge($session, ['step_data' => $stepData]);
}
```

---

### Step 3: Refactor resumeTutorial()

**Before** (50 lines):
```php
public function resumeTutorial(string $sessionId): array
{
    // 1. Load session from DB
    // 2. Restore context state
    // 3. Load tutorial player
    // 4. Get current step
    // 5. Apply prerequisites
    // 6. Return session + step data
}
```

**After** (15 lines):
```php
public function resumeTutorial(string $sessionId): array
{
    // Load session
    $session = $this->sessionManager->loadSession($sessionId);

    if (!$session) {
        throw new TutorialSessionException("Session not found: {$sessionId}");
    }

    // Restore context
    $this->context->restoreState($session['data']);

    // Load tutorial player
    $tutorialPlayer = $this->resourceManager->getTutorialPlayer($sessionId);

    // Get current step
    $stepData = $this->progressManager->getCurrentStepForClient(
        $session['current_step'],
        $session['version'],
        true // apply prerequisites
    );

    return array_merge($session, ['step_data' => $stepData]);
}
```

---

### Step 4: Refactor advanceStep()

**Before** (110 lines):
```php
public function advanceStep(array $validationData = []): array
{
    // 1. Load current step
    // 2. Validate
    // 3. Award XP
    // 4. Apply context changes
    // 5. Get next step
    // 6. Apply prerequisites
    // 7. Update database
    // 8. Check if completed
    // 9. Return next step or completion
}
```

**After** (30 lines):
```php
public function advanceStep(array $validationData = []): array
{
    $session = $this->sessionManager->loadSession($this->sessionId);

    // Delegate to ProgressManager
    $result = $this->progressManager->advanceStep(
        $this->sessionId,
        $session['current_step'],
        $session['version'],
        $validationData
    );

    // If tutorial completed, handle completion
    if ($result['completed']) {
        return $this->completeTutorial();
    }

    return $result;
}
```

---

### Step 5: Refactor completeTutorial()

**Before** (75 lines):
```php
private function completeTutorial(): array
{
    // 1. Delete map instance
    // 2. Remove enemy
    // 3. Transfer rewards
    // 4. Delete tutorial player
    // 5. Mark session complete
    // 6. Return completion data
}
```

**After** (15 lines):
```php
private function completeTutorial(): array
{
    $xpEarned = $this->context->getTutorialXP();
    $piEarned = 0; // From context

    // Delete resources
    if ($this->tutorialPlayer) {
        $this->tutorialPlayer->transferRewardsToRealPlayer($xpEarned, $piEarned);
        $this->resourceManager->deleteTutorialPlayer($this->tutorialPlayer, $this->sessionId);
    }

    // Complete session
    $this->sessionManager->completeSession($this->sessionId, $xpEarned);

    return [
        'success' => true,
        'completed' => true,
        'xp_earned' => $xpEarned,
        'pi_earned' => $piEarned
    ];
}
```

---

## LOC Reduction Estimate

| Method | Before | After | Reduction |
|--------|--------|-------|-----------|
| `startTutorial()` | 60 lines | 20 lines | -40 lines (-67%) |
| `resumeTutorial()` | 50 lines | 15 lines | -35 lines (-70%) |
| `advanceStep()` | 110 lines | 30 lines | -80 lines (-73%) |
| `completeTutorial()` | 75 lines | 15 lines | -60 lines (-80%) |
| Helper methods | 200 lines | 0 lines | -200 lines (moved to services) |
| **Total** | **865 lines** | **~200 lines** | **-665 lines (-77%)** |

---

## Benefits

### 1. Single Responsibility
- Each service has ONE clear purpose
- Easy to understand and test
- Changes are isolated

### 2. Testability
- Services can be unit tested independently
- Mock dependencies easily
- Test business logic without DB/side effects

### 3. Reusability
- SessionManager can be used by admin tools
- ProgressManager can be used for tutorial replay
- ResourceManager can be used for cleanup scripts

### 4. Maintainability
- 200-300 lines per file (vs 865 in one file)
- Clear method names indicate purpose
- Dependencies are explicit

### 5. Extensibility
- Easy to add new session types
- Easy to add new resource types
- Easy to add validation rules

---

## Migration Checklist

### Immediate (Required for services to work):
- [ ] Update `TutorialManager` to use new services
- [ ] Update API endpoints to handle new exceptions
- [ ] Test session creation/loading
- [ ] Test step progression
- [ ] Test resource cleanup

### Short-term (Nice to have):
- [ ] Add unit tests for each service
- [ ] Add integration tests for service interaction
- [ ] Document service APIs
- [ ] Add service health checks

### Long-term (Future enhancements):
- [ ] Extract session validation to separate validator
- [ ] Add caching layer for step data
- [ ] Add metrics/analytics tracking
- [ ] Consider event-driven architecture

---

## Testing Strategy

### Unit Tests (Per Service)

**TutorialSessionManager:**
```php
- testCreateSession()
- testLoadSession()
- testUpdateProgress()
- testCompleteSession()
- testCancelSession()
- testHasCompletedBefore()
- testIsSessionActive()
- testGenerateUniqueSessionIds()
```

**TutorialProgressManager:**
```php
- testAdvanceStepWithValidation()
- testAdvanceStepValidationFailure()
- testJumpToStep()
- testApplyPrerequisites()
- testGetCurrentStepForClient()
- testTutorialCompletion()
```

**TutorialResourceManager:**
```php
- testCreateTutorialPlayer()
- testDeleteTutorialPlayer()
- testSpawnEnemyCorrectPosition()
- testCleanupOrphanedResources()
- testGetTutorialPlayer()
```

### Integration Tests

```php
- testFullTutorialFlow() // Start → Progress → Complete
- testTutorialCancellation()
- testConcurrentTutorials() // Multiple players
- testResumeAfterDisconnect()
- testResourceCleanupOnFailure()
```

---

## Performance Considerations

### Before (Monolithic):
- One large object with all dependencies loaded
- Tight coupling makes caching difficult
- Hard to optimize individual operations

### After (Service-Oriented):
- Load only needed services
- Each service can be optimized independently
- Easy to add caching layer per service
- Can distribute services across servers (future)

### Example Optimization Opportunities:
1. **SessionManager**: Cache active sessions in Redis
2. **ProgressManager**: Cache step data for 1 minute
3. **ResourceManager**: Batch cleanup operations

---

## Error Handling Improvements

### Specific Exceptions

```php
try {
    $session = $sessionManager->createSession(...);
} catch (TutorialSessionException $e) {
    // Handle session-specific errors
    // Show user-friendly message
    // Log for debugging
}

try {
    $result = $progressManager->advanceStep(...);
} catch (TutorialValidationException $e) {
    // Show validation hint to user
    return ['success' => false, 'hint' => $e->getHint()];
} catch (TutorialStepException $e) {
    // Handle step loading errors
    // Fall back to previous step
}
```

---

## Conclusion

Phase 3 successfully created three focused services that will dramatically simplify TutorialManager:

✅ **TutorialSessionManager** - Session lifecycle
✅ **TutorialProgressManager** - Step progression
✅ **TutorialResourceManager** - Resource management

**Next Phase:** Refactor TutorialManager to use these services (estimated 77% LOC reduction)
