# Tutorial Movement Consumption Control

## Overview

By default, the tutorial does **NOT** consume movements when the player moves. This is legacy behavior that allows tutorial designers to create exploratory sections where players can move freely without worrying about resource management.

However, certain tutorial sections (like teaching about movement limits) need to actually consume movements to demonstrate the mechanic properly.

## Configuration

Movement consumption is controlled per-step via the `context_changes` configuration:

```php
'context_changes' => [
    'consume_movements' => true,  // Enable movement consumption
]
```

## Default Behavior

### Steps 1-5: Unlimited Movement (Legacy)
- Movements are NOT consumed
- Players can explore freely
- Movement counter is displayed but doesn't decrease
- Perfect for teaching navigation without resource pressure

### Steps 6+: Limited Movement (Realistic)
- Movements ARE consumed (if `consume_movements: true` is set)
- Each move decreases the movement counter
- Players experience real resource management
- Perfect for teaching movement strategy

## Example Configuration

```php
// Step 5: Free exploration
[
    'step_id' => 'first_movement',
    'config' => [
        // No consume_movements - defaults to false (unlimited)
        'text' => 'Move around and explore! Your movements are unlimited.'
    ]
],

// Step 6: Enable movement consumption
[
    'step_id' => 'movement_limits_intro',
    'config' => [
        'context_changes' => [
            'consume_movements' => true,  // Enable consumption
            'set_mvt_limit' => 4
        ],
        'text' => 'Each move now consumes 1 movement point!'
    ]
],

// Step 7: Movements still consumed (persists from step 6)
[
    'step_id' => 'deplete_movements',
    'config' => [
        // consume_movements still true from previous step
        'text' => 'Use all 4 movements to continue.'
    ]
]
```

## How It Works

### 1. Step Configuration (populate_tutorial_steps.php)
```php
'context_changes' => [
    'consume_movements' => true
]
```

### 2. AbstractStep applies context changes
When a step completes and the next step begins, `AbstractStep::applyContextChanges()`:
- Sets `$_SESSION['tutorial_consume_movements']` = true/false
- Stores in TutorialContext state

### 3. go.php checks the flag
When player moves:
```php
if ($player->coords->plan === 'tutorial') {
    $consumeMovement = !empty($_SESSION['tutorial_consume_movements']);
    if ($consumeMovement) {
        $bonus = array('mvt'=>-1);
        $player->putBonus($bonus);
    }
}
```

## Persistence

The `consume_movements` setting **persists** across steps until explicitly changed:

- Step 6 sets `consume_movements: true`
- Steps 7, 8, 9... inherit this setting
- Step 10 can set `consume_movements: false` to disable again

## Use Cases

### Teaching Exploration (consume_movements: false)
- "Explore the map freely"
- "Find the hidden item" (without time pressure)
- "Navigate to the waypoint"

### Teaching Resource Management (consume_movements: true)
- "You have 4 movements per turn - use them wisely"
- "Plan your route to reach the goal in 3 moves"
- "Deplete all your movements to end the turn"

### Teaching Combat (consume_movements: false initially)
- Let players position freely before combat
- Then enable consumption during combat tutorial

## Technical Notes

### Why Legacy Behavior (Unlimited)?

The original tutorial design intentionally did NOT consume movements because:
1. **Reduces frustration**: New players can retry navigation without penalties
2. **Focuses learning**: Players focus on "how to move" not "movement budget"
3. **Simpler onboarding**: Fewer mechanics to teach simultaneously
4. **Exploratory freedom**: Encourages experimentation

### Implementation Details

- **File**: `/var/www/html/go.php` lines 287-307
- **Session key**: `$_SESSION['tutorial_consume_movements']`
- **Context state**: `TutorialContext::setState('consume_movements', $value)`
- **Default**: `false` (unlimited movements)

### Migration Path

If updating an existing tutorial:
1. Steps 1-5: No changes needed (default unlimited is fine)
2. Step 6 (movement_limits_intro): Add `'consume_movements' => true`
3. Subsequent steps: Automatically inherit the setting

## Troubleshooting

### Movements not decreasing?
- Check `$_SESSION['tutorial_consume_movements']` is true
- Verify step configuration has `'consume_movements' => true` in context_changes
- Check go.php logs for "[go.php] Tutorial mode: consume_movements=..."

### Movements decreasing when they shouldn't?
- Check if a previous step set `consume_movements: true`
- Explicitly set `'consume_movements' => false` in current step

### Reset to unlimited?
```php
'context_changes' => [
    'consume_movements' => false  // Disable consumption
]
```

## Related Documentation

- [Tutorial Step Configuration Guide](tutorial-step-configuration-guide.md)
- [Tutorial XP & PI Integration](tutorial-xp-pi-integration.md)
- [Tutorial Testing Guide](tutorial-testing-guide.md)
