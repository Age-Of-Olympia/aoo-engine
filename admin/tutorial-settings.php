<?php
/**
 * Tutorial settings: rewards, offer to new players. Who gets the tutorial
 * is the tutorial module (admin/modules.php).
 */

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php';

use App\Service\CsrfProtectionService;
use App\Tutorial\TutorialFeatureFlag;

$csrf = new CsrfProtectionService();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminPost('/admin/tutorial-settings.php');

    if (isset($_POST['update_global'])) {
        $autoShowNew = isset($_POST['auto_show_new_players']) ? '1' : '0';
        TutorialFeatureFlag::updateSetting('auto_show_new_players', $autoShowNew);

        setFlash('success', 'Réglages généraux enregistrés.');
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
$autoShowNewPlayers = filter_var($settings['auto_show_new_players'] ?? '1', FILTER_VALIDATE_BOOLEAN);
$whitelistedPlayers = TutorialFeatureFlag::getWhitelistedPlayers();

// Load reward settings
$skipRewardXP = (int)($settings['skip_reward_xp'] ?? 50);
$skipRewardPI = (int)($settings['skip_reward_pi'] ?? 50);
$completionRewardXP = (int)($settings['completion_reward_xp'] ?? 390);
$completionRewardPI = (int)($settings['completion_reward_pi'] ?? 390);

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
                    La variable d'environnement <code>TUTORIAL_V2_ENABLED</code> remplace le réglage du module.<br>
                <?php endif; ?>
                <?php if ($hasConstantOverride): ?>
                    La constante <code>TUTORIAL_V2_ENABLED</code> de config.php remplace le réglage du module.<br>
                <?php endif; ?>
                <?php if ($hasPlayerListOverride): ?>
                    La constante <code>TUTORIAL_V2_TEST_PLAYERS</code> remplace la liste des testeurs du module.<br>
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

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-globe"></i> Accès au tutoriel</h3>
        </div>
        <div class="card-body">
            <p>
                <?php if (TutorialFeatureFlag::isEnabled()): ?>
                    <span class="badge badge-success"><i class="fas fa-check"></i> Tout le monde</span>
                <?php else: ?>
                    <span class="badge badge-warning"><i class="fas fa-lock"></i> Testeurs seulement</span>
                    (<?= count($whitelistedPlayers) ?>)
                <?php endif; ?>
            </p>
            <p class="form-text">
                Le tutoriel est un module : on l'active, et on choisit ses testeurs, dans
                <a href="/admin/modules.php">Administration › Modules</a>.
            </p>

            <hr>
            <form method="post">
                <?= $csrf->renderTokenField() ?>

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
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
echo admin_layout('Options du tutoriel', $content);
?>
