# Tutorial Security Model

## Critical Security Principle

**The tutorial system MUST NEVER automatically switch the player's character without explicit user action.**

## Session Variable Control

Tutorial mode is controlled by three session variables:
- `$_SESSION['in_tutorial']` - Boolean flag
- `$_SESSION['tutorial_session_id']` - Tutorial session UUID
- `$_SESSION['tutorial_player_id']` - Tutorial character ID (NOT main player ID)

## Where These Variables Are Set

### ✅ AUTHORIZED Locations (ONLY these should set tutorial session vars):

1. **api/tutorial/start.php**
   - When user explicitly clicks "Start Tutorial"
   - Sets vars after creating tutorial session
   - Persists with `session_write_close()` + `session_start()`

2. **api/tutorial/resume.php**
   - When JavaScript explicitly calls resume API
   - Sets vars when resuming active tutorial
   - Persists with `session_write_close()` + `session_start()`

### ✅ AUTHORIZED Location (clears vars):

3. **api/tutorial/cancel.php**
   - When user clicks "Skip Tutorial" or completes tutorial
   - Clears vars using `TutorialHelper::exitTutorialMode()`
   - Persists with `session_write_close()` + `session_start()`

### ❌ FORBIDDEN Locations (NEVER set these vars):

- **config.php** - NEVER auto-activate tutorial on page load
- **index.php** - Main game page should NOT set tutorial vars
- **Any game action files** (go.php, observe.php, etc.) - NEVER set tutorial vars

## Why config.php Must NOT Auto-Activate Tutorial

### The Problem (Before Fix)

Original code in config.php:
```php
// DANGEROUS - DO NOT DO THIS
if (isset($_SESSION['playerId']) && empty($_SESSION['in_tutorial'])) {
    // Check database for active tutorial
    if ($hasActiveTutorial) {
        $_SESSION['in_tutorial'] = true;  // ❌ SECURITY ISSUE
        $_SESSION['tutorial_player_id'] = $tutorialPlayerId;
    }
}
```

**Security Issues:**
1. **Unexpected Character Switch**: Player loads game normally → config.php detects old incomplete tutorial → switches character without warning
2. **Session Contamination**: Tutorial vars leak into normal gameplay
3. **UX Disaster**: Player tries to play main character → suddenly playing tutorial character
4. **No User Consent**: Tutorial activates without user clicking anything

### The Solution (Current)

```php
// SAFE - config.php does NOT auto-activate tutorial
// Session vars are ONLY set by explicit API calls (start.php, resume.php)
```

Tutorial activation requires:
1. User action (clicking "Start Tutorial" or page loading tutorial UI)
2. JavaScript explicitly calling start.php or resume.php API
3. API explicitly setting session vars

## Session Persistence

All three authorized endpoints use this pattern:

```php
// Set or clear tutorial session vars
TutorialHelper::startTutorialMode($sessionId, $tutorialPlayerId);
// OR
TutorialHelper::exitTutorialMode();

// Force session write to disk
session_write_close();

// Restart session for subsequent operations
session_start();
```

This ensures:
- Changes persist across page reloads
- Subsequent requests (go.php, etc.) see the updated vars
- Session vars don't mysteriously disappear

## Player ID Logic

### TutorialHelper Centralization

All game code should use:

```php
$playerId = TutorialHelper::getActivePlayerId();
```

This returns:
- Tutorial player ID if `$_SESSION['in_tutorial']` is true
- Main player ID otherwise

### Files Using TutorialHelper (Updated)

- go.php
- observe.php
- load_caracs.php
- index.php
- api/tutorial/advance.php
- src/View/NewTurnView.php

## TutorialContext Player Switching

### The Resume Player Switch Issue

When resuming a tutorial, there's a critical player context switch that must happen:

**The Problem**:
1. User has main player (ID 7) and tutorial player (ID 305)
2. JavaScript calls `resume.php` to resume tutorial
3. `TutorialManager` is initially created with main player
4. Context keeps using main player for validation hints, movement checks, etc.
5. User sees wrong movement counts (main player's 6 mvt instead of tutorial player's 4 mvt)

**The Solution** (api/tutorial/resume.php + TutorialManager.php):

```php
// 1. resume.php: Load tutorial session with tutorial_player_id
$sql = 'SELECT tp.tutorial_session_id, ..., tpl.player_id as tutorial_player_id
        FROM tutorial_progress tp
        LEFT JOIN tutorial_players tpl ON tpl.tutorial_session_id = tp.tutorial_session_id
        WHERE tp.player_id = ? AND tp.completed = 0 AND tpl.is_active = 1';

// 2. resume.php: Set session vars with tutorial player ID
TutorialHelper::startTutorialMode(
    $session['tutorial_session_id'],
    $session['tutorial_player_id']  // Use tutorial player, not main player
);

// 3. TutorialManager::resumeTutorial(): Switch context player
if ($this->tutorialPlayer) {
    $tutorialPlayerObj = new Player($this->tutorialPlayer->id);
    $tutorialPlayerObj->get_data();
    $this->context->setPlayer($tutorialPlayerObj);
    error_log("[TutorialManager] Switched context to tutorial player {$this->tutorialPlayer->id}");
}
```

