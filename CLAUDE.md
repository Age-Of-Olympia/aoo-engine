# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Age of Olympia v4 (AoO) is a browser-based multiplayer RPG game built with PHP. The game features a turn-based gameplay system with actions, player progression, forums, and real-time interactions. The project uses Doctrine ORM for database management and follows a service-oriented architecture.

## Development Environment

The project runs in a **VSCode Dev Container** with Docker. Three containers are used:
- **webserver** (PHP-AOO4-Local): Apache server running the PHP application
- **mariadb-aoo4**: MariaDB database (port 3306)
- **phpmyadmin**: Database management interface (port 8081)

**IMPORTANT - Port Configuration:**
- **From host machine**: `http://localhost:9000` (mapped to container port 80)
- **From inside devcontainer**: `http://localhost` or `http://localhost:80` (direct access to Apache)
- When running scripts/tests inside the container, always use `http://localhost` (port 80)

### Starting the Server

```bash
apache2-foreground
```

**Note**: Apache has a bug where it stops with SIGWINCH when the terminal window is resized. Just restart it if this happens.

### Debugging

Use VSCode's "Listen for Xdebug" debug configuration. Xdebug is pre-configured in the dev container.

**CRITICAL - Log Files:**
- **DO NOT** attempt to read Apache log files (`/var/log/apache2/error.log`) - they are redirected to container stdout and reading them will freeze/hang
- Instead, use `docker logs` command or view container output directly
- The user can provide logs from their terminal when needed

### Test Accounts

Three default characters are created:
- **Cradek** (matricule 1): Nain, administrator - password: `test`
- **Dorna** (matricule 2): Nain, player - password: `test`
- **Thyrias** (matricule 3): Elfe, player - password: `test`

Admin console: Press `²` key when logged in, or use the settings menu button.

## Build, Test & Quality Commands

All commands are centralized in the **Makefile** (used locally and in CI):

```bash
# Run all quality checks (PHPStan + tests + coverage)
make all

# Run PHPStan static analysis (level 4 on tests/ directory)
make phpstan

# Run all PHPUnit tests
make test

# Run a specific test by name
make testf YourTestName

# Generate code coverage report (output: tmp/coverage/)
make coverage

# CI-specific commands
make test-ci       # Tests with XML reports
make phpstan-ci    # PHPStan with CI setup
make setup-ci-env  # Setup CI environment (copies datas, img, config)
```

### Running Individual Tests

```bash
# Using make
make testf CalculateXpTest

# Using PHPUnit directly
XDEBUG_MODE=coverage ./vendor/bin/phpunit --filter CalculateXpTest --testdox
```

## Code Quality & Proactive Refactoring

**IMPORTANT**: Be proactive in identifying and fixing code smells, anti-patterns, and opportunities for refactoring. Don't wait for the user to point them out.

### Code Smells to Watch For

#### 1. **Duplicated Code (DRY Violation)**
**Detection**: If you're making the same change in 2+ locations, it's a code smell.

**Example**:
```javascript
// BAD: Duplicated logic in start() and resume()
async start() {
    this.showOverlay();
    this.renderStep(data);
    sessionStorage.setItem('active', 'true');
}

async resume() {
    this.showOverlay();      // DUPLICATED
    this.renderStep(data);   // DUPLICATED
    sessionStorage.setItem('active', 'true');  // DUPLICATED
}

// GOOD: Extract common logic
async start() {
    this.activateUI(data);
}

async resume() {
    this.activateUI(data);
}

activateUI(data) {
    this.showOverlay();
    this.renderStep(data);
    sessionStorage.setItem('active', 'true');
}
```

**Action**: Immediately propose refactoring when you notice duplication.

#### 2. **Long Methods (God Methods)**
**Detection**: Methods longer than ~50 lines, methods doing multiple things

**Action**: Extract smaller, well-named methods with single responsibilities.

#### 3. **Magic Numbers/Strings**
**Detection**: Hardcoded values without explanation

**Example**:
```php
// BAD
if ($player->level > 10) { ... }

// GOOD
const VETERAN_LEVEL_THRESHOLD = 10;
if ($player->level > self::VETERAN_LEVEL_THRESHOLD) { ... }
```

#### 4. **Deep Nesting**
**Detection**: More than 3 levels of indentation

**Action**: Extract methods, use early returns, invert conditions.

#### 5. **Inconsistent Naming**
**Detection**: Similar concepts named differently (e.g., `getData()` vs `fetchInfo()` vs `retrieveData()`)

**Action**: Propose consistent naming convention.

