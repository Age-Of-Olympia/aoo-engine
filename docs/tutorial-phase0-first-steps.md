# Tutorial Refactoring - Phase 0: First Small Steps

**Document Version**: 1.0
**Date**: 2025-11-11
**Status**: Ready to Execute
**Parent Document**: tutorial-refactoring-plan.md

---

## Overview

This document describes the **first incremental steps** to begin tutorial refactoring **without breaking the existing game**. We'll focus on:

1. ✅ Creating isolated infrastructure
2. ✅ Refactoring dialog system to be reusable
3. ✅ Setting up database tables
4. ✅ Creating skeleton classes
5. ✅ Testing in parallel with existing system

**Key Principle**: Everything is **additive and isolated**. The existing tutorial continues to work while we build the new system alongside it.

---

## Step 0.1: Database Schema (Non-Breaking)

**Duration**: 30 minutes
**Risk**: Very Low

### Create Migration

Create `src/Migrations/Version20251111_AddTutorialTables.php`:

```php
<?php

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251111_AddTutorialTables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tutorial_progress and tutorial_configurations tables (Phase 0)';
    }

    public function up(Schema $schema): void
    {
        // Tutorial progress tracking
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_progress (
                id INT AUTO_INCREMENT PRIMARY KEY,
                player_id INT NOT NULL,
                tutorial_session_id VARCHAR(36) NOT NULL,
                current_step INT NOT NULL DEFAULT 0,
                total_steps INT NOT NULL DEFAULT 47,
                completed BOOLEAN DEFAULT FALSE,
                started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                tutorial_mode ENUM('first_time', 'replay', 'practice') DEFAULT 'first_time',
                data JSON NULL COMMENT 'Step-specific data, verification flags',
                FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
                INDEX idx_player_session (player_id, tutorial_session_id),
                INDEX idx_completed (completed)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Tutorial configurations
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_configurations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                version VARCHAR(20) NOT NULL UNIQUE,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                config_json JSON NOT NULL COMMENT 'Full tutorial configuration',
                is_active BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_active (is_active),
                INDEX idx_version (version)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS tutorial_progress');
        $this->addSql('DROP TABLE IF EXISTS tutorial_configurations');
    }
}
```

### Execute Migration

```bash
# Test migration locally first
php vendor/bin/doctrine-migrations migrate --dry-run

# Execute migration
php vendor/bin/doctrine-migrations migrate
```

**Verification**:
```bash
mysql -u root -p aoo4 -e "SHOW TABLES LIKE 'tutorial%';"
```

### Why This is Safe

- ✅ Creates new tables only
- ✅ Doesn't modify existing tables
- ✅ Can be rolled back easily
- ✅ Doesn't affect existing code

---

## Step 0.2: Refactor Dialog System (Make it Reusable)

**Duration**: 2-3 hours
**Risk**: Low (with testing)

### Current Problem

`Classes/Dialog.php` is tightly coupled to game logic:
- Hardcoded paths (`datas/private/dialogs/`, `datas/public/dialogs/`)
- Uses global functions (`json()->decode()`)
- Mixed concerns (data loading + rendering)

### Solution: Create DialogService

Create `src/Service/DialogService.php`:

