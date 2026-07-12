<?php
/**
 * Seed des races depuis les JSON legacy (admin dashboard → Races → Seed).
 *
 * Raison d'être : la migration Version20260710120000_RacesFromJson s'exécute
 * au déploiement depuis le checkout git, où datas/ (gitignoré) n'existe pas —
 * sur le serveur elle ne trouve donc aucun JSON et laisse des lignes à stats
 * nulles. Cette page relance le même seed depuis la racine web, où datas/
 * existe. Relançable sans risque : voir RaceSeedService pour les règles de
 * préservation (drapeaux, description, listes éditées en admin).
 *
 * Accès : hérite du niveau du menu races.php (alias AdminMenuAccessService).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\RaceSeedService;

$csrf = new CsrfProtectionService();
$service = new RaceSeedService();
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seed_races'])) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
        $report = $service->seed();
        setFlash('success', sprintf(
            'Seed appliqué : %d race(s) créée(s), %d mise(s) à jour.',
            count($report['created']),
            count($report['updated'])
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
<h1>Races — seed depuis les JSON legacy</h1>

<?= renderFlashMessage() ?>

<div class="alert alert-info" style="font-size: 13px; line-height: 1.5;">
    <strong>Pourquoi cette page ?</strong>
    La migration <code style="display:inline">RacesFromJson</code> seed les races depuis
    <code style="display:inline">datas/*/races/*.json</code>, mais en production les migrations s'exécutent depuis le
    checkout git où <code style="display:inline">datas/</code> n'existe pas : les lignes y sont créées avec des stats
    nulles. Ce bouton relance le même seed depuis la racine web, où les JSON existent.
    <ul class="mb-0 mt-1">
        <li>Sur une race existante : drapeaux (jouable/cachée), description non vide et compteurs de portraits sont <strong>préservés</strong> ; libellé, couleurs, faction, plan, animateur et les 16 caractéristiques sont rafraîchis.</li>
        <li>Les listes (actions de départ, sorts) ne sont remplacées que si le JSON en fournit.</li>
        <li>Relançable sans risque (tout ou rien, idempotent).</li>
    </ul>
</div>

<?php if ($report !== null && ($report['created'] !== [] || $report['updated'] !== [])): ?>
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title">Bilan du seed</h5>
            <?php if ($report['created'] !== []): ?>
                <p class="mb-1"><strong>Créées :</strong> <?= e(implode(', ', $report['created'])) ?></p>
            <?php endif; ?>
            <?php if ($report['updated'] !== []): ?>
                <p class="mb-1"><strong>Mises à jour :</strong> <?= e(implode(', ', $report['updated'])) ?></p>
            <?php endif; ?>
            <a class="btn btn-sm btn-outline-primary mt-2" href="/admin/races.php">Voir la liste des races</a>
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
                Aucun JSON de race trouvé sous <code style="display:inline">datas/*/races/</code> — rien à seeder
                depuis cet environnement.
            </div>
        <?php else: ?>
            <table class="table table-striped table-sm" style="max-width: 760px;">
                <thead><tr>
                    <th>Race</th><th>Fichier</th><th>Action</th><th>Listes du JSON</th>
                </tr></thead>
                <tbody>
                <?php foreach ($preview['entries'] as $entry): ?>
                    <tr>
                        <td><code><?= e($entry['name']) ?></code>
                            <?= $entry['private'] ? ' <span class="badge badge-secondary">privée</span>' : '' ?></td>
                        <td style="font-size: 12px;"><?= e($entry['file']) ?></td>
                        <td><?= $entry['action'] === 'create'
                            ? '<span class="badge badge-success">créer</span>'
                            : '<span class="badge badge-info">mettre à jour</span>' ?></td>
                        <td><?= (int) $entry['starterActions'] ?> actions, <?= (int) $entry['spells'] ?> sorts</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <form method="post" class="d-flex align-items-center gap-3">
                <?= $csrf->renderTokenField() ?>
                <button type="submit" name="seed_races" class="btn btn-primary">
                    <i class="fas fa-seedling"></i> Seeder <?= count($preview['entries']) ?> race(s)
                </button>
                <small class="text-muted">Tout ou rien — re-consultez la liste des races après coup pour vérifier.</small>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
echo admin_layout('Races — seed JSON legacy', $content);