#### 6. **Large Classes (God Objects)**
**Detection**: Classes with too many responsibilities (e.g., `Player.php` - 51k LOC)

**Note**: For legacy code like `Classes/Player.php`, acknowledge it but suggest gradual extraction of services.

#### 7. **Poor Error Handling**
**Detection**: Silent failures, generic error messages, no logging

**Action**: Add proper error handling with descriptive messages and logging.

### When to Refactor

**Always refactor when**:
- You find duplicated code
- You're touching code that's hard to understand
- You notice a pattern of similar changes across multiple files
- Tests are difficult to write due to code structure

**Propose refactoring**:
- Before implementing new features in poorly structured code
- When you notice architectural issues during debugging
- After fixing bugs that were caused by code smells

**Don't refactor**:
- If it would delay critical bug fixes (but note it for later)
- If it would change behavior without tests

### Refactoring Process

1. **Identify the smell**: "I notice this code is duplicated in 3 places..."
2. **Propose the refactoring**: "Would you like me to extract this into a shared method?"
3. **If user agrees or if it's obvious**: Refactor immediately
4. **Add explanatory comments**: Document why the refactoring improves the code
5. **Ensure tests still pass**: Run relevant tests after refactoring

### Example Proactive Refactoring

```
User: "Fix the bug in the login and register methods where validation fails"

You: "I've fixed the bug in both methods. However, I notice both methods have
identical validation logic (40 lines duplicated). This violates the DRY principle
and makes future changes error-prone.

I recommend extracting this into a shared validateCredentials() method. This will:
- Reduce code from 80 to 45 lines
- Ensure consistent validation
- Make future changes easier (single point of change)

Should I proceed with this refactoring?"
```

### Code Review Mindset

When writing or modifying code, ask yourself:
- ✅ **Is this code duplicated elsewhere?** → Extract to shared method
- ✅ **Would I understand this in 6 months?** → Add comments/better names
- ✅ **Is this method doing one thing?** → Split if doing multiple things
- ✅ **Are there magic numbers/strings?** → Extract to named constants
- ✅ **Could this fail silently?** → Add error handling
- ✅ **Is the order of operations critical?** → Document with comments
- ✅ **Are names clear and consistent?** → Rename for clarity

### Documentation Standards

When refactoring:
- Add JSDoc/PHPDoc comments to new methods explaining **why** not just **what**
- Document critical order of operations (e.g., "overlay must exist before applying mode")
- Note any breaking changes or migration paths

### Language-Specific Guidelines

**PHP**:
- Follow PSR-12 coding standards
- Use type hints for all parameters and return types
- Extract services instead of adding to god classes

**JavaScript**:
- Use ES6+ features (const/let, arrow functions, async/await)
- Avoid global variables
- Use descriptive function names

**SQL**:
- Use prepared statements (never string concatenation)
- Index foreign keys
- Avoid N+1 queries

## Architecture

### Directory Structure

