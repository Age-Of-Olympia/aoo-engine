<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionCatalogService;
use App\Service\SkillOwnershipService;
use App\View\Action\ExportButtonView;

$catalog = new ActionCatalogService();
$actions = $catalog->listActions();
$ownerCounts = (new SkillOwnershipService())->actionOwnerCounts();
$exportButton = new ExportButtonView();

ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0">Actions</h1>
    <?= $exportButton->all() ?>
</div>
<p class="text-muted mb-3"><?= count($actions) ?> action(s)</p>

<table class="table table-striped table-hover">
    <thead>
        <tr>
            <th>Name</th>
            <th>Display name</th>
            <th>Type</th>
            <th>Level</th>
            <th>Category</th>
            <th>Conditions</th>
            <th>Outcomes</th>
            <th>Joueurs</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($actions as $action): ?>
            <?php $owners = $ownerCounts[$action->getName()] ?? 0; ?>
            <tr>
                <td><?= e($action->getName()) ?></td>
                <td><?= e($action->getDisplayName()) ?></td>
                <td><span class="badge badge-info"><?= e(action_type_label($action)) ?></span></td>
                <td><?= e($action->getLevel()) ?></td>
                <td><?= e($action->getCategory()) ?></td>
                <td><?= $action->getConditions()->count() ?></td>
                <td><?= $action->getOutcomes()->count() ?></td>
                <td>
                    <?php if ($owners > 0): ?>
                        <a href="/admin/skill-owners.php?type=action&amp;name=<?= urlencode($action->getName()) ?>"><?= $owners ?> joueur<?= $owners > 1 ? 's' : '' ?></a>
                    <?php else: ?>
                        <span class="text-muted">0</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a class="btn btn-sm btn-outline-primary" href="/admin/action-workbench.php?id=<?= (int) $action->getId() ?>">Edit</a>
                    <?= $exportButton->single((int) $action->getId()) ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
$content = ob_get_clean();
echo admin_layout('Actions', $content);