```php
<?php

namespace App\Service;

use Classes\Dialog;
use Classes\Player;

/**
 * Reusable dialog service for both game and tutorial
 */
class DialogService
{
    private string $dialogBasePath;
    private bool $isTutorialMode;

    public function __construct(bool $isTutorialMode = false)
    {
        $this->isTutorialMode = $isTutorialMode;
        $this->dialogBasePath = $isTutorialMode
            ? 'datas/tutorial/dialogs/'
            : 'datas/private/dialogs/';
    }

    /**
     * Load dialog by name
     *
     * @param string $dialogName
     * @return object|null
     */
    public function loadDialog(string $dialogName): ?object
    {
        // Try tutorial path first if in tutorial mode
        if ($this->isTutorialMode) {
            $tutorialPath = $this->dialogBasePath . $dialogName . '.json';
            if (file_exists($tutorialPath)) {
                return $this->loadDialogFromFile($tutorialPath);
            }
        }

        // Fallback to standard paths
        $privatePath = 'datas/private/dialogs/' . $dialogName . '.json';
        if (file_exists($privatePath)) {
            return $this->loadDialogFromFile($privatePath);
        }

        $publicPath = 'datas/public/dialogs/' . $dialogName . '.json';
        if (file_exists($publicPath)) {
            return $this->loadDialogFromFile($publicPath);
        }

        return null;
    }

    /**
     * Load dialog from file
     */
    private function loadDialogFromFile(string $path): ?object
    {
        $content = file_get_contents($path);
        if (!$content) {
            return null;
        }

        $decoded = json_decode($content);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Dialog JSON error in {$path}: " . json_last_error_msg());
            return null;
        }

        return $decoded;
    }

    /**
     * Render dialog with legacy Dialog class
     *
     * @param string $dialogName
     * @param Player|null $player
     * @param Player|null $target
     * @return string HTML output
     */
    public function renderDialog(
        string $dialogName,
        ?Player $player = null,
        ?Player $target = null
    ): string {
        // For now, use existing Dialog class for rendering
        // This maintains backward compatibility
        $dialog = new Dialog($dialogName, $player, $target);

        ob_start();
        echo $dialog->get_data();
        return ob_get_clean();
    }

    /**
     * Get dialog data without rendering (for API)
     *
     * @param string $dialogName
     * @param Player|null $player
     * @param Player|null $target
     * @return array
     */
    public function getDialogData(
        string $dialogName,
        ?Player $player = null,
        ?Player $target = null
    ): array {
        $dialogJson = $this->loadDialog($dialogName);

        if (!$dialogJson) {
            return [
                'error' => 'Dialog not found',
                'dialog_name' => $dialogName
            ];
        }

        return [
            'id' => $dialogJson->id ?? $dialogName,
            'name' => $this->replacePlaceholders(
                $dialogJson->name ?? '',
                $player,
                $target
            ),
            'nodes' => $this->processDialogNodes(
                $dialogJson->dialog ?? [],
                $player,
                $target
            )
        ];
    }

    /**
     * Process dialog nodes
     */
    private function processDialogNodes(
        array $nodes,
        ?Player $player,
        ?Player $target
    ): array {
        $processed = [];

        foreach ($nodes as $node) {
            $processed[] = [
                'id' => $node->id ?? '',
                'text' => $this->replacePlaceholders(
                    $node->text ?? '',
                    $player,
                    $target
                ),
                'avatar' => $node->avatar ?? null,
                'type' => $node->type ?? null,
                'options' => $this->processOptions(
                    $node->options ?? [],
                    $player,
                    $target
                )
            ];
        }

        return $processed;
    }

    /**
     * Process dialog options
     */
    private function processOptions(
        array $options,
        ?Player $player,
        ?Player $target
    ): array {
        $processed = [];

        foreach ($options as $option) {
            $processed[] = [
                'text' => $this->replacePlaceholders(
                    $option->text ?? '',
                    $player,
                    $target
                ),
                'go' => $option->go ?? null,
                'url' => $option->url ?? null,
                'set' => $option->set ?? null
            ];
        }

        return $processed;
    }

    /**
     * Replace placeholders in text
     */
    private function replacePlaceholders(
        string $text,
        ?Player $player,
        ?Player $target
    ): string {
        if ($player) {
            $text = str_replace('PLAYER_ID', (string)$player->id, $text);
            $text = str_replace('PLAYER_NAME', $player->data->name ?? '', $text);
        }

        if ($target) {
            $text = str_replace('TARGET_ID', (string)$target->id, $text);
            $text = str_replace('TARGET_NAME', $target->data->name ?? '', $text);
        }

        return $text;
    }

    /**
     * Check if in tutorial mode
     */
    public function isTutorialMode(): bool
    {
        return $this->isTutorialMode;
    }
}
```

