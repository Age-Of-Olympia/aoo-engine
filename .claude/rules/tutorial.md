---
paths:
  - "src/Tutorial/**"
  - "js/tutorial/**"
  - "api/tutorial/**"
  - "cypress/e2e/tutorial*"
  - "tests/Tutorial/**"
  - "src/Migrations/*Tutorial*"
---

# Tutorial system

Full write-up: [docs/tutorial-system-overview.md](../../docs/tutorial-system-overview.md).

**Architecture** (`src/Tutorial/`):
- **TutorialManager**: Orchestrates tutorial flow, step progression
- **TutorialContext**: Holds player state, session data
- **TutorialHelper**: Utility functions (session management, player switching)
- **TutorialPlayer**: Temporary player characters for tutorial sessions
- **TutorialView**: Renders tutorial UI on index page

**Tutorial Flow**:
1. Player starts tutorial via "Commencer le tutoriel" button
2. `api/tutorial/start.php` creates tutorial session and tutorial player
3. Tutorial player spawned on `plan='tutorial'` at (0,0)
4. Steps rendered by `js/tutorial/TutorialUI.js` with tooltips and highlights
5. Player completes validation requirements to advance steps
6. On completion or cancel, tutorial player is deactivated and main player restored

**Tutorial Database Tables**:
- **Session & Player Tracking**:
  - `tutorial_progress`: Active tutorial sessions (player_id, tutorial_session_id, current_step, xp_earned, completed, tutorial_mode, tutorial_version)
  - `tutorial_players`: Temporary player characters (id, tutorial_session_id, player_id, name, is_active) — link to real player lives on `players.real_player_id_ref`
  - `tutorial_enemies`: Spawned enemies for combat training (tutorial_session_id, enemy_player_id, enemy_coords_id)

- **Step Configuration (Normalized Schema)**:
  - `tutorial_steps`: Core step definitions (id, version, step_id, next_step, step_number, step_type, title, text, xp_reward, is_active)
  - `tutorial_step_ui`: UI configuration 1:1 (step_id, target_selector, tooltip_position, interaction_mode, show_delay, etc.)
  - `tutorial_step_validation`: Validation rules 1:1 (step_id, requires_validation, validation_type, validation_hint, target_x, target_y, etc.)
  - `tutorial_step_prerequisites`: Resource requirements 1:1 (step_id, mvt_required, pa_required, auto_restore, consume_movements, etc.)
  - `tutorial_step_features`: Special features 1:1 (step_id, celebration, show_rewards, redirect_delay)
  - `tutorial_step_highlights`: Additional highlights 1:N (step_id, selector)
  - `tutorial_step_interactions`: Allowed interactions for semi-blocking mode 1:N (step_id, selector, description)
  - `tutorial_step_context_changes`: Context state modifications 1:N (step_id, context_key, context_value)
  - `tutorial_step_next_preparation`: Preparation for next step 1:N (step_id, preparation_key, preparation_value)

- **Dialogs**:
  - `tutorial_dialogs`: Dialog configurations (id, dialog_id, npc_name, dialog_data JSON, is_active)

**Step Types** (in `src/Tutorial/Steps/`):
- **AbstractStep**: Base class for all steps
- **InfoStep**: Informational dialogs (blocking, no validation)
- **MovementStep**: Movement validation (any_movement, movements_depleted, position)
- **ActionStep**: Action usage validation (action_used, action_available)
- **UIInteractionStep**: UI interaction validation (ui_panel_opened, ui_interaction)
- **CombatStep**: Combat-related validation

**Step Configuration**:
Steps are stored in the normalized database schema. The `TutorialStepRepository` performs JOINs across all step tables and converts them into a configuration array that AbstractStep subclasses use. Configuration includes:

- **Core** (`tutorial_steps`): step_id, next_step, step_number, step_type, title, text, xp_reward
- **UI** (`tutorial_step_ui`): target_selector, tooltip_position, interaction_mode, show_delay, auto_advance_delay
- **Validation** (`tutorial_step_validation`): requires_validation, validation_type, validation_hint, target_x/y, action_name
- **Prerequisites** (`tutorial_step_prerequisites`): mvt_required, pa_required, auto_restore, consume_movements
- **Features** (`tutorial_step_features`): celebration, show_rewards, redirect_delay
- **Highlights** (`tutorial_step_highlights`): Additional elements to highlight (1:N)
- **Interactions** (`tutorial_step_interactions`): Allowed clicks in semi-blocking mode (1:N)
- **Context Changes** (`tutorial_step_context_changes`): State modifications on step completion (1:N)
- **Next Preparation** (`tutorial_step_next_preparation`): Setup for next step (1:N)