- **src/**: Modern PSR-4 autoloaded code (Doctrine entities, services, views, actions)
  - `Entity/`: Doctrine ORM entities (Action, Player, Race, etc.)
  - `Service/`: Business logic services (ActionExecutorService, PlayerService, ViewService, etc.)
  - `Action/`: Action implementations (AttackAction, HealAction, SearchAction, etc.)
    - `Condition/`: Action condition validators
    - `OutcomeInstruction/`: Action outcome processors
  - `View/`: View rendering classes (MainView, ForumView, etc.)
  - `Form/`: Form handling
  - `Enum/`: Enumerations
  - `Migrations/`: Doctrine database migrations

- **Classes/**: Legacy utility classes (Player, Forum, Log, Item, Ui, etc.)
  - `Player.php`: Core player operations (51k+ LOC - central to game logic)
  - `Log.php`: Game event logging system
  - `Forum.php`: Forum functionality
  - `Ui.php`: UI rendering utilities
  - `console-commands/`: Admin console command implementations

- **api/**: REST API endpoints organized by domain (account, forum, map, player, etc.)

- **Root PHP files**: Page controllers (index.php, login.php, map.php, forum.php, etc.)

- **scripts/**: Page-specific logic called by controllers

- **config/**: Configuration files
  - `constants.php`: Game constants (13k+ LOC)
  - `db_constants.php`: Database configuration
  - `bootstrap.php`: Doctrine ORM setup
  - `functions.php`: Global utility functions

- **tests/**: PHPUnit tests
  - `Action/`: Action system tests
  - `Logs/`: Game event/log tests

- **datas/**: Game data files (JSON configurations, game state)

- **img/**: Game images and assets

### Key Architectural Patterns

#### Action System
The game uses a **Doctrine-based action system** with inheritance:
- Base entity: `src/Entity/Action.php` (abstract, single-table inheritance)
- Concrete actions: `src/Action/*Action.php` (AttackAction, HealAction, etc.)
- Actions have:
  - **Conditions** (ActionCondition): Prerequisites for execution
  - **Outcomes** (ActionOutcome): Results of successful execution
  - **OutcomeInstructions**: Specific outcome processors
- Execution handled by: `ActionExecutorService`

#### Service Layer
Services in `src/Service/` encapsulate business logic:
- **ActionExecutorService**: Executes game actions
- **PlayerService**: Player-related operations
- **ViewService**: View rendering (44k+ LOC - complex)
- **ForumService**: Forum operations
- **InventoryService**: Inventory management
- Services extend `BaseService` and use Doctrine EntityManager

#### View System
Views in `src/View/` render UI components:
- Each view class handles specific page rendering
- `ViewService` coordinates view assembly
- Views use `Classes\Ui` for HTML generation

#### Doctrine ORM
- Entities use PHP 8 attributes for mapping
- EntityManager created via `EntityManagerFactory`
- Migrations in `src/Migrations/`
- CLI tools: `config/cli-config.php`

#### Legacy Integration
- Modern `src/` code coexists with legacy `Classes/` code
- `Classes\Player` is still central (51k LOC) - gradually being refactored
- Both use same database via Doctrine/raw queries

## JavaScript & CSS Changes - Cache Busting

**CRITICAL**: When modifying JavaScript or CSS files, you MUST update version parameters to prevent browser caching issues.

### JavaScript Comments in Minified Output

**CRITICAL**: When writing inline JavaScript in PHP files that use `Str::minify()`:
- **NEVER use `//` single-line comments** - they will break minified code
- **ALWAYS use `/* */` multi-line comments** instead
- Minification puts code on one line, causing `//` to comment out everything after it
- This applies to any JavaScript in PHP files that gets minified (MenuView.php, etc.)

```php
// ❌ BAD - Will break when minified
echo '<script>
    // This comment will break minified code
    $(document).ready(function() { ... });
</script>';

// ✅ GOOD - Safe for minification
echo '<script>
    /* This comment is safe for minified code */
    $(document).ready(function() { ... });
</script>';
```

### Why This Matters
Browsers aggressively cache JS/CSS files. Without cache-busting, users will continue using old cached versions even after you deploy changes, leading to:
- Features not working
- JavaScript errors
- Confusing debugging sessions

### How to Handle JS/CSS Changes

**Always update the version parameter** when you modify a JS or CSS file:

```html
<!-- Before modifying js/tiled.js -->
<script src="js/tiled.js?v=20251111"></script>

<!-- After modifying js/tiled.js, increment the version -->
<script src="js/tiled.js?v=20251112"></script>
```

