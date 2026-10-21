<?php
/**
 * Tutorial settings: who gets the tutorial, rewards, whitelist.
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
    requireAdminPost('/admin/tutorial-settings.php');

    // Update global enabled setting
    if (isset($_POST['update_global'])) {
        $globalEnabled = isset($_POST['global_enabled']) ? '1' : '0';
        $autoShowNew = isset($_POST['auto_show_new_players']) ? '1' : '0';

        TutorialFeatureFlag::updateSetting('global_enabled', $globalEnabled);
        TutorialFeatureFlag::updateSetting('auto_show_new_players', $autoShowNew);

        setFlash('success', 'Réglages généraux enregistrés.');
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
        setFlash('success', 'Liste des joueurs autorisés enregistrée.');
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
                setFlash('success', "{$player['name']} (matricule {$playerId}) ajouté aux joueurs autorisés.");
            } else {
                setFlash('danger', "Matricule {$playerId} introuvable.");
            }
        }
    }

    // Remove a player from whitelist
    if (isset($_POST['remove_player'])) {
        $playerId = (int)($_POST['player_id'] ?? 0);
        if ($playerId > 0) {
            TutorialFeatureFlag::removeWhitelistedPlayer($playerId);
            setFlash('success', "Joueur retiré des joueurs autorisés.");
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

        setFlash('success', 'Récompenses enregistrées.');
    }

    TutorialFeatureFlag::clearCache();

    redirectTo('/admin/tutorial-settings.php');
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
$result = $database->exe("SELECT id, name, race FROM players WHERE player_type = 'real' ORDER BY name");
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

    <!-- Override Warnings -->
    <?php if ($hasEnvOverride || $hasConstantOverride || $hasPlayerListOverride): ?>
        <div class="alert alert-warning">
            <strong><i class="fas fa-exclamation-triangle"></i> Réglage imposé par la configuration du serveur</strong>
            <p class="mt-2 mb-0">
                <?php if ($hasEnvOverride): ?>
                    La variable d'environnement <code>TUTORIAL_V2_ENABLED</code> remplace le réglage ci-dessous.<br>
                <?php endif; ?>
                <?php if ($hasConstantOverride): ?>
                    La constante <code>TUTORIAL_V2_ENABLED</code> de config.php remplace le réglage ci-dessous.<br>
                <?php endif; ?>
                <?php if ($hasPlayerListOverride): ?>
                    La constante <code>TUTORIAL_V2_TEST_PLAYERS</code> remplace la liste des joueurs autorisés.<br>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Reward Settings Card (Full Width) -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-trophy"></i> Récompenses du tutoriel</h3>
        </div>
        <div class="card-body">
            <form method="post">
                <?= $csrf->renderTokenField() ?>

                <div class="row">
                    <!-- Skip Rewards -->
                    <div class="col-md-6">
                        <h5 class="text-danger">
                            <i class="fas fa-forward"></i> Tutoriel passé
                        </h5>
                        <p class="form-text mb-3">
                            Ce que reçoit un joueur qui passe le tutoriel sans le terminer.
                        </p>

                        <div class="form-group">
                            <label for="skip_reward_xp">
                                <strong>XP</strong>
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
                            <small class="form-text">Points d'expérience pour un tutoriel passé</small>
                        </div>

                        <div class="form-group">
                            <label for="skip_reward_pi">
                                <strong>PI</strong>
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
                            <small class="form-text">Points d'investissement pour un tutoriel passé</small>
                        </div>
                    </div>

                    <!-- Completion Rewards -->
                    <div class="col-md-6">
                        <h5 class="text-success">
                            <i class="fas fa-check-circle"></i> Tutoriel terminé
                        </h5>
                        <p class="form-text mb-3">
                            Ce que reçoit un joueur qui termine toutes les étapes.
                        </p>

                        <div class="form-group">
                            <label for="completion_reward_xp">
                                <strong>XP</strong>
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
                            <small class="form-text">Points d'expérience pour un tutoriel terminé</small>
                        </div>

                        <div class="form-group">
                            <label for="completion_reward_pi">
                                <strong>PI</strong>
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
                            <small class="form-text">Points d'investissement pour un tutoriel terminé</small>
                        </div>
                    </div>
                </div>

                <hr>
                <div>
                    <button type="submit" name="update_rewards" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer les récompenses
                    </button>

                    <div class="alert alert-secondary mt-3 mb-0">
                        <small>
                            Terminer le tutoriel rapporte <strong><?= $completionRewardXP - $skipRewardXP ?> XP</strong>
                            et <strong><?= $completionRewardPI - $skipRewardPI ?> PI</strong> de plus que le passer.
                        </small>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <!-- Global Settings Card -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-globe"></i> Réglages généraux</h3>
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
                                    <strong>Tutoriel ouvert à tous</strong>
                                </label>
                            </div>
                            <small class="form-text">
                                Coché : tous les joueurs ont accès au tutoriel.
                                Décoché : seuls les joueurs autorisés y ont accès.
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
                                    <strong>Proposé aux nouveaux joueurs</strong>
                                </label>
                            </div>
                            <small class="form-text">
                                Un nouveau joueur se voit proposer le tutoriel à sa première connexion.
                            </small>
                        </div>

                        <button type="submit" name="update_global" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                    </form>

                    <!-- Current Status -->
                    <hr>
                <div>
                        <h5>État actuel</h5>
                        <table class="table table-sm">
                            <tr>
                                <td>Accès au tutoriel :</td>
                                <td>
                                    <?php if (TutorialFeatureFlag::isEnabled()): ?>
                                        <span class="badge badge-success"><i class="fas fa-check"></i> Tout le monde</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning"><i class="fas fa-lock"></i> Joueurs autorisés seulement</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Joueurs autorisés :</td>
                                <td><span class="badge badge-info"><?= count($whitelistedPlayers) ?></span></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Whitelist Card -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3><i class="fas fa-users"></i> Joueurs autorisés</h3>
                    <span class="badge badge-primary"><?= count($whitelistedPlayers) ?> joueurs</span>
                </div>
                <div class="card-body">
                    <?php if ($hasPlayerListOverride): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle"></i> La liste est fixée dans config.php et ne peut pas être modifiée ici.
                        </div>
                    <?php endif; ?>

                    <!-- Add Player Form -->
                    <form method="post" class="mb-3">
                        <?= $csrf->renderTokenField() ?>
                        <div class="d-flex gap-2">
                            <?php
                            $playerChoices = [];
                            foreach ($allPlayers as $player) {
                                if (!in_array($player['id'], $whitelistedPlayers)) {
                                    $playerChoices[(string) $player['id']] =
                                        $player['name'] . ' (matricule ' . $player['id'] . ', ' . $player['race'] . ')';
                                }
                            }
                            echo formSelect('player_id', $playerChoices, null, '-- Choisir un joueur --',
                                'class="form-control"' . ($hasPlayerListOverride ? ' disabled' : ''));
                            ?>
                            <button
                                type="submit"
                                name="add_player"
                                class="btn btn-success"
                                <?= $hasPlayerListOverride ? 'disabled' : '' ?>
                            >
                                <i class="fas fa-plus"></i> Ajouter
                            </button>
                        </div>
                    </form>

                    <!-- Current Whitelist -->
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Matricule</th>
                                    <th>Nom</th>
                                    <th>Race</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($whitelistedPlayers)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">
                                            Aucun joueur autorisé
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($whitelistedPlayers as $playerId): ?>
                                        <?php $player = $whitelistedPlayerDetails[$playerId] ?? null; ?>
                                        <tr>
                                            <td><?= $playerId ?></td>
                                            <td><?= $player ? e($player['name']) : '<em class="text-muted">Inconnu</em>' ?></td>
                                            <td><?= $player ? e($player['race']) : '-' ?></td>
                                            <td>
                                                <form method="post" class="d-inline">
                                                    <?= $csrf->renderTokenField() ?>
                                                    <input type="hidden" name="player_id" value="<?= $playerId ?>">
                                                    <button
                                                        type="submit"
                                                        name="remove_player"
                                                        class="btn btn-sm btn-outline-danger"
                                                        title="Retirer des joueurs autorisés"
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
                        <hr>
                <div>
                            <details>
                                <summary class="text-muted">
                                    <i class="fas fa-edit"></i> Modifier la liste des matricules
                                </summary>
                                <form method="post" class="mt-2">
                                    <?= $csrf->renderTokenField() ?>
                                    <div class="form-group">
                                        <textarea
                                            name="whitelisted_players"
                                            class="form-control"
                                            rows="2"
                                            placeholder="1, 2, 3"
                                        ><?= e(implode(', ', $whitelistedPlayers)) ?></textarea>
                                        <small class="form-text">Matricules séparés par des virgules</small>
                                    </div>
                                    <button type="submit" name="update_whitelist" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-save"></i> Enregistrer la liste
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
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-flask"></i> Vérifier un joueur</h3>
        </div>
        <div class="card-body">
            <p>Ce joueur a-t-il accès au tutoriel ?</p>
            <div class="d-flex gap-2 align-items-center">
                <input type="number" id="test_player_id" class="form-control" placeholder="Matricule" style="max-width: 150px;">
                <button type="button" class="btn btn-info" onclick="testPlayer()">
                    <i class="fas fa-search"></i> Vérifier
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
        resultEl.innerHTML = '<span class="text-danger">Saisissez un matricule valide</span>';
        return;
    }

    // Check against our local data
    const globalEnabled = <?= json_encode($globalEnabled) ?>;
    const whitelistedPlayers = <?= json_encode($whitelistedPlayers) ?>;

    const hasAccess = globalEnabled || whitelistedPlayers.includes(parseInt(playerId));

    if (hasAccess) {
        resultEl.innerHTML = '<span class="badge badge-success"><i class="fas fa-check"></i> Le matricule ' + playerId + ' a accès au tutoriel</span>';
    } else {
        resultEl.innerHTML = '<span class="badge badge-danger"><i class="fas fa-times"></i> Le matricule ' + playerId + ' n\'a pas accès au tutoriel</span>';
    }
}
</script>


<?php
$content = ob_get_clean();
echo admin_layout('Options du tutoriel', $content);
?>
