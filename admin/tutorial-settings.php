<?php
/**
 * Tutorial Admin Panel - Feature Flags Settings
 *
 * Manage global tutorial settings and player whitelist
 */

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php';

use Classes\Db;
use App\Service\CsrfProtectionService;
use App\Tutorial\TutorialFeatureFlag;

$database = new Db();
$csrf = new CsrfProtectionService();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
    } catch (\RuntimeException $e) {
        setFlash('danger', 'Security validation failed. Please refresh and try again.');
        redirectTo('tutorial-settings.php');
    }

    // Update global enabled setting
    if (isset($_POST['update_global'])) {
        $globalEnabled = isset($_POST['global_enabled']) ? '1' : '0';
        $autoShowNew = isset($_POST['auto_show_new_players']) ? '1' : '0';

        TutorialFeatureFlag::updateSetting('global_enabled', $globalEnabled);
        TutorialFeatureFlag::updateSetting('auto_show_new_players', $autoShowNew);

        setFlash('success', 'Global settings updated successfully');
    }

    // Update whitelisted players
    if (isset($_POST['update_whitelist'])) {
        $playerIds = $_POST['whitelisted_players'] ?? '';
        // Clean and validate the input
        $ids = array_filter(
            array_map('intval', explode(',', str_replace([' ', "\n", "\r"], '', $playerIds))),
            fn($id) => $id > 0
        );
        $cleanedIds = implode(',', $ids);

        TutorialFeatureFlag::updateSetting('whitelisted_players', $cleanedIds);
        setFlash('success', 'Whitelisted players updated successfully');
    }

    // Add a player to whitelist
    if (isset($_POST['add_player'])) {
        $playerId = (int)($_POST['player_id'] ?? 0);
        if ($playerId > 0) {
            // Verify player exists
            $result = $database->exe("SELECT id, name FROM players WHERE id = ?", [$playerId]);
            $player = $result->fetch_assoc();

            if ($player) {
                TutorialFeatureFlag::addWhitelistedPlayer($playerId);
                setFlash('success', "Player \"{$player['name']}\" (ID: {$playerId}) added to whitelist");
            } else {
                setFlash('danger', "Player ID {$playerId} not found");
            }
        }
    }

    // Remove a player from whitelist
    if (isset($_POST['remove_player'])) {
        $playerId = (int)($_POST['player_id'] ?? 0);
        if ($playerId > 0) {
            TutorialFeatureFlag::removeWhitelistedPlayer($playerId);
            setFlash('success', "Player removed from whitelist");
        }
    }

    // Update reward settings
    if (isset($_POST['update_rewards'])) {
        $skipXP = max(0, (int)($_POST['skip_reward_xp'] ?? 50));
        $skipPI = max(0, (int)($_POST['skip_reward_pi'] ?? 50));
        $completionXP = max(0, (int)($_POST['completion_reward_xp'] ?? 390));
        $completionPI = max(0, (int)($_POST['completion_reward_pi'] ?? 390));

        TutorialFeatureFlag::updateSetting('skip_reward_xp', (string)$skipXP);
        TutorialFeatureFlag::updateSetting('skip_reward_pi', (string)$skipPI);
        TutorialFeatureFlag::updateSetting('completion_reward_xp', (string)$completionXP);
        TutorialFeatureFlag::updateSetting('completion_reward_pi', (string)$completionPI);

        setFlash('success', 'Reward settings updated successfully');
    }

    // Regenerate CSRF token
    $csrf->regenerateToken();
    TutorialFeatureFlag::clearCache();

    redirectTo('tutorial-settings.php');
}

// Load current settings
TutorialFeatureFlag::clearCache();
$settings = TutorialFeatureFlag::getSettings();
$globalEnabled = filter_var($settings['global_enabled'] ?? '0', FILTER_VALIDATE_BOOLEAN);
$autoShowNewPlayers = filter_var($settings['auto_show_new_players'] ?? '1', FILTER_VALIDATE_BOOLEAN);
$whitelistedPlayers = TutorialFeatureFlag::getWhitelistedPlayers();