### Version Format
Use `YYYYMMDD` format (today's date) or increment existing version number. The important thing is that it changes.

### Common Files with Version Parameters
Check these files when looking for JS/CSS includes:
- Root `.php` page controllers (index.php, map.php, forum.php, tiled.php, etc.)
- `Classes/Ui.php` (handles some script loading)
- Template/view files in `src/View/`

### Example Workflow
1. Modify `js/tiled.js`
2. Find where it's loaded: `tiled.php` line 182
3. Update: `<script src="js/tiled.js?v=20251111"></script>` → `<script src="js/tiled.js?v=20251112"></script>`
4. Test in browser (hard refresh with Ctrl+F5 if needed)

**Remember**: Forgetting this step will make it appear like your changes don't work!

## Database

- **MariaDB** database named `aoo4`
- Initialized via `db/init_noupdates.sql` on container startup
- Connection config: `config/db_constants.php` (must be created from `.exemple` file)
- Use PHPMyAdmin at `http://localhost:8081` for database inspection

## Configuration Files

### Required Setup Files

1. **`.env`** (from `.env.dist`):
```bash
UID=1000  # Your user ID (run 'id' command)
GID=1000  # Your group ID
```

2. **`config/db_constants.php`** (from `config/db_constants.php.exemple`):
```php
define('DB_CONSTANTS', array(
    'host'=>"mariadb-aoo4:3306",
    'user'=>"root",
    'psw'=>"passwordRoot",
    'db'=>"aoo4",
    'password'=>"passwordRoot",
    'dbname'=>"aoo4",
    'driver' => 'pdo_mysql',
));
```

## CI/CD Pipeline

GitLab CI with multiple stages (`.gitlab-ci.yml`):

1. **build**: Build Docker test image (cached in container registry)
2. **stan**: Run PHPStan static analysis
3. **test**: Run PHPUnit tests with coverage
4. **security**: SQL injection tests with sqlmap (disabled by default)
5. **prepare**: Generate release notes (on tags)
6. **release**: Create GitLab release (on semantic version tags)
7. **deployment**: Deploy to staging/saison-3 environments

**Main branch**: `staging`

**CI optimizations**:
- Docker image caching to avoid rebuilds
- Composer cache via `composer.lock`
- Templates to avoid duplication
- Automatic failure on test errors

## Composer Autoloading

Two PSR-4 namespaces:
- `App\`: maps to `src/`
- `Classes\`: maps to `Classes/`
- `Tests\`: maps to `tests/` (dev)

## Data & Assets

To run the game locally, copy from standalone directories:
```bash
cp -r datas_standalone/* datas/
cp -r img_standalone/* img/
```

Or use symlinks (current setup uses symlink to `aoo_prod_20250821/datas/`).

## Security Testing

SQLMap targets (disabled in CI by default):
```bash
make sqlmap-login      # Test login.php for SQL injection
make sqlmap-register   # Test register.php for SQL injection
```

Results saved to `tmp/security/`.

## Entry Points

- **Web pages**: Root `.php` files (index.php, login.php, forum.php, etc.)
  - Each includes `config.php` (session, autoload, authentication)
  - Delegates to `scripts/*.php` for logic

- **API**: `api/` directory with REST endpoints

- **Console**: `console.php` for admin commands (uses Symfony Console)

## Branching Strategy

- **staging**: Main development branch (default)
- **saison-3**: Season 3 branch
- **main**: Production branch
- CI runs on: staging, saison-3, main

## Game Mechanics

### Core Gameplay Loop

Age of Olympia is a **turn-based survival RPG** where players:
1. Perform actions (move, search, attack, gather resources)
2. Actions consume **PA (Points d'Action)** or **MVT (Mouvement points)**
3. Wait for **DLA (Délai Avant Action)** - cooldown timer between turns
4. Progress through leveling, combat, and resource gathering

### Turn System

**Key Concepts**:
- **Turn**: A player's turn data is stored in `players.turn` (JSON field)
- **PA (Points d'Action)**: Action points for most activities
- **MVT (Mouvement)**: Movement points specifically for moving on the map
- **DLA (Délai Avant Action)**: Cooldown time until next turn (in seconds)
- **nextTurnTime**: Unix timestamp when next turn is available

**Turn Refresh**:
- Players get new PA/MVT when `nextTurnTime` <= current time
- Turn data is loaded via `$player->get_caracs()` which populates `$player->turn`
- Remaining points checked via `$player->getRemaining('pa')` or `$player->getRemaining('mvt')`

### Map System

**Coordinate System**:
- Grid-based map with (x, y, z) coordinates
- **Plans**: Different map layers (e.g., 'gaia', 'tutorial')
- Coordinates stored in `coords` table with unique entries per location
- Players reference `coords_id` in `players` table

**Map Elements**:
- **Walls** (`map_walls`): Impassable terrain, trees, rocks, resources
  - `damages: -1` = récoltable (gatherable resource)
  - `damages: -2` = épuisé (depleted resource)
  - `damages: 0` = normal wall (impassable)
- **Items** (`map_items`): Ground items players can pick up
- **Foregrounds** (`map_foregrounds`): Visual decorations (non-interactive)

**Resource Gathering**:
- Resources are **walls** (in `map_walls` table), NOT items
- Trees (`arbre1`, `arbre2`) and stones (`pierre1`, `pierre2`) are walls
- Check `WALLS_PV` constant in `config/constants.php` for resource types
- Players must move **adjacent** to resource, then use `fouiller` action
- Gathered materials (wood, stone) go to player inventory as items

### Actions System

**Action Types**:
- **Movement**: `se_deplacer` (costs MVT points)
- **Search**: `fouiller` (gather resources from adjacent tiles)
- **Combat**: `attaquer`, `attaque_double`
- **Rest**: `repos` (restore PA/MVT)
- **Training**: `entrainement` (gain XP)
- **Prayer**: `prier` (faction-specific benefits)

**Action Storage**:
- Available actions: `players_actions` table (player_id, name, type, charges)
- Action definitions: `src/Action/*Action.php` (Doctrine entities)
- Options: `players_options` table (player_id, name)

**Important Options**:
- `showActionDetails`: Shows calculation details and dice rolls (now DEFAULT for new players)
- `isAdmin`: Administrator privileges
- `incognitoMode`: PNJ invisibility mode
- `raceHint`: Show race color borders

### Player System

**Player Creation**:
- Main function: `Player::put_player($name, $race, $pnj=false)` in `Classes/Player.php:2021`
- Creates entry in `players` table
- Initializes at coordinates (0,0) on plan 'gaia'
- Default options applied:
  - First player (ID 1) gets `isAdmin`
  - All new players get `showActionDetails` (as of recent change)
  - PNJs get `incognitoMode`

**Player Data**:
- Core data: `$player->data` (loaded via `$player->get_data()`)
- Turn data: `$player->turn` (loaded via `$player->get_caracs()`)
- Coordinates: `$player->coords` (loaded via `$player->getCoords()`)
- Inventory: `$player->inventory` (loaded via `$player->get_inventory()`)

**Player Types**:
- **Regular Players**: Positive IDs (1, 2, 3, ...)
- **NPCs/PNJs**: Negative IDs (-1, -2, -3, ...)
- **Tutorial Players**: Positive IDs, tracked in `tutorial_players` table

### Tutorial System

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
  - `tutorial_players`: Temporary player characters (id, real_player_id, tutorial_session_id, player_id, name, is_active)
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
4. Ensure walls exist in `map_walls` with `damages: -1` (récoltable)
5. Validate with `adjacent_to_position` instead of exact position
6. Use multi-step flow: move → inspect → gather → check inventory

### Important Database Tables

**Players**:
- `players`: Main player data (id, name, race, coords_id, xp, pi, nextTurnTime, turn JSON)
- `players_actions`: Player available actions (player_id, name, type, charges)
- `players_options`: Player preferences (player_id, name)
- `players_items`: Player inventory (player_id, item_id, quantity)
- `players_logs`: Event logs (player_id, target_id, message, timestamp)

**Map**:
- `coords`: Coordinate entries (id, x, y, z, plan)
- `map_walls`: Walls and resources (coords_id, name, damages, pvmax)
- `map_items`: Ground items (coords_id, item_id, quantity)
- `map_foregrounds`: Decorative foregrounds (coords_id, name)

**Tutorial**:
- `tutorial_steps`: Core step definitions (id, version, step_id, next_step, step_number, step_type, title, text, xp_reward, is_active)
- `tutorial_step_ui`, `tutorial_step_validation`, `tutorial_step_prerequisites`, `tutorial_step_features`: 1:1 step configuration tables
- `tutorial_step_highlights`, `tutorial_step_interactions`, `tutorial_step_context_changes`, `tutorial_step_next_preparation`: 1:N step configuration tables
- `tutorial_progress`: Session tracking (tutorial_session_id, player_id, current_step, completed, tutorial_mode, tutorial_version, xp_earned)
- `tutorial_players`: Tutorial characters (id, real_player_id, tutorial_session_id, player_id, name, is_active)
- `tutorial_enemies`: Combat training enemies (tutorial_session_id, enemy_player_id, enemy_coords_id)
- `tutorial_dialogs`: Dialog configurations (dialog_id, npc_name, dialog_data JSON)

### Key Constants (`config/constants.php`)

**Resource Configuration**:
```php
// WALLS_PV defines wall types and their gather behavior
'arbre1' => -1,  // Gatherable tree
'arbre2' => -1,  // Gatherable tree
'pierre1' => -1, // Gatherable stone
'pierre2' => -1, // Gatherable stone
// -1 = récoltable (gatherable)
// -2 = épuisé (depleted)
// 0+ = normal wall hit points
```

**Races**:
- Defined in `RACES` constant
- Each race has faction, starting stats, portrait/avatar paths
- Examples: 'Humain', 'Elfe', 'Nain'

### Frontend Map System

**Map Rendering**:
- Map tiles rendered as `.case` elements with `data-coords` attribute
- Example: `<div class="case" data-coords="0,1" x="0" y="1">`
- Click on tile opens observation panel via `observe.php`
- Player avatar shown as SVG element within tile

**UI Panels**:
- **Actions Panel** (`#ui-card`): Shows available actions for selected tile
- **Characteristics Panel**: Shows player stats
- Panels loaded via AJAX to `observe.php` with coords parameter

**AJAX Flow**:
1. Click map tile → `observe.php?coords=x,y`
2. Server renders observation data (actions, player stats)
3. Response injected into `#ajax-data` div
4. Tutorial system waits for panel visibility before showing tooltips

## Additional Documentation

- Architecture diagram: `docs/images/Logique-Aoo.png`
- Unit test documentation: `docs/unit-test-logs.md`
- Architecture doc: `docs/architecture.md`