Steps are accessed via `TutorialStepRepository::getStepById($stepId, $version)` or `getStepByNumber($stepNumber, $version)`.

**Tutorial JavaScript** (`js/tutorial/`):
- **TutorialUI.js**: Main controller (API calls, step rendering, validation)
- **TutorialTooltip.js**: Tooltip positioning and display
- **TutorialHighlighter.js**: Element highlighting with pulse animation
- **TutorialInit.js**: Initialization and event wiring

**Interaction Modes**:
- **blocking**: Full overlay, only tutorial controls clickable
- **semi-blocking**: Overlay with specific allowed elements (e.g., movement tiles, action buttons)
- **open**: No overlay, player can interact freely

**Session Management**:
- Tutorial session stored in PHP `$_SESSION['tutorial_session_id']` and `$_SESSION['tutorial_player_id']`
- Active state tracked in `sessionStorage.tutorial_active` for auto-resume
- Player switching via `TutorialHelper::startTutorialMode()` and `exitTutorialMode()`

**Tutorial Player Isolation**:
- Tutorial players exist on separate `plan='tutorial'` map
- Positive IDs tracked in `tutorial_players` table
- Tutorial enemies spawned via `TutorialManager::spawnTutorialEnemy()`
- All tutorial data cleaned up on cancel or completion

**Player Visibility System**:
The tutorial implements complete player isolation using the `player_visibility` setting in plan JSON:

1. **Plan Configuration** (`datas/private/plans/tutorial.json`):
   ```json
   {
       "player_visibility": false,  // Hide other players
       "biomes": [...]              // Resource definitions
   }
   ```

2. **Three-Layer Isolation**:
   - **Map Rendering** (`Classes/View.php:290`): Other players not drawn on map
   - **Movement Blocking** (`go.php:70`): Other players don't block coordinates
   - **Character Card** (`observe.php:125`): Other players not listed in observation panel

3. **Implementation Pattern** (consistent across all three):
   ```php
   $planJson = json()->decode('plans', $player->coords->plan);
   $playerVisibilityEnabled = !isset($planJson->player_visibility) || $planJson->player_visibility !== false;

   if ($playerVisibilityEnabled) {
       // Normal mode: show/block other players
   } else {
       // Tutorial mode: only show current player and NPCs
       // Filter: (p.id = ? OR p.id < 0)
   }
   ```

**Movement Control**:
Tutorial supports both infinite and limited movement via session flag:

- **Infinite Movement** (default): `$_SESSION['tutorial_consume_movements']` not set
- **Limited Movement**: Set via step prerequisites `{"mvt": 3, "auto_restore": true}`
- **Implementation** (`go.php:289-305`):
  ```php
  $isTutorial = ($player->coords->plan === 'tutorial');
  $consumeMovement = !empty($_SESSION['tutorial_consume_movements']);

  // Consume movement if:
  // - Plan has JSON (non-tutorial) OR tutorial explicitly requests it
  if(($planJson && !$isTutorial) || $consumeMovement){
      $player->putBonus(['mvt' => -1]);
  }
  ```
- This allows tutorial plan to have resource JSON without forcing movement consumption

**Active Player Detection**:
Critical pattern for tutorial compatibility - always use `TutorialHelper::getActivePlayerId()`:

```php
// WRONG - Always uses main player
$player = new Player($_SESSION['playerId']);

// CORRECT - Uses tutorial player if active, otherwise main player
use App\Tutorial\TutorialHelper;
$activePlayerId = TutorialHelper::getActivePlayerId();
$player = new Player($activePlayerId);
```

**Files requiring active player detection**:
- Movement: `go.php` ✅
- Observation: `observe.php` ✅
- Inventory: `src/View/Inventory/InventoryView.php` ✅
- Bank: `src/View/Inventory/BankView.php` ✅
- Craft: `src/View/Inventory/CraftView.php` ✅
- Actions: Various action handlers ✅

**Step Validation Types**:

**MovementStep** (`src/Tutorial/Steps/Movement/MovementStep.php`):
- `any_movement`: Player moved at all
- `movements_depleted`: Player used all MVT points
- `specific_count`: Player moved X times
- `position`: Player at exact coordinates (x, y)
- `adjacent_to_position`: Player adjacent to target (Manhattan distance = 1) - useful for resource gathering

