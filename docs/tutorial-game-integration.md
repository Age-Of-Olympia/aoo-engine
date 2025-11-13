# Tutorial Game Integration Guide

## Overview

The tutorial system can **automatically advance** when players perform actions in the game. This guide shows how to integrate tutorial validation with your game code.

## How It Works

1. **Simple steps (info, dialog)**: Click "Next" button → advances automatically
2. **Action steps (movement, combat, search)**: Perform the action in game → tutorial validates and advances automatically

## Integration Pattern

When a player performs an action, call the global `notifyTutorial()` function:

```javascript
window.notifyTutorial(actionType, actionData);
```

The tutorial system will:
- Check if tutorial is active
- Validate the action matches what the current step requires
- Automatically advance to next step if valid
- Show error message if invalid

## Movement Integration

**When:** Player clicks a map cell to move

**Where:** In your movement handling code (map.js, main.js, or AJAX success callback)

**Example:**
```javascript
// After successful movement
function onMovementSuccess(oldX, oldY, newX, newY) {
    // ... your existing movement code ...

    // Notify tutorial
    window.notifyTutorial('movement', {
        from: [oldX, oldY],
        to: [newX, newY]
    });
}
```

**Tutorial Steps Using This:**
- Step 4: "Votre premier mouvement" - Requires any movement
- Step 5: "Mouvements limités" - Requires movement in specific direction

## Combat Integration

**When:** Player clicks attack action on an enemy

**Where:** In your combat/attack handling code

**Example:**
```javascript
// After successful attack
function onAttackSuccess(targetId, damage) {
    // ... your existing combat code ...

    // Notify tutorial
    window.notifyTutorial('combat', {
        target_id: targetId,
        damage: damage,
        action_type: 'attack'
    });
}
```

**Tutorial Steps Using This:**
- Step 12: "Attaquez!" - Requires attacking training dummy

## Action Integration (Search, Heal, etc.)

**When:** Player performs any game action

**Where:** In your action execution code

**Example:**
```javascript
// After successful action
function onActionSuccess(actionType, actionData) {
    // ... your existing action code ...

    // Notify tutorial
    window.notifyTutorial('action', {
        action_type: actionType,
        ...actionData
    });
}
```

**Tutorial Steps Using This:**
- Step 9: "Pratique : Fouiller" - Requires search action

## Implementation Steps

### 1. Find Your Game Action Code

Locate where these actions are handled:
- Movement: Usually in `map.js`, `main.js`, or AJAX callback for movement
- Combat: Combat action handler or attack click handler
- Actions: General action executor or specific action handlers

### 2. Add Notification Calls

After successful action execution, add one line:

```javascript
window.notifyTutorial('action_type', { ...relevant_data });
```

### 3. Test

1. Login as player 7 (tutorial enabled)
2. Start tutorial: Click green "Tutoriel" button in menu
3. Follow tutorial steps
4. Perform the requested action
5. Tutorial should auto-advance ✓

## Example: Full Movement Integration

```javascript
// Existing movement code
function handleCellClick(x, y) {
    const oldX = player.x;
    const oldY = player.y;

    $.ajax({
        url: 'api/player/move.php',
        method: 'POST',
        data: { x: x, y: y },
        success: function(response) {
            if (response.success) {
                // Update player position
                player.x = x;
                player.y = y;
                updateMapDisplay();

                // *** ADD THIS LINE ***
                window.notifyTutorial('movement', {
                    from: [oldX, oldY],
                    to: [x, y]
                });
            }
        }
    });
}
```

## Validation Logic

Each tutorial step has validation logic in PHP:
- `src/Tutorial/Steps/Movement/MovementStep.php` - Validates movement happened
- `src/Tutorial/Steps/Combat/CombatStep.php` - Validates attack on correct target
- `src/Tutorial/Steps/Actions/ActionStep.php` - Validates correct action type

The validation happens server-side in `api/tutorial/advance.php`.

## Debugging

Check browser console for tutorial messages:
```
[TutorialUI] Action notification: movement {from: [5,5], to: [5,6]}
[TutorialUI] Advancing to next step... {validationData: {...}}
[TutorialUI] Step advanced: 5 → 6
```

If validation fails, you'll see:
```
[TutorialUI] Validation failed {error: "...", hint: "..."}
```

## Tips

1. **Only notify on success**: Don't call `notifyTutorial()` if action failed
2. **Include relevant data**: The more data you pass, the better validation can be
3. **Test thoroughly**: Try both valid and invalid actions
4. **Check console**: Look for tutorial debug messages

## FAQ

**Q: What if the player isn't in tutorial mode?**
A: The `notifyTutorial()` function checks if tutorial is active. If not, it does nothing.

**Q: Will this break for players not in tutorial?**
A: No! It's completely safe to add these calls everywhere.

**Q: Can I test without performing actions?**
A: Yes! For simple info steps (0-3, 6-8), just click "Next" button.

**Q: What if I don't integrate actions yet?**
A: Steps 0-8 work without integration (info + dialog). Steps 9+ will require integration to advance.

## Next Steps

1. Identify your game action handlers
2. Add `notifyTutorial()` calls
3. Test with player 7
4. Expand tutorial content (currently 14 steps, plan for 47)

---

**Need Help?** Check `docs/tutorial-integration.md` for full API documentation.