// Load reward settings
$skipRewardXP = (int)($settings['skip_reward_xp'] ?? 50);
$skipRewardPI = (int)($settings['skip_reward_pi'] ?? 50);
$completionRewardXP = (int)($settings['completion_reward_xp'] ?? 390);
$completionRewardPI = (int)($settings['completion_reward_pi'] ?? 390);

// Fetch player details for whitelist
$whitelistedPlayerDetails = [];
if (!empty($whitelistedPlayers)) {
    $placeholders = implode(',', array_fill(0, count($whitelistedPlayers), '?'));
    $result = $database->exe(
        "SELECT id, name, race FROM players WHERE id IN ($placeholders)",
        $whitelistedPlayers
    );
    while ($row = $result->fetch_assoc()) {
        $whitelistedPlayerDetails[$row['id']] = $row;
    }
}

// Fetch all players for the "add player" dropdown
$allPlayers = [];
$result = $database->exe("SELECT id, name, race FROM players WHERE id > 0 ORDER BY name LIMIT 100");
while ($row = $result->fetch_assoc()) {
    $allPlayers[] = $row;
}

// Check for config overrides
$hasEnvOverride = isset($_ENV['TUTORIAL_V2_ENABLED']);
$hasConstantOverride = defined('TUTORIAL_V2_ENABLED');
$hasPlayerListOverride = defined('TUTORIAL_V2_TEST_PLAYERS');

ob_start();
?>