**UIInteractionStep** (`src/Tutorial/Steps/UIInteractionStep.php`):
- `ui_panel_opened`: Specific panel visible
- `ui_button_clicked`: Specific button clicked
- `ui_interaction`: Generic element click validation (tracks `element_clicked` parameter)

**Advanced Step Configuration**:
```json
{
  "show_delay": 500,  // Delay tooltip/highlight (ms) for UI to settle
  "validation_type": "adjacent_to_position",
  "validation_params": {
    "target_x": 0,
    "target_y": 1  // Tree position - validates ANY adjacent tile
  }
}
```

**Tutorial UX Best Practices**:
1. **Clear Previous State**: Remove hints/warnings when advancing steps
2. **UTF-8 Encoding**: Always use `header('Content-Type: application/json; charset=utf-8')` in APIs
3. **Timing**: Use `show_delay` for steps that need UI to settle first
4. **Navigation**: Make menu navigation explicit (Inventaire → see items → Damier → return)
5. **Validation Hints**: Clear messages removed automatically on step advance

**Common Tutorial Issues & Solutions**:

| Issue | Symptom | Solution |
|-------|---------|----------|
| **"Coordonnées invalides"** | Movement blocked during tutorial | Check `player_visibility` in plan JSON and `go.php` isolation logic |
| **Wrong player inventory** | Main player items shown instead of tutorial player's | Use `TutorialHelper::getActivePlayerId()` instead of `$_SESSION['playerId']` |
| **Encoding issues** | French accents display as "rÃ©coltable" | Add `charset=utf-8` to `Content-Type` header in API responses |
| **Hints persist** | Previous step hints visible on new step | Call `$('.tooltip-blocked-message').remove()` in `renderStep()` |
| **Highlight wrong position** | Element highlighted 3 tiles away | Use `getBoundingClientRect()` for positioning, check selector matches single element |
| **Tooltip appears too fast** | Tooltip before UI ready | Add `show_delay: 500` to step config |
| **Movement always consumed** | Can't have unlimited movement with plan JSON | Exclude tutorial from auto-consumption: `($planJson && !$isTutorial)` |

**Resource Gathering Setup**:
1. Create plan JSON: `datas/private/plans/tutorial.json`
2. Add `player_visibility: false` to hide other players
3. Define biomes with wall types and resources:
   ```json
   {"wall": "arbre1", "ressource": "bois", "exhaust": 75, "regrow": 20}
   ```
4. Ensure the resource entities exist on the plan (type of nature « ressource », placed through `entity_cells`)
5. Validate with `adjacent_to_position` instead of exact position
6. Use multi-step flow: move → inspect → gather → check inventory

**Race-Adaptive Features**:

The tutorial adapts to the player's race for movement points:

| Race | Max MVT |
|------|---------|
| Nain | 4 |
| Elfe | 5 |
| Homme-Sauvage | 6 |

- **`{max_mvt}` placeholder**: Use in step text to display race-specific movement count
  - Example: `"Vous avez {max_mvt} mouvements"` → "Vous avez 4 mouvements" (for Nain)
- **`-1` for race max**: In `tutorial_step_prerequisites.mvt_required`, use `-1` to mean "use race max"
- **RaceService**: Use `$raceService->getRaceMaxMvt($raceName)` to get race MVT
- **Race API**: `GET /api/races/get.php?name=nain` returns race stats (mvt, pv, pa, bgColor)

**Tutorial Documentation**: See [docs/tutorial-system-overview.md](docs/tutorial-system-overview.md) for comprehensive documentation.

**Tutorial**:
- `tutorial_steps`: Core step definitions (id, version, step_id, next_step, step_number, step_type, title, text, xp_reward, is_active)
- `tutorial_step_ui`, `tutorial_step_validation`, `tutorial_step_prerequisites`, `tutorial_step_features`: 1:1 step configuration tables
- `tutorial_step_highlights`, `tutorial_step_interactions`, `tutorial_step_context_changes`, `tutorial_step_next_preparation`: 1:N step configuration tables
- `tutorial_progress`: Session tracking (tutorial_session_id, player_id, current_step, completed, tutorial_mode, tutorial_version, xp_earned)
- `tutorial_players`: Tutorial characters (id, tutorial_session_id, player_id, name, is_active) — link to real player lives on `players.real_player_id_ref`
- `tutorial_enemies`: Combat training enemies (tutorial_session_id, enemy_player_id, enemy_coords_id)
- `tutorial_dialogs`: Dialog configurations (dialog_id, npc_name, dialog_data JSON)
