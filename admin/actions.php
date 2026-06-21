<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\Action\ActionCatalogService;

$catalog = new ActionCatalogService();
$actions = $catalog->listActions();

ob_start();
?>
<h1>Actions</h1>
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
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($actions as $action): ?>
            <tr>
                <td><?= e($action->getName()) ?></td>
                <td><?= e($action->getDisplayName()) ?></td>
                <td><span class="badge badge-info"><?= e(action_type_label($action)) ?></span></td>
                <td><?= e($action->getLevel()) ?></td>
                <td><?= e($action->getCategory()) ?></td>
                <td><?= $action->getConditions()->count() ?></td>
                <td><?= $action->getOutcomes()->count() ?></td>
                <td><a class="btn btn-sm btn-outline-primary" href="/admin/action-workbench.php?id=<?= (int) $action->getId() ?>">Edit</a></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
$content = ob_get_clean();
echo admin_layout('Actions', $content);