<div>
    <h1><i class="fas fa-cog"></i> Tutorial Feature Flags</h1>

    <?= renderFlashMessage() ?>

    <!-- Override Warnings -->
    <?php if ($hasEnvOverride || $hasConstantOverride || $hasPlayerListOverride): ?>
        <div class="alert alert-warning">
            <strong><i class="fas fa-exclamation-triangle"></i> Configuration Override Active</strong>
            <p style="margin: 10px 0 0 0; font-size: 0.9em;">
                <?php if ($hasEnvOverride): ?>
                    <code>TUTORIAL_V2_ENABLED</code> environment variable is set - overrides database setting.<br>
                <?php endif; ?>
                <?php if ($hasConstantOverride): ?>
                    <code>TUTORIAL_V2_ENABLED</code> constant is defined in config.php - overrides database setting.<br>
                <?php endif; ?>
                <?php if ($hasPlayerListOverride): ?>
                    <code>TUTORIAL_V2_TEST_PLAYERS</code> constant is defined - overrides whitelist setting.<br>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Reward Settings Card (Full Width) -->
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header">
            <h3><i class="fas fa-trophy"></i> Tutorial Rewards</h3>
        </div>
        <div class="card-body">
            <form method="post">
                <?= $csrf->renderTokenField() ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <!-- Skip Rewards -->
                    <div>
                        <h5 style="margin-bottom: 15px; color: #f44336;">
                            <i class="fas fa-forward"></i> Skip Rewards (Without Completion)
                        </h5>
                        <p style="font-size: 0.9em; color: #666; margin-bottom: 15px;">
                            Rewards given when player skips the tutorial without completing it.
                        </p>

                        <div class="form-group">
                            <label for="skip_reward_xp">
                                <strong>XP Reward</strong>
                            </label>
                            <input
                                type="number"
                                class="form-control"
                                id="skip_reward_xp"
                                name="skip_reward_xp"
                                value="<?= $skipRewardXP ?>"
                                min="0"
                                required
                            >
                            <small class="form-text">Experience points for skipping</small>
                        </div>

                        <div class="form-group">
                            <label for="skip_reward_pi">
                                <strong>PI Reward</strong>
                            </label>
                            <input
                                type="number"
                                class="form-control"
                                id="skip_reward_pi"
                                name="skip_reward_pi"
                                value="<?= $skipRewardPI ?>"
                                min="0"
                                required
                            >
                            <small class="form-text">Investment points for skipping</small>
                        </div>
                    </div>

                    <!-- Completion Rewards -->
                    <div>
                        <h5 style="margin-bottom: 15px; color: #4CAF50;">
                            <i class="fas fa-check-circle"></i> Completion Rewards
                        </h5>
                        <p style="font-size: 0.9em; color: #666; margin-bottom: 15px;">
                            Rewards given when player completes all tutorial steps.
                        </p>

                        <div class="form-group">
                            <label for="completion_reward_xp">
                                <strong>XP Reward</strong>
                            </label>
                            <input
                                type="number"
                                class="form-control"
                                id="completion_reward_xp"
                                name="completion_reward_xp"
                                value="<?= $completionRewardXP ?>"
                                min="0"
                                required
                            >
                            <small class="form-text">Experience points for completion</small>
                        </div>

                        <div class="form-group">
                            <label for="completion_reward_pi">
                                <strong>PI Reward</strong>
                            </label>
                            <input
                                type="number"
                                class="form-control"
                                id="completion_reward_pi"
                                name="completion_reward_pi"
                                value="<?= $completionRewardPI ?>"
                                min="0"
                                required
                            >
                            <small class="form-text">Investment points for completion</small>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                    <button type="submit" name="update_rewards" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Reward Settings
                    </button>

                    <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                        <small>
                            <strong>Current Difference:</strong>
                            Completion gives <strong><?= $completionRewardXP - $skipRewardXP ?> more XP</strong>
                            and <strong><?= $completionRewardPI - $skipRewardPI ?> more PI</strong> than skipping.
                        </small>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
        <!-- Global Settings Card -->
        <div style="flex: 1; min-width: 400px;">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-globe"></i> Global Settings</h3>
                </div>
                <div class="card-body">
                    <form method="post">
                        <?= $csrf->renderTokenField() ?>

                        <div class="form-group">
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    id="global_enabled"
                                    name="global_enabled"
                                    <?= $globalEnabled ? 'checked' : '' ?>
                                    <?= ($hasEnvOverride || $hasConstantOverride) ? 'disabled' : '' ?>
                                >
                                <label class="form-check-label" for="global_enabled">
                                    <strong>Enable Tutorial Globally</strong>
                                </label>
                            </div>
                            <small class="form-text">
                                When enabled, all players can access the tutorial.
                                When disabled, only whitelisted players can access it.
                            </small>
                        </div>

                        <div class="form-group">
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    id="auto_show_new_players"
                                    name="auto_show_new_players"
                                    <?= $autoShowNewPlayers ? 'checked' : '' ?>
                                >
                                <label class="form-check-label" for="auto_show_new_players">
                                    <strong>Auto-show for New Players</strong>
                                </label>
                            </div>
                            <small class="form-text">
                                Automatically prompt new players to start the tutorial.
                            </small>
                        </div>

                        <button type="submit" name="update_global" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Global Settings
                        </button>
                    </form>

                    <!-- Current Status -->
                    <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                        <h5>Current Status</h5>
                        <table class="table table-sm">
                            <tr>
                                <td>Tutorial Access:</td>
                                <td>
                                    <?php if (TutorialFeatureFlag::isEnabled()): ?>
                                        <span class="badge badge-success"><i class="fas fa-check"></i> Everyone</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning"><i class="fas fa-lock"></i> Whitelist Only</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Whitelisted Players:</td>
                                <td><span class="badge badge-info"><?= count($whitelistedPlayers) ?></span></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Whitelist Card -->
        <div style="flex: 1; min-width: 400px;">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3><i class="fas fa-users"></i> Whitelisted Players</h3>
                    <span class="badge badge-primary"><?= count($whitelistedPlayers) ?> players</span>
                </div>
                <div class="card-body">
                    <?php if ($hasPlayerListOverride): ?>
                        <div class="alert alert-warning" style="font-size: 0.9em;">
                            <i class="fas fa-info-circle"></i> Whitelist is defined in config.php and cannot be edited here.
                        </div>
                    <?php endif; ?>

                    <!-- Add Player Form -->
                    <form method="post" class="mb-3">
                        <?= $csrf->renderTokenField() ?>
                        <div class="d-flex" style="gap: 10px;">
                            <select name="player_id" class="form-control" <?= $hasPlayerListOverride ? 'disabled' : '' ?>>
                                <option value="">-- Select Player --</option>
                                <?php foreach ($allPlayers as $player): ?>
                                    <?php if (!in_array($player['id'], $whitelistedPlayers)): ?>
                                        <option value="<?= $player['id'] ?>">
                                            <?= e($player['name']) ?> (ID: <?= $player['id'] ?>, <?= e($player['race']) ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <button
                                type="submit"
                                name="add_player"
                                class="btn btn-success"
                                <?= $hasPlayerListOverride ? 'disabled' : '' ?>
                            >
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                    </form>

                    <!-- Current Whitelist -->
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Race</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($whitelistedPlayers)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">
                                            No players whitelisted
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($whitelistedPlayers as $playerId): ?>
                                        <?php $player = $whitelistedPlayerDetails[$playerId] ?? null; ?>
                                        <tr>
                                            <td><?= $playerId ?></td>
                                            <td><?= $player ? e($player['name']) : '<em class="text-muted">Unknown</em>' ?></td>
                                            <td><?= $player ? e($player['race']) : '-' ?></td>
                                            <td>
                                                <form method="post" style="display: inline;">
                                                    <?= $csrf->renderTokenField() ?>
                                                    <input type="hidden" name="player_id" value="<?= $playerId ?>">
                                                    <button
                                                        type="submit"
                                                        name="remove_player"
                                                        class="btn btn-sm btn-outline-danger"
                                                        title="Remove from whitelist"
                                                        <?= $hasPlayerListOverride ? 'disabled' : '' ?>
                                                    >
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Raw Edit -->
                    <?php if (!$hasPlayerListOverride): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                            <details>
                                <summary style="cursor: pointer; color: #6c757d;">
                                    <i class="fas fa-edit"></i> Edit Raw Player IDs
                                </summary>
                                <form method="post" style="margin-top: 10px;">
                                    <?= $csrf->renderTokenField() ?>
                                    <div class="form-group">
                                        <textarea
                                            name="whitelisted_players"
                                            class="form-control"
                                            rows="2"
                                            placeholder="1, 2, 3"
                                        ><?= e(implode(', ', $whitelistedPlayers)) ?></textarea>
                                        <small class="form-text">Comma-separated player IDs</small>
                                    </div>
                                    <button type="submit" name="update_whitelist" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-save"></i> Update Raw List
                                    </button>
                                </form>
                            </details>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Test Section -->
    <div class="card" style="margin-top: 20px;">
        <div class="card-header">
            <h3><i class="fas fa-flask"></i> Quick Test</h3>
        </div>
        <div class="card-body">
            <p>Test if a specific player can access the tutorial:</p>
            <div class="d-flex" style="gap: 10px; align-items: center;">
                <input type="number" id="test_player_id" class="form-control" placeholder="Player ID" style="max-width: 150px;">
                <button type="button" class="btn btn-info" onclick="testPlayer()">
                    <i class="fas fa-search"></i> Test Access
                </button>
                <span id="test_result"></span>
            </div>
        </div>
    </div>
</div>

<script>
function testPlayer() {
    const playerId = document.getElementById('test_player_id').value;
    const resultEl = document.getElementById('test_result');

    if (!playerId || playerId < 1) {
        resultEl.innerHTML = '<span class="text-danger">Enter a valid player ID</span>';
        return;
    }

    // Check against our local data
    const globalEnabled = <?= json_encode($globalEnabled) ?>;
    const whitelistedPlayers = <?= json_encode($whitelistedPlayers) ?>;

    const hasAccess = globalEnabled || whitelistedPlayers.includes(parseInt(playerId));

    if (hasAccess) {
        resultEl.innerHTML = '<span class="badge badge-success"><i class="fas fa-check"></i> Player ID ' + playerId + ' CAN access tutorial</span>';
    } else {
        resultEl.innerHTML = '<span class="badge badge-danger"><i class="fas fa-times"></i> Player ID ' + playerId + ' CANNOT access tutorial</span>';
    }
}
</script>

<style>
details summary {
    padding: 8px 0;
}
details summary::-webkit-details-marker {
    display: none;
}
details summary::before {
    content: '▶ ';
    font-size: 0.8em;
}
details[open] summary::before {
    content: '▼ ';
}
.form-check {
    padding-left: 0;
}
.form-check-input {
    margin-right: 10px;
    width: 18px;
    height: 18px;
    vertical-align: middle;
}
.form-check-label {
    vertical-align: middle;
}
</style>

<?php
$content = ob_get_clean();
echo admin_layout('Tutorial Feature Flags', $content);
?>
