<?php
/**
 * Renommage de saison des plans (admin dashboard → Cartes → Renommage de
 * saison) : les plans de la saison courante prennent le nom de base, une
 * archive déplacée garde sa saison en suffixe (gaia + gaia_s2 → gaia_s1 +
 * gaia). Cérémonie rejouable à chaque ouverture de saison — l'aperçu dit
 * tout ce qui sera renommé, et pourquoi certains plans sont ignorés.
 *
 * Accès : hérite du niveau du menu plans.php (alias AdminMenuAccessService).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\PlanSeasonRenameService;
use App\Service\SeasonService;

$csrf = new CsrfProtectionService();
$service = new PlanSeasonRenameService();
$report = null;

/* The POST renders its own report on the same request: no redirect. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_renames'])) {
    requireAdminPost('/admin/plan-season-rename.php');
    try {
        $report = $service->apply();
        if ($report['failed'] !== null) {
            setFlash('danger', sprintf(
                'Arrêté sur « %s » → « %s » : %s (%d renommage(s) déjà faits restent faits).',
                $report['failed']['from'],
                $report['failed']['to'],
                $report['failed']['error'],
                count($report['renamed'])
            ));
        } else {
            setFlash('success', count($report['renamed']) . ' plan(s) renommé(s).');
        }
    } catch (\Throwable $e) {
        setFlash('danger', 'Échec : ' . $e->getMessage());
    }
}

try {
    $preview = $service->preview();
} catch (\Throwable $e) {
    $preview = ['operations' => [], 'skipped' => []];
    setFlash('danger', 'Analyse impossible : ' . $e->getMessage());
}

$currentSeason = (new SeasonService())->current();

ob_start();
?>
<?php if ($report !== null && $report['renamed'] !== []): ?>
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title">Bilan</h5>
            <?php foreach ($report['renamed'] as $done): ?>
                <p class="mb-1"><code><?= e($done['from']) ?></code> → <code><?= e($done['to']) ?></code>
                    <?= $done['pinnedBg'] !== null ? ' <small class="text-muted">(fond épinglé : ' . e($done['pinnedBg']) . ')</small>' : '' ?></p>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <h5 class="card-title">Ce qu'un lancement ferait</h5>

        <?php if ($preview['skipped'] !== []): ?>
            <div class="alert alert-warning py-1">
                <?php foreach ($preview['skipped'] as $slug => $why): ?>
                    <div><code><?= e($slug) ?></code> — <?= e($why) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($preview['operations'] === []): ?>
            <div class="alert alert-success mb-0">Rien à renommer : les noms sont déjà normalisés.</div>
        <?php else: ?>
            <table class="table table-striped table-sm">
                <thead><tr><th>Ordre</th><th>De</th><th>Vers</th><th>Nature</th><th>Fond épinglé</th></tr></thead>
                <tbody>
                <?php foreach ($preview['operations'] as $i => $op): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><code><?= e($op['from']) ?></code></td>
                        <td><code><?= e($op['to']) ?></code></td>
                        <td><?= $op['kind'] === 'archive'
                            ? '<span class="badge bg-secondary">archive déplacée</span>'
                            : '<span class="badge bg-success">suffixe retiré</span>' ?></td>
                        <td class="small"><?= $op['pinBg'] !== null ? e($op['pinBg']) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <form method="post" class="d-flex align-items-center gap-3"
                  data-confirm="Renommer <?= count($preview['operations']) ?> plan(s) ? Chaque renommage est définitif (l'historique est renommé aussi).">
                <?= $csrf->renderTokenField() ?>
                <button type="submit" name="apply_renames" class="btn btn-primary">
                    <i class="fas fa-signature"></i> Renommer <?= count($preview['operations']) ?> plan(s)
                </button>
                <small class="text-muted">Un renommage à la fois — en cas d'échec, ce qui est fait reste fait.</small>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
ob_start();
?>
<strong>Pourquoi cette page ?</strong>
    La saison est une colonne (<code>plans.season</code>) : le suffixe
    <code>_sX</code> du nom est un reste de l'ancien système. Cette page donne le nom de base
    aux plans de la <strong>saison courante (<?= (int) $currentSeason ?>)</strong> et ajoute leur numéro de saison
    au nom des archives. Rejouable à chaque ouverture de saison.
    <ul class="mb-0 mt-1">
        <li>Chaque renommage est répercuté partout : coords, journaux, rendements, réglages, téléporteurs,
            conditions d'action, PNG de minimap.</li>
        <li>Un plan sans fond configuré, qui utilise <code>img/tiles/&lt;nom&gt;</code>,
            reçoit ce fond dans sa config avant le renommage : l'affichage ne change pas.</li>
        <li>Les conflits (nom de base déjà utilisé par la saison courante ou par un plan de toutes saisons) sont ignorés
            et signalés : à trancher à la main.</li>
    </ul>
<?php
echo admin_layout('Renommage de saison', $content, ['intro' => ob_get_clean()]);
