<?php
/**
 * Seed des plans depuis les JSON legacy (admin dashboard → Cartes → Seed).
 *
 * Raison d'être : la migration Version20260821100000_PlansLeaveTheirJson crée
 * le schéma au déploiement depuis le checkout git, où datas/ (gitignoré)
 * n'existe pas — les lignes se sèment donc ici, depuis la racine web où les
 * JSON existent. Create-only : une ligne existante n'est jamais touchée,
 * les éditions admin survivent à un re-run.
 *
 * Accès : hérite du niveau du menu plans.php (alias AdminMenuAccessService).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\PlanSeedService;

$csrf = new CsrfProtectionService();
$service = new PlanSeedService();
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seed_plans'])) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
        $report = $service->seed();
        setFlash('success', sprintf(
            'Seed appliqué : %d plan(s) créé(s), %d déjà en base (préservés).',
            count($report['created']),
            count($report['skipped'])
        ));
    } catch (\Throwable $e) {
        setFlash('danger', 'Échec du seed : ' . $e->getMessage());
    }
}

try {
    $preview = $service->preview();
} catch (\Throwable $e) {
    $preview = ['entries' => [], 'unreadable' => []];
    setFlash('danger', 'Analyse impossible : ' . $e->getMessage());
}

ob_start();
?>
<h1>Plans — seed depuis les JSON legacy</h1>

<?= renderFlashMessage() ?>

<div class="alert alert-info" style="font-size: 13px; line-height: 1.5;">
    <strong>Pourquoi cette page ?</strong>
    La configuration des plans vit désormais en base (<code style="display:inline">plans</code>,
    <code style="display:inline">plan_z_levels</code>) ; la migration crée le schéma depuis le checkout git,
    où <code style="display:inline">datas/</code> n'existe pas. Ce bouton sème les lignes depuis
    <code style="display:inline">datas/*/plans/*.json</code> de cet environnement.
    <ul class="mb-0 mt-1">
        <li><strong>Create-only</strong> : un plan déjà en base n'est jamais modifié — relançable sans risque.</li>
        <li>Les clés mortes (<code style="display:inline">exits</code>, <code style="display:inline">enters</code>,
            <code style="display:inline">id</code>, <code style="display:inline">num_z_levels</code>) sont
            volontairement abandonnées ; toute autre clé inconnue est signalée ci-dessous.</li>
        <li>Après le seed, l'admin Plans édite la base — les fichiers JSON ne sont plus ni lus ni écrits.</li>
    </ul>
</div>

<?php if ($report !== null): ?>
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title">Bilan du seed</h5>
            <?php if ($report['created'] !== []): ?>
                <p class="mb-1"><strong>Créés :</strong> <?= e(implode(', ', $report['created'])) ?></p>
            <?php endif; ?>
            <?php if ($report['skipped'] !== []): ?>
                <p class="mb-1"><strong>Déjà en base (préservés) :</strong> <?= e(implode(', ', $report['skipped'])) ?></p>
            <?php endif; ?>
            <?php if ($report['unreadable'] !== []): ?>
                <p class="mb-1 text-danger"><strong>Illisibles :</strong> <?= e(implode(', ', $report['unreadable'])) ?></p>
            <?php endif; ?>
            <?php foreach ($report['warnings'] as $slug => $slugWarnings): ?>
                <p class="mb-1 text-warning"><strong><?= e($slug) ?> :</strong> <?= e(implode(' · ', $slugWarnings)) ?></p>
            <?php endforeach; ?>
            <a class="btn btn-sm btn-outline-primary mt-2" href="/admin/plans.php">Voir la liste des plans</a>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <h5 class="card-title">JSON trouvés dans cet environnement</h5>

        <?php if ($preview['unreadable'] !== []): ?>
            <div class="alert alert-warning py-1" style="font-size: 13px;">
                Fichiers illisibles (ignorés) : <?= e(implode(', ', $preview['unreadable'])) ?>
            </div>
        <?php endif; ?>

        <?php if ($preview['entries'] === []): ?>
            <div class="alert alert-warning mb-0">
                Aucun JSON de plan trouvé sous <code style="display:inline">datas/*/plans/</code> — rien à seeder
                depuis cet environnement.
            </div>
        <?php else: ?>
            <table class="table table-striped table-sm" style="max-width: 860px;">
                <thead><tr>
                    <th>Plan</th><th>Nom</th><th>Niveaux z</th><th>Action</th><th>Avertissements</th>
                </tr></thead>
                <tbody>
                <?php foreach ($preview['entries'] as $entry): ?>
                    <tr>
                        <td><code><?= e($entry['slug']) ?></code></td>
                        <td><?= e($entry['name']) ?></td>
                        <td><?= (int) $entry['zLevels'] ?></td>
                        <td><?= $entry['inDb']
                            ? '<span class="badge badge-secondary">déjà en base</span>'
                            : '<span class="badge badge-success">créer</span>' ?></td>
                        <td style="font-size: 12px;"><?= e(implode(' · ', $entry['warnings'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <form method="post" class="d-flex align-items-center gap-3">
                <?= $csrf->renderTokenField() ?>
                <button type="submit" name="seed_plans" class="btn btn-primary">
                    <i class="fas fa-seedling"></i> Seeder les plans manquants
                </button>
                <small class="text-muted">Create-only — les plans déjà en base ne bougent pas.</small>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
echo admin_layout('Plans — seed JSON legacy', $content);
