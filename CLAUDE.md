# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Age of Olympia v4 (AoO) is a browser-based multiplayer RPG game built with PHP. The game features a turn-based gameplay system with actions, player progression, forums, and real-time interactions. The project uses Doctrine ORM for database management and follows a service-oriented architecture.

## Development Environment

The project runs in a **VSCode Dev Container** with Docker. Three containers are used:
- **webserver** (PHP-AOO4-Local): Apache server running the PHP application
- **mariadb-aoo4**: MariaDB database (port 3306)
- **phpmyadmin**: Database management interface (port 8081)

The webserver is accessible at `http://localhost:9000`.

### Starting the Server

```bash
apache2-foreground
```

**Note**: Apache has a bug where it stops with SIGWINCH when the terminal window is resized. Just restart it if this happens.

### Debugging

Use VSCode's "Listen for Xdebug" debug configuration. Xdebug is pre-configured in the dev container.

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

## Additional Documentation

- Architecture diagram: `docs/images/Logique-Aoo.png`
- Unit test documentation: `docs/unit-test-logs.md`
- Architecture doc: `docs/architecture.md`