### Update Existing Dialog Class (Backward Compatible)

Modify `Classes/Dialog.php` to **optionally** use DialogService:

```php
<?php
namespace Classes;

use App\Service\DialogService;

class Dialog {

    private $dialog;
    private $dialogJson;
    private $player;
    private $target;
    private ?DialogService $dialogService = null;

    function __construct($dialog, $player=false, $target=false, bool $useTutorialMode = false) {
        if($player){
            $this->player = $player;
        }

        if($target){
            $this->target = $target;
        }

        // NEW: Use DialogService if requested
        if ($useTutorialMode) {
            $this->dialogService = new DialogService(true);
            $this->dialogJson = $this->dialogService->loadDialog($dialog);
        } else {
            // OLD: Keep existing behavior for backward compatibility
            $this->dialogJson = json()->decode('dialogs', $dialog);
        }

        if(!$this->dialogJson){
            echo '
            <br />
            <button OnClick="$(\'#ui-dialog\').hide()">
                Fermer
            </button>
            ';

            ?>
            <script>
            $(document).ready(function(){
                $('#ui-dialog, .dialog-template').css('height', '150px');
            });
            </script>
            <?php

            exit();
        }
    }

    // ... rest of class unchanged ...
}
```

### Why This is Safe

- ✅ DialogService is **new code**, doesn't modify existing
- ✅ Dialog class has **optional parameter** (defaults to old behavior)
- ✅ Existing game code works unchanged
- ✅ Tutorial can opt-in to new service

### Testing

Create test script `scripts/test_dialog_service.php`:

```php
<?php
require_once(__DIR__ . '/../config.php');

use App\Service\DialogService;
use Classes\Player;

echo "Testing DialogService...\n\n";

// Test 1: Game mode dialog
$gameService = new DialogService(false);
$dialogData = $gameService->getDialogData('marchand');
echo "Game Mode - Marchand Dialog:\n";
print_r($dialogData);
echo "\n";

// Test 2: Tutorial mode dialog (will fail gracefully - no tutorial dialogs yet)
$tutorialService = new DialogService(true);
$dialogData = $tutorialService->getDialogData('gaia_welcome');
echo "Tutorial Mode - Gaia Dialog:\n";
print_r($dialogData);
echo "\n";

// Test 3: Render with player
$player = new Player(1);
$player->get_data();
$html = $gameService->renderDialog('marchand', $player);
echo "Rendered HTML length: " . strlen($html) . " chars\n";

echo "\n✓ DialogService tests complete!\n";
```

Run test:
```bash
php scripts/test_dialog_service.php
```

---

## Step 0.3: Create Skeleton Tutorial Classes

**Duration**: 1-2 hours
**Risk**: Very Low (no integration yet)

### Create Directory Structure

```bash
mkdir -p src/Tutorial
mkdir -p src/Tutorial/Config
mkdir -p src/Tutorial/State
mkdir -p src/Tutorial/Steps
mkdir -p datas/tutorial/dialogs
mkdir -p datas/tutorial/configurations
```

### Create Minimal TutorialContext

Create `src/Tutorial/TutorialContext.php`:

```php
<?php

namespace App\Tutorial;

use Classes\Player;

/**
 * Isolated game context for tutorial (Phase 0 - Skeleton)
 */
class TutorialContext
{
    private Player $player;
    private string $mode;
    private array $tutorialState = [];

    public function __construct(Player $player, string $mode = 'first_time')
    {
        $this->player = $player;
        $this->mode = $mode;
    }

    /**
     * Get player
     */
    public function getPlayer(): Player
    {
        return $this->player;
    }

    /**
     * Get mode
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Get tutorial state
     */
    public function getTutorialState(): array
    {
        return $this->tutorialState;
    }

    /**
     * Set tutorial state value
     */
    public function setState(string $key, $value): void
    {
        $this->tutorialState[$key] = $value;
    }

    /**
     * Get tutorial state value
     */
    public function getState(string $key, $default = null)
    {
        return $this->tutorialState[$key] ?? $default;
    }
}
```

