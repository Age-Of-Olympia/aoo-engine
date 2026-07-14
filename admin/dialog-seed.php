<?php
/**
 * Seed des dialogues depuis les JSON legacy (admin dashboard → Dialogues → Seed).
 *
 * Raison d'être : la migration Version20260713150000_DialogsFromJson ne crée
 * que la table — au déploiement, les migrations s'exécutent depuis le
 * checkout git où datas/ (gitignoré) n'existe pas. Cette page lance le seed
 * depuis la racine web, où datas/ existe. Relançable sans risque : les
 * lignes existantes sont préservées (création seulement), voir
 * DialogSeedService.
 *
 * Accès : hérite du niveau du menu dialogs.php (alias AdminMenuAccessService).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\DialogSeedService;

$csrf = new CsrfProtectionService();
$service = new DialogSeedService();
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seed_dialogs'])) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
        $report = $service->seed();
        setFlash('success', sprintf(
            'Seed appliqué : %d dialogue(s) créé(s), %d conservé(s).',
            count($report['created']),
            count($report['kept'])
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
<h1>Dialogues — seed depuis les JSON legacy</h1>

<?= renderFlashMessage() ?>

<div class="alert alert-info" style="font-size: 13px; line-height: 1.5;">
    <strong>Pourquoi cette page ?</strong>
    La migration <code style="display:inline">DialogsFromJson</code> ne crée que la table
    <code style="display:inline">dialogs</code> : en production les migrations s'exécutent depuis le checkout git où
    <code style="display:inline">datas/</code> n'existe pas. Ce bouton seed les dialogues depuis
    <code style="display:inline">datas/*/dialogs/*.json</code> de cet environnement.
    <ul class="mb-0 mt-1">
        <li>Une ligne existante est <strong>préservée telle quelle</strong> (création seulement) : relancer n'écrase jamais un dialogue édité en admin, ni <code style="display:inline">register</code> réécrit à chaque inscription.</li>
        <li>Tant qu'un dialogue n'est pas seedé, le jeu replie sur son fichier JSON — rien ne casse entre-temps.</li>
        <li>Relançable sans risque (tout ou rien, idempotent).</li>
    </ul>
</div>

<?php if ($report !== null && ($report['created'] !== [] || $report['kept'] !== [])): ?>
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title">Bilan du seed</h5>
            <?php if ($report['created'] !== []): ?>
                <p class="mb-1"><strong>Créés :</strong> <?= e(implode(', ', $report['created'])) ?></p>
            <?php endif; ?>
            <?php if ($report['kept'] !== []): ?>
                <p class="mb-1"><strong>Conservés (déjà en base) :</strong> <?= e(implode(', ', $report['kept'])) ?></p>
            <?php endif; ?>
            <a class="btn btn-sm btn-outline-primary mt-2" href="/admin/dialogs.php">Voir la liste des dialogues</a>
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
                Aucun JSON de dialogue trouvé sous <code style="display:inline">datas/*/dialogs/</code> — rien à seeder
                depuis cet environnement.
            </div>
        <?php else: ?>
            <table class="table table-striped table-sm" style="max-width: 760px;">
                <thead><tr>
                    <th>Dialogue</th><th>Fichier</th><th>Action</th><th>Nœuds</th>
                </tr></thead>
                <tbody>
                <?php foreach ($preview['entries'] as $entry): ?>
                    <tr>
                        <td><code><?= e($entry['name']) ?></code>
                            <?= $entry['private'] ? ' <span class="badge badge-secondary">privé</span>' : '' ?></td>
                        <td style="font-size: 12px;"><?= e($entry['file']) ?></td>
                        <td><?= $entry['action'] === 'create'
                            ? '<span class="badge badge-success">créer</span>'
                            : '<span class="badge badge-info">conservé</span>' ?></td>
                        <td><?= (int) $entry['nodes'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <form method="post" class="d-flex align-items-center gap-3">
                <?= $csrf->renderTokenField() ?>
                <button type="submit" name="seed_dialogs" class="btn btn-primary">
                    <i class="fas fa-seedling"></i> Seeder les dialogues manquants
                </button>
                <small class="text-muted">Tout ou rien — re-consultez la liste des dialogues après coup.</small>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
echo admin_layout('Dialogues — seed JSON legacy', $content);
