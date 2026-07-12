<?php
/**
 * Seed des factions depuis les JSON legacy (admin dashboard → Factions → Seed).
 *
 * Raison d'être : la migration Version20260713120000_FactionsFromJson
 * s'exécute au déploiement depuis le checkout git, où datas/ (gitignoré)
 * n'existe pas — sur le serveur elle ne trouve donc aucun JSON et ne crée que
 * des lignes minimales (nom déduit du code, aucun rôle). Cette page relance
 * le même seed depuis la racine web, où datas/ existe. Relançable sans
 * risque : voir FactionSeedService pour les règles de préservation
 * (drapeaux hidden/secret, lore non vide, rôles édités en admin).
 *
 * Accès : hérite du niveau du menu factions.php (alias AdminMenuAccessService).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\FactionSeedService;

$csrf = new CsrfProtectionService();
$service = new FactionSeedService();
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seed_factions'])) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
        $report = $service->seed();
        setFlash('success', sprintf(
            'Seed appliqué : %d faction(s) créée(s), %d mise(s) à jour.',
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
<h1>Factions — seed depuis les JSON legacy</h1>

<?= renderFlashMessage() ?>

<div class="alert alert-info" style="font-size: 13px; line-height: 1.5;">
    <strong>Pourquoi cette page ?</strong>
    La migration <code style="display:inline">FactionsFromJson</code> seed les factions depuis
    <code style="display:inline">datas/*/factions/*.json</code>, mais en production les migrations s'exécutent depuis
    le checkout git où <code style="display:inline">datas/</code> n'existe pas : les lignes y sont créées sans lore,
    sans icône et sans rôles. Ce bouton relance le même seed depuis la racine web, où les JSON existent.
    <ul class="mb-0 mt-1">
        <li>Sur une faction existante : drapeaux (cachée/secrète) et lore non vide sont <strong>préservés</strong> ; nom, icône et plan de respawn sont rafraîchis.</li>
        <li>Les rôles ne sont remplacés que si le JSON en fournit.</li>
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
            <a class="btn btn-sm btn-outline-primary mt-2" href="/admin/factions.php">Voir la liste des factions</a>
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
                Aucun JSON de faction trouvé sous <code style="display:inline">datas/*/factions/</code> — rien à
                seeder depuis cet environnement.
            </div>
        <?php else: ?>
            <table class="table table-striped table-sm" style="max-width: 760px;">
                <thead><tr>
                    <th>Faction</th><th>Fichier</th><th>Action</th><th>Rôles du JSON</th>
                </tr></thead>
                <tbody>
                <?php foreach ($preview['entries'] as $entry): ?>
                    <tr>
                        <td><code><?= e($entry['code']) ?></code>
                            <?= $entry['private'] ? ' <span class="badge badge-secondary">privée</span>' : '' ?></td>
                        <td style="font-size: 12px;"><?= e($entry['file']) ?></td>
                        <td><?= $entry['action'] === 'create'
                            ? '<span class="badge badge-success">créer</span>'
                            : '<span class="badge badge-info">mettre à jour</span>' ?></td>
                        <td><?= (int) $entry['roles'] ?> rôle(s)</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <form method="post" class="d-flex align-items-center gap-3">
                <?= $csrf->renderTokenField() ?>
                <button type="submit" name="seed_factions" class="btn btn-primary">
                    <i class="fas fa-seedling"></i> Seeder <?= count($preview['entries']) ?> faction(s)
                </button>
                <small class="text-muted">Tout ou rien — re-consultez la liste des factions après coup pour vérifier.</small>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
echo admin_layout('Factions — seed JSON legacy', $content);