### Create Minimal TutorialManager

Create `src/Tutorial/TutorialManager.php`:

```php
<?php

namespace App\Tutorial;

use Classes\Player;

/**
 * Tutorial Manager (Phase 0 - Skeleton)
 */
class TutorialManager
{
    private TutorialContext $context;
    private string $sessionId;

    public function __construct(Player $player, string $mode = 'first_time')
    {
        $this->context = new TutorialContext($player, $mode);
        $this->sessionId = $this->generateSessionId();
    }

    /**
     * Check if player has completed tutorial
     */
    public static function hasCompletedTutorial(int $playerId): bool
    {
        $db = new \Classes\Db();
        $sql = 'SELECT COUNT(*) as n FROM tutorial_progress
                WHERE player_id = ? AND completed = 1 AND tutorial_mode = "first_time"';
        $result = $db->exe($sql, $playerId);
        $row = $result->fetch_assoc();
        return $row['n'] > 0;
    }

    /**
     * Get context
     */
    public function getContext(): TutorialContext
    {
        return $this->context;
    }

    /**
     * Get session ID
     */
    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * Generate unique session ID
     */
    private function generateSessionId(): string
    {
        return sprintf(
            'tut_%s_%s',
            uniqid('', true),
            bin2hex(random_bytes(4))
        );
    }
}
```

### Create Test Script

Create `scripts/test_tutorial_classes.php`:

```php
<?php
require_once(__DIR__ . '/../config.php');

use App\Tutorial\TutorialManager;
use App\Tutorial\TutorialContext;
use Classes\Player;

echo "Testing Tutorial Classes...\n\n";

// Test 1: Create TutorialContext
$player = new Player(1);
$player->get_data();
$context = new TutorialContext($player, 'first_time');

echo "Context created:\n";
echo "- Player: " . $context->getPlayer()->data->name . "\n";
echo "- Mode: " . $context->getMode() . "\n";
echo "\n";

// Test 2: State management
$context->setState('test_key', 'test_value');
echo "State set: test_key = " . $context->getState('test_key') . "\n";
echo "\n";

// Test 3: Create TutorialManager
$manager = new TutorialManager($player, 'first_time');
echo "Manager created:\n";
echo "- Session ID: " . $manager->getSessionId() . "\n";
echo "- Context player: " . $manager->getContext()->getPlayer()->data->name . "\n";
echo "\n";

// Test 4: Check completion status
$hasCompleted = TutorialManager::hasCompletedTutorial(1);
echo "Has player 1 completed tutorial? " . ($hasCompleted ? 'Yes' : 'No') . "\n";
echo "\n";

echo "✓ Tutorial class tests complete!\n";
```

Run test:
```bash
php scripts/test_tutorial_classes.php
```

### Why This is Safe

- ✅ New classes in new namespace
- ✅ No integration with existing code
- ✅ Can be tested independently
- ✅ Easy to delete if needed

---

## Step 0.4: Create Simple Test Dialog

**Duration**: 30 minutes
**Risk**: Very Low

### Create Tutorial Dialog

Create `datas/tutorial/dialogs/test_welcome.json`:

```json
{
  "id": "test_welcome",
  "name": "Gaïa (Tutorial Test)",
  "type": "pnj",
  "dialog": [
    {
      "id": "bonjour",
      "text": "Bienvenue dans le <strong>nouveau système de tutoriel</strong>! Ceci est un test. Votre nom est <strong>PLAYER_NAME</strong>.",
      "options": [
        {
          "go": "continue",
          "text": "Continuer le test"
        }
      ]
    },
    {
      "id": "continue",
      "text": "Parfait! Le système de dialogue fonctionne. Le DialogService charge correctement les fichiers depuis datas/tutorial/dialogs/.",
      "options": [
        {
          "go": "EXIT",
          "text": "Terminer le test"
        }
      ]
    }
  ]
}
```

