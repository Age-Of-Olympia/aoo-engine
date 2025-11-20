<?php
/**
 * Tutorial Admin Panel - Main Dashboard
 *
 * Manage tutorial steps, view player progress, and test configurations
 */

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php';

use Classes\Db;
use App\Service\CsrfProtectionService;

$database = new Db();
$csrf = new CsrfProtectionService();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
    } catch (\RuntimeException $e) {
        setFlash('danger', 'Security validation failed. Please refresh and try again.');
        redirectTo('tutorial.php');
    }

    if (isset($_POST['toggle_step'])) {
        $stepId = (int)$_POST['step_id'];
        $isActive = (int)$_POST['is_active'];

        try {
            $database->exe("UPDATE tutorial_steps SET is_active = ? WHERE id = ?", [$isActive, $stepId]);
            setFlash('success', 'Step ' . ($isActive ? 'enabled' : 'disabled') . ' successfully');
        } catch (Exception $e) {
            error_log("[TutorialAdmin] Toggle error: " . $e->getMessage());
            setFlash('danger', 'Failed to update step');
        }
    }

    if (isset($_POST['delete_step'])) {
        $stepId = (int)$_POST['step_id'];

        try {
            // Delete cascades to all related tables
            $database->exe("DELETE FROM tutorial_steps WHERE id = ?", [$stepId]);
            setFlash('success', 'Step deleted successfully');
        } catch (Exception $e) {
            error_log("[TutorialAdmin] Delete error: " . $e->getMessage());
            setFlash('danger', 'Failed to delete step');
        }
    }

    // Regenerate token after POST
    $csrf->regenerateToken();
}

// Fetch statistics
$stats = [];

// Total steps
$result = $database->exe("SELECT COUNT(*) as total FROM tutorial_steps");
$stats['total'] = $result->fetch_assoc()['total'];

// Active steps
$result = $database->exe("SELECT COUNT(*) as active FROM tutorial_steps WHERE is_active = 1");
$stats['active'] = $result->fetch_assoc()['active'];

// Steps by type
$result = $database->exe("SELECT step_type, COUNT(*) as count FROM tutorial_steps GROUP BY step_type ORDER BY count DESC");
$stats['by_type'] = [];
while ($row = $result->fetch_assoc()) {
    $stats['by_type'][$row['step_type']] = $row['count'];
}

// Tutorial sessions stats
$result = $database->exe("
    SELECT
        COUNT(*) as total_sessions,
        SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN completed = 0 THEN 1 ELSE 0 END) as in_progress
    FROM tutorial_progress
");
$stats['sessions'] = $result->fetch_assoc();

