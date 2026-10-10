<?php
/**
 * Placed entities whose family (building, decor, resource…) differs from
 * their type's, and the button that aligns them. Same path as the type save:
 * EntityRekindService.
 */
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php';

use App\Enum\EntityCategory;
use App\Service\CsrfProtectionService;
use App\Service\Map\EntityRekindService;

$csrf = new CsrfProtectionService();
$service = new EntityRekindService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
    } catch (RuntimeException $e) {
        setFlash('danger', $e->getMessage());
        redirectTo('entity-kinds.php');
    }

    $ids = match (true) {
        isset($_POST['rekind_one']) => [(int) $_POST['rekind_one']],
        isset($_POST['rekind_all']) => array_column($service->mismatches(), 'id'),
        default => array_map('intval', (array) ($_POST['ids'] ?? [])),
    };
    if ($ids === []) {
        setFlash('warning', 'Aucune entité sélectionnée.');
    } else {
        setFlash('success', $service->rekind($ids) . ' entité(s) corrigée(s).');
    }
    redirectTo('entity-kinds.php'); // PRG
}

$rows = $service->mismatches();
$families = EntityCategory::structureFamilies();

ob_start();
?>

<div class="container">
    <h3>Catégories à corriger</h3>

    <?= renderFlashMessage() ?>

    <div class="alert alert-info" style="font-size: 13px; line-height: 1.5;">
        Entités posées dont la catégorie ne correspond plus à celle de leur type, par exemple un arbre posé
        comme bâtiment alors que son type est une ressource. Corriger une entité lui donne la catégorie de son
        type : l'id, la position, les PV, le propriétaire et l'inventaire ne changent pas. Une entité qui quitte
        la catégorie Bâtiment perd son dialogue et son chantier en cours.
        Enregistrer un type corrige aussi tous ses exemplaires.
    </div>

    <form method="post" class="mb-0">
        <?= $csrf->renderTokenField() ?>

        <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
            <span class="badge bg-secondary"><?= count($rows) ?> entité(s)</span>
            <?php if ($rows !== []): ?>
                <button type="submit" name="rekind_all" value="1" class="btn btn-sm btn-primary ml-auto"
                        onclick="return confirm('Corriger les <?= count($rows) ?> entités de la liste ?');">
                    Tout corriger
                </button>
            <?php endif; ?>
        </div>

        <?php if ($rows === []): ?>
            <div class="alert alert-success mb-0">Toutes les entités posées ont la catégorie de leur type.</div>
        <?php else: ?>
            <table class="table table-sm table-striped" style="font-size:13px;" data-admin-list data-page-size="50"
                   data-facets="type:Type,placed:Catégorie posée,target:Catégorie du type,plan:Plan">
                <thead><tr>
                    <th style="width:28px;"><input type="checkbox" title="Sélectionner les lignes affichées"
                        onclick="this.closest('table').querySelectorAll('tbody tr').forEach(r => { if (r.offsetParent !== null) r.querySelector('input').checked = this.checked; })"></th>
                    <th>Id</th><th>Type</th><th>Plan</th><th>Case (x,y,z)</th>
                    <th>Catégorie posée</th><th>Catégorie du type</th><th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr data-type="<?= e($row['race']) ?>" data-plan="<?= e($row['plan'] ?? '—') ?>"
                        data-placed="<?= e($families[$row['player_type']] ?? $row['player_type']) ?>"
                        data-target="<?= e($families[$row['type_kind']] ?? $row['type_kind']) ?>">
                        <td><input type="checkbox" name="ids[]" value="<?= $row['id'] ?>"></td>
                        <td><?= $row['id'] ?> <small class="text-muted">#<?= $row['display_id'] ?></small></td>
                        <td><?= e($row['label']) ?> <code><?= e($row['race']) ?></code></td>
                        <td><?= e($row['plan'] ?? '—') ?></td>
                        <td><?= $row['x'] !== null ? "{$row['x']},{$row['y']},{$row['z']}" : '—' ?></td>
                        <td><?= e($families[$row['player_type']] ?? $row['player_type']) ?></td>
                        <td><?= e($families[$row['type_kind']] ?? $row['type_kind']) ?></td>
                        <td><button type="submit" name="rekind_one" value="<?= $row['id'] ?>"
                                    class="btn btn-sm btn-outline-primary">Corriger</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn-sm btn-primary">Corriger la sélection</button>
        <?php endif; ?>
    </form>
</div>

<?php
$content = ob_get_clean();
echo admin_layout('Catégories à corriger', $content);