### Create Test Page

Create `test_tutorial_dialog.php` in root:

```php
<?php
use Classes\Player;
use Classes\Ui;
use App\Service\DialogService;

require_once('config.php');

$ui = new Ui('Test Tutorial Dialog');

echo '<h1>Test Tutorial Dialog System</h1>';

$player = new Player($_SESSION['playerId'] ?? 1);
$player->get_data();

// Test DialogService in tutorial mode
$dialogService = new DialogService(true);

echo '<h2>Test 1: Load Dialog Data (API format)</h2>';
$dialogData = $dialogService->getDialogData('test_welcome', $player);
echo '<pre>';
print_r($dialogData);
echo '</pre>';

echo '<h2>Test 2: Render Dialog (HTML)</h2>';
$dialogHtml = $dialogService->renderDialog('test_welcome', $player);
echo $dialogHtml;

echo '<h2>Test 3: Fallback to Game Dialog</h2>';
$gameDialogService = new DialogService(false);
$gameDialogHtml = $gameDialogService->renderDialog('marchand', $player);
echo '<p>Game dialog rendered: ' . (strlen($gameDialogHtml) > 0 ? '✓ Success' : '✗ Failed') . '</p>';

echo '<div style="margin-top: 20px;">';
echo '<a href="index.php"><button>Retour au jeu</button></a>';
echo '</div>';
```

### Test the Dialog

1. Navigate to: `http://localhost:9000/test_tutorial_dialog.php`
2. Verify:
   - Dialog data loads correctly
   - HTML renders properly
   - Fallback to game dialogs works

### Cleanup

Once tested, you can either:
- Keep `test_tutorial_dialog.php` for future testing
- Delete it (not needed in production)

---

## Step 0.5: Add Feature Flag

**Duration**: 15 minutes
**Risk**: Very Low

### Create Feature Flag System

Create `src/Tutorial/TutorialFeatureFlag.php`:

```php
<?php

namespace App\Tutorial;

/**
 * Feature flags for tutorial system
 * Allows gradual rollout without breaking existing functionality
 */
class TutorialFeatureFlag
{
    /**
     * Is new tutorial system enabled?
     */
    public static function isEnabled(): bool
    {
        // Check environment variable first
        if (isset($_ENV['TUTORIAL_V2_ENABLED'])) {
            return filter_var($_ENV['TUTORIAL_V2_ENABLED'], FILTER_VALIDATE_BOOLEAN);
        }

        // Check constant (can be set in config.php)
        if (defined('TUTORIAL_V2_ENABLED')) {
            return TUTORIAL_V2_ENABLED === true;
        }

        // Default: disabled
        return false;
    }

    /**
     * Force enable for specific player (for testing)
     */
    public static function isEnabledForPlayer(int $playerId): bool
    {
        if (self::isEnabled()) {
            return true;
        }

        // Allow specific test players (admins)
        $testPlayers = [1, 2, 3]; // Cradek, Dorna, Thyrias
        return in_array($playerId, $testPlayers);
    }
}
```

### Add to Config

Add to `config/constants.php` (or create `config/tutorial.php`):

```php
<?php

// Tutorial system feature flags
define('TUTORIAL_V2_ENABLED', false); // Set to true when ready to deploy
```

### Why This is Important

- ✅ Can develop new system in production safely
- ✅ Can test with specific players only
- ✅ Easy rollback (just flip flag)
- ✅ Gradual rollout possible

---

## Step 0.6: Verification Checklist

Before moving to next phase, verify:

### Database
- [ ] `tutorial_progress` table exists
- [ ] `tutorial_configurations` table exists
- [ ] Can insert and query test data

**Test**:
```bash
mysql -u root -p aoo4 -e "DESC tutorial_progress;"
mysql -u root -p aoo4 -e "DESC tutorial_configurations;"
```