// Fetch all steps
$steps = [];
$result = $database->exe("
    SELECT
        ts.id,
        ts.version,
        ts.step_number,
        ts.step_id,
        ts.next_step,
        ts.step_type,
        ts.title,
        ts.xp_reward,
        ts.is_active,
        ui.interaction_mode,
        ui.target_selector,
        v.requires_validation,
        v.validation_type,
        (SELECT COUNT(*) FROM tutorial_step_interactions tsi WHERE tsi.step_id = ts.id) as interactions_count
    FROM tutorial_steps ts
    LEFT JOIN tutorial_step_ui ui ON ts.id = ui.step_id
    LEFT JOIN tutorial_step_validation v ON ts.id = v.step_id
    ORDER BY ts.step_number
");

while ($row = $result->fetch_assoc()) {
    $steps[] = $row;
}

ob_start();
?>

<div class="container">
    <h1>Tutorial Admin Panel</h1>

    <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-<?=$_SESSION['flash']['type']?>" role="alert">
            <?= htmlspecialchars($_SESSION['flash']['message']) ?>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <!-- Statistics Dashboard -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Total Steps</h5>
                    <p class="card-text display-4"><?=$stats['total']?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Active Steps</h5>
                    <p class="card-text display-4"><?=$stats['active']?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Tutorial Sessions</h5>
                    <p class="card-text display-4"><?=$stats['sessions']['total_sessions']?></p>
                    <small class="text-muted">
                        <?=$stats['sessions']['completed']?> completed,
                        <?=$stats['sessions']['in_progress']?> in progress
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Completion Rate</h5>
                    <p class="card-text display-4">
                        <?php
                        $rate = $stats['sessions']['total_sessions'] > 0
                            ? round(($stats['sessions']['completed'] / $stats['sessions']['total_sessions']) * 100)
                            : 0;
                        echo $rate . '%';
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Steps by Type -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Steps by Type</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($stats['by_type'] as $type => $count): ?>
                    <div class="col-md-3 mb-2">
                        <span class="badge badge-primary"><?=$type?>: <?=$count?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Steps Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2>Tutorial Steps</h2>
            <div>
                <a href="tutorial-step-editor.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add New Step
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Step ID</th>
                            <th>Next Step</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Mode</th>
                            <th>Validation</th>
                            <th>Interactions</th>
                            <th>XP</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($steps as $step): ?>
                            <tr class="<?= $step['is_active'] ? '' : 'table-secondary' ?>">
                                <td><?=$step['step_number']?></td>
                                <td>
                                    <code><?=$step['step_id'] ?? '-'?></code>
                                </td>
                                <td>
                                    <?php if ($step['next_step']): ?>
                                        <code><?=htmlspecialchars($step['next_step'])?></code>
                                    <?php else: ?>
                                        <em class="text-muted">end</em>
                                    <?php endif; ?>
                                </td>
                                <td><?=htmlspecialchars($step['title'])?></td>
                                <td>
                                    <span class="badge badge-info"><?=$step['step_type']?></span>
                                </td>
                                <td><?=$step['interaction_mode'] ?? '-'?></td>
                                <td>
                                    <?php if ($step['requires_validation']): ?>
                                        <span class="badge badge-warning"><?=$step['validation_type']?></span>
                                    <?php else: ?>
                                        <span class="text-muted">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?=$step['interactions_count']?></td>
                                <td class="text-center"><?=$step['xp_reward']?></td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <?= $csrf->renderTokenField() ?>
                                        <input type="hidden" name="step_id" value="<?=$step['id']?>">
                                        <input type="hidden" name="is_active" value="<?=$step['is_active'] ? 0 : 1?>">
                                        <button
                                            type="submit"
                                            name="toggle_step"
                                            class="btn btn-sm btn-<?=$step['is_active'] ? 'success' : 'secondary'?>"
                                            title="<?=$step['is_active'] ? 'Disable' : 'Enable'?>"
                                        >
                                            <i class="fas fa-<?=$step['is_active'] ? 'check' : 'times'?>"></i>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a
                                            href="tutorial-step-editor.php?id=<?=$step['id']?>"
                                            class="btn btn-sm btn-primary"
                                            title="Edit"
                                        >
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-danger"
                                            onclick="confirmDelete(<?=$step['id']?>, '<?=htmlspecialchars($step['title'], ENT_QUOTES)?>')"
                                            title="Delete"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Delete Confirmation Form -->
<form id="deleteForm" method="post" style="display: none;">
    <?= $csrf->renderTokenField() ?>
    <input type="hidden" name="step_id" id="deleteStepId">
    <input type="hidden" name="delete_step" value="1">
</form>

<script>
function confirmDelete(stepId, title) {
    if (confirm('Are you sure you want to delete step "' + title + '"?\n\nThis will also delete:\n- UI configuration\n- Validation rules\n- Prerequisites\n- Allowed interactions\n\nThis action cannot be undone.')) {
        document.getElementById('deleteStepId').value = stepId;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<style>
.card {
    margin-bottom: 1rem;
}

.display-4 {
    font-size: 2.5rem;
    font-weight: 300;
}

.badge {
    padding: 0.35em 0.65em;
    font-size: 0.9em;
}

.table-secondary {
    opacity: 0.6;
}

.btn-group {
    display: flex;
    gap: 0.25rem;
}
</style>

<?php
$content = ob_get_clean();
echo admin_layout('Tutorial Admin', $content);
?>