This ensures:
- Movement counts reflect tutorial player's actual movements
- Validation hints use tutorial player data
- Prerequisites check tutorial player resources
- Step data is generated from tutorial player perspective

### Context Player Switching Rules

**When to switch context player:**
- ✅ In `TutorialManager::resumeTutorial()` after loading tutorial session
- ✅ When tutorial player is loaded from database (`TutorialPlayer::loadBySession()`)

**When NOT to switch:**
- ❌ On every step render (context player should already be set)
- ❌ In `TutorialHelper` (that's for session-level player ID, not context)
- ❌ In step validation methods (use `TutorialHelper::getActivePlayerId()` instead)

**TutorialContext::setPlayer() Usage:**

```php
// CORRECT: Switch to tutorial player when resuming
$tutorialPlayer = new Player($tutorialPlayerData->id);
$tutorialPlayer->get_data();
$this->context->setPlayer($tutorialPlayer);

// INCORRECT: Don't switch back to main player during tutorial
// This would break movement counts and validations
```

## Step Implementation: Player Access Pattern

When implementing tutorial steps that need to access player data (movements, actions, inventory, etc.), **always use `TutorialHelper::getActivePlayerId()`** instead of `$this->context->getPlayer()`.

### Why TutorialHelper is More Reliable

**The Problem with context player:**
- Context player depends on initialization order
- May not be set correctly in all call paths
- Can be out of sync with session variables

**The Solution with TutorialHelper:**
- Session variables are the authoritative source (set by start.php/resume.php)
- Always returns the correct player ID
- Works consistently across all execution paths

### Standard Pattern for Step Methods

```php
// ✅ CORRECT: Use TutorialHelper in both validate() and getValidationHint()
class MovementStep extends AbstractStep
{
    public function validate(array $data): bool
    {
        // Get active player ID from session vars (authoritative source)
        $activePlayerId = TutorialHelper::getActivePlayerId();
        $player = new Player($activePlayerId);
        $player->get_data();
        $player->get_caracs();

        $mvtRemaining = $player->getRemaining('mvt');
        return $mvtRemaining === 0;
    }

    public function getValidationHint(): string
    {
        // Same pattern: always use TutorialHelper
        $activePlayerId = TutorialHelper::getActivePlayerId();
        $player = new Player($activePlayerId);
        $player->get_data();
        $player->get_caracs();

        $mvtRemaining = $player->getRemaining('mvt');
        return "Il vous reste encore {$mvtRemaining} mouvement(s).";
    }
}
```

```php
// ❌ INCORRECT: Don't rely on context player
public function validate(array $data): bool
{
    $player = $this->context->getPlayer(); // May be wrong player!
    // ...
}
```

### When Context Player IS Safe

Context player can be used safely in:
- `TutorialContext` internal methods (it owns the player reference)
- After explicitly verifying the context player was set correctly
- In code that doesn't care about main vs tutorial player distinction

But for consistency and safety, **prefer `TutorialHelper::getActivePlayerId()` everywhere**.

## Testing the Security Model

### ✅ Valid Scenarios

1. **Start tutorial** → Session vars set → Play tutorial → Complete → Vars cleared
2. **Start tutorial** → Close browser → Return → JavaScript resumes → Vars re-set
3. **Start tutorial** → Skip tutorial → Vars cleared → Play main character

### ❌ Invalid Scenarios (Should NOT Happen)

1. **Load game** → Config auto-detects old tutorial → Character switches ❌
2. **Playing main character** → Suddenly using tutorial player ❌
3. **Tutorial vars persist** after completing/canceling ❌

## Debugging Tutorial Mode

### Check Current State

```php
// In any PHP file
error_log("Tutorial mode: " . (TutorialHelper::isInTutorial() ? 'YES' : 'NO'));
error_log("Active player ID: " . TutorialHelper::getActivePlayerId());
error_log("Main player ID: " . TutorialHelper::getMainPlayerId());
```

### Verify Session Vars

```javascript
// In browser console
console.log('Session vars:', {
    in_tutorial: window.tutorialUI?.isActive,
    session_id: window.tutorialUI?.currentSession
});
```

## Migration Notes

If you find code that manually checks `$_SESSION['in_tutorial']`:

### Before (Manual Check - Scattered)
```php
$playerId = $_SESSION['playerId'];
if (!empty($_SESSION['in_tutorial']) && !empty($_SESSION['tutorial_player_id'])) {
    $playerId = $_SESSION['tutorial_player_id'];
}
```

### After (Centralized - Correct)
```php
use App\Tutorial\TutorialHelper;

$playerId = TutorialHelper::getActivePlayerId();
```

## Security Audit Checklist

When adding new game features:

- [ ] Does it use `TutorialHelper::getActivePlayerId()` instead of `$_SESSION['playerId']`?
- [ ] Does it NEVER set `$_SESSION['in_tutorial']` or `$_SESSION['tutorial_player_id']`?
- [ ] Have you tested it works correctly in both tutorial and normal mode?
- [ ] Does it handle tutorial player without affecting main player data?

## Related Documentation

- [Tutorial Helper API](tutorial-helper-api.md)
- [Tutorial Testing Guide](tutorial-testing-guide.md)
- [Tutorial XP & PI Integration](tutorial-xp-pi-integration.md)