### Code
- [ ] `DialogService` class exists and works
- [ ] `TutorialContext` class exists
- [ ] `TutorialManager` class exists
- [ ] `TutorialFeatureFlag` class exists
- [ ] All test scripts run successfully

**Test**:
```bash
php scripts/test_dialog_service.php
php scripts/test_tutorial_classes.php
```

### Integration
- [ ] Existing game still works normally
- [ ] Old tutorial still works
- [ ] No errors in PHP logs
- [ ] No errors in browser console

**Test**:
```bash
# Start game
# Log in as Cradek
# Click "Rejouer le tutoriel" in account settings
# Verify old tutorial works
```

### Isolation
- [ ] New code is in `src/Tutorial/` namespace
- [ ] New dialogs are in `datas/tutorial/` directory
- [ ] Feature flags work correctly
- [ ] Can toggle features on/off

**Test**:
```php
// In test script
use App\Tutorial\TutorialFeatureFlag;

echo TutorialFeatureFlag::isEnabled() ? "Enabled" : "Disabled";
echo TutorialFeatureFlag::isEnabledForPlayer(1) ? "Enabled for Cradek" : "Disabled";
```

---

## Commit Strategy

### Commit 1: Database Schema
```bash
git add src/Migrations/Version20251111_AddTutorialTables.php
git commit -m "feat(tutorial): add tutorial_progress and tutorial_configurations tables

- Add tutorial_progress table for tracking player progress
- Add tutorial_configurations table for versioned tutorial content
- No impact on existing game functionality
- Can be rolled back safely

Related to #52 (improve tutorial system)"
```

### Commit 2: DialogService
```bash
git add src/Service/DialogService.php
git add Classes/Dialog.php
git add scripts/test_dialog_service.php
git commit -m "refactor(dialog): create reusable DialogService

- Extract dialog loading logic to DialogService
- Support both game and tutorial dialog paths
- Maintain backward compatibility with existing Dialog class
- Add comprehensive tests

Related to #52"
```

### Commit 3: Tutorial Classes
```bash
git add src/Tutorial/TutorialContext.php
git add src/Tutorial/TutorialManager.php
git add src/Tutorial/TutorialFeatureFlag.php
git add scripts/test_tutorial_classes.php
git commit -m "feat(tutorial): add skeleton tutorial classes

- Add TutorialContext for isolated tutorial state
- Add TutorialManager for tutorial orchestration
- Add TutorialFeatureFlag for gradual rollout
- No integration yet, fully isolated

Related to #52"
```

### Commit 4: Test Dialog
```bash
git add datas/tutorial/dialogs/test_welcome.json
git add test_tutorial_dialog.php
git commit -m "test(tutorial): add test dialog for verification

- Add sample tutorial dialog
- Add test page to verify DialogService
- For development/testing only

Related to #52"
```

---

## What's Next?

After completing Phase 0, you'll have:

✅ **Database schema ready**
✅ **Reusable dialog system**
✅ **Skeleton tutorial classes**
✅ **Feature flags for safe deployment**
✅ **Test infrastructure**

**Next Phase (0.7)**:
- Create first real tutorial step
- Integrate with existing tutorial trigger (`index.php?tutorial`)
- Test full flow from start to first step

**Estimated Total Time for Phase 0**: 4-6 hours

**Risk Level**: Very Low (everything is isolated and tested)

---

## Rollback Plan

If anything goes wrong:

### Rollback Database
```bash
php vendor/bin/doctrine-migrations migrate prev
```

### Rollback Code
```bash
git revert HEAD~4..HEAD
```

### Disable Feature
```php
// In config/constants.php
define('TUTORIAL_V2_ENABLED', false);
```

---

## Success Criteria

Phase 0 is complete when:

1. ✅ All database tables created
2. ✅ All test scripts pass
3. ✅ Existing game works unchanged
4. ✅ DialogService can load both game and tutorial dialogs
5. ✅ Feature flags work correctly
6. ✅ Code is committed to git
7. ✅ Documentation is updated

**You are now ready to proceed to Phase 1!**
