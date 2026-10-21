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
use App\Tutorial\TutorialCatalogService;
use App\Tutorial\TutorialStepRepository;

$database = new Db();
$csrf = new CsrfProtectionService();
$catalogService = new TutorialCatalogService();
$stepRepository = new TutorialStepRepository();

// Get selected version filter
$selectedVersion = isset($_GET['version']) ? trim($_GET['version']) : '';

// Fetch available versions from catalog
$availableVersions = $catalogService->getVersionsForSelect();

// Handle form submissions
$selfUrl = '/admin/tutorial.php' . ($selectedVersion !== '' ? '?version=' . urlencode($selectedVersion) : '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminPost($selfUrl);

    if (isset($_POST['toggle_step'])) {
        $stepId = (int)$_POST['step_id'];
        $isActive = (bool)$_POST['is_active'];

        try {
            $stepRepository->setStepActive($stepId, $isActive);
            setFlash('success', 'Step ' . ($isActive ? 'enabled' : 'disabled') . ' successfully');
        } catch (Exception $e) {
            error_log("[TutorialAdmin] Toggle error: " . $e->getMessage());
            setFlash('danger', 'Failed to update step');
        }
    }

    if (isset($_POST['delete_step'])) {
        $stepId = (int)$_POST['step_id'];

        try {
            $stepRepository->deleteStep($stepId);
            setFlash('success', 'Step deleted successfully');
        } catch (Exception $e) {
            error_log("[TutorialAdmin] Delete error: " . $e->getMessage());
            setFlash('danger', 'Failed to delete step');
        }
    }

    redirectTo($selfUrl);
}

// Fetch statistics (filtered by version if selected)
$versionParam = $selectedVersion ?: null;
$stats = $stepRepository->getStepStatistics($versionParam);

// Tutorial sessions stats
$result = $database->exe("
    SELECT
        COUNT(*) as total_sessions,
        SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN completed = 0 THEN 1 ELSE 0 END) as in_progress
    FROM tutorial_progress
");
$stats['sessions'] = $result->fetch_assoc();

// Fetch steps (filtered by version if selected)
$steps = $stepRepository->getStepsForAdmin($versionParam);

ob_start();
?>

<div class="container">

    <!-- Navigation Tabs -->
    <ul class="nav-tabs" id="adminTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="steps-tab" data-toggle="tab" href="#steps-panel" role="tab">
                <i class="fas fa-list-ol"></i> Steps <span class="badge badge-primary ml-1"><?=$stats['total']?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="sessions-tab" data-toggle="tab" href="#sessions-panel" role="tab">
                <i class="fas fa-users"></i> Sessions <span class="badge badge-info ml-1"><?=$stats['sessions']['total_sessions']?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="stats-tab" data-toggle="tab" href="#stats-panel" role="tab">
                <i class="fas fa-chart-bar"></i> Statistics <span class="badge badge-success ml-1"><?=$stats['active']?></span>
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Sessions Panel -->
        <div class="tab-pane fade" id="sessions-panel" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3><i class="fas fa-users"></i> Tutorial Sessions</h3>
                    <div>
                        <button class="btn btn-outline-secondary btn-sm" id="refreshSessions">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Quick Stats Row -->
                    <?php
                    $rate = $stats['sessions']['total_sessions'] > 0
                        ? round(($stats['sessions']['completed'] / $stats['sessions']['total_sessions']) * 100)
                        : 0;
                    ?>
                    <div class="session-stats-inline mb-3">
                        <span class="stat-badge bg-success"><i class="fas fa-check"></i> <?=$stats['sessions']['completed']?> Completed</span>
                        <span class="stat-badge bg-primary"><i class="fas fa-spinner"></i> <?=$stats['sessions']['in_progress']?> In Progress</span>
                        <span class="stat-badge bg-info"><i class="fas fa-percentage"></i> <?=$rate?>% Completion</span>
                    </div>

                    <!-- Sessions Table -->
                    <div class="table-responsive">
                        <table class="table table-hover" id="sessionsTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>Player</th>
                                    <th>Progress</th>
                                    <th>Current Step</th>
                                    <th>XP Earned</th>
                                    <th>Status</th>
                                    <th>Last Update</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="sessionsTableBody">
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-spinner fa-spin"></i> Loading sessions...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted" id="sessionsPaginationInfo">
                            Showing 0 sessions
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0" id="sessionsPagination">
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Panel -->
        <div class="tab-pane fade" id="stats-panel" role="tabpanel">
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title text-muted">Total Steps</h5>
                            <p class="card-text display-4"><?=$stats['total']?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title text-muted">Active Steps</h5>
                            <p class="card-text display-4 text-success"><?=$stats['active']?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title text-muted">Inactive Steps</h5>
                            <p class="card-text display-4 text-secondary"><?=$stats['total'] - $stats['active']?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title text-muted">Tutorials</h5>
                            <p class="card-text display-4"><?= count($availableVersions) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Steps by Type -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-tags"></i> Steps by Type</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($stats['by_type'] as $type => $count): ?>
                            <div class="col-md-3 mb-2">
                                <div class="d-flex align-items-center">
                                    <span class="badge badge-primary mr-2"><?=$count?></span>
                                    <span><?=$type?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Steps Panel (Main Content) -->
        <div class="tab-pane fade show active" id="steps-panel" role="tabpanel">

    <!-- Version Filter -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="form-inline">
                <label for="version">Filtrer par tutoriel :</label>
                <?php
                $versionChoices = [];
                foreach ($availableVersions as $v) {
                    $versionChoices[$v['version']] = $v['name'] . ' (' . $v['version'] . ')';
                }
                echo formSelect('version', $versionChoices, $selectedVersion !== '' ? $selectedVersion : null,
                    '-- Tous les tutoriels --',
                    'id="version" class="form-control" data-autosubmit');
                ?>
                <?php if ($selectedVersion): ?>
                <a href="?" class="btn btn-outline-secondary btn-sm">Effacer filtre</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Steps Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Tutorial Steps <?= $selectedVersion ? '(' . htmlspecialchars($selectedVersion) . ')' : '' ?></span>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary" id="btn-export-all" title="Export all steps as JSON">
                    <i class="fas fa-upload"></i> Export All
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btn-import-all" title="Import steps from JSON">
                    <i class="fas fa-download"></i> Import All
                </button>
                <input type="file" id="import-all-file-input" accept=".json" hidden>
                <a href="tutorial-step-editor.php<?= $selectedVersion ? '?version=' . urlencode($selectedVersion) : '' ?>" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add New Step
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" data-admin-list data-page-size="50">
                    <thead>
                        <tr>
                            <th>#</th>
                            <?php if (!$selectedVersion): ?><th>Version</th><?php endif; ?>
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
                                <?php if (!$selectedVersion): ?>
                                <td><code><?= htmlspecialchars($step['version']) ?></code></td>
                                <?php endif; ?>
                                <td>
                                    <code><?= e($step['step_id'] ?? '-') ?></code>
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
                                    <span class="badge badge-info"><?= e($step['step_type']) ?></span>
                                </td>
                                <td><?= e($step['interaction_mode'] ?? '-') ?></td>
                                <td>
                                    <?php if ($step['requires_validation']): ?>
                                        <span class="badge badge-warning"><?= e($step['validation_type']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?=$step['interactions_count']?></td>
                                <td class="text-center"><?=$step['xp_reward']?></td>
                                <td>
                                    <form method="post" class="d-inline">
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
                                        <form method="post" class="d-inline"
                                              data-confirm="<?= e('Supprimer l\'étape « ' . $step['title'] . ' » ? Sa configuration, ses validations, prérequis et interactions partent avec, sans retour possible.') ?>">
                                            <?= $csrf->renderTokenField() ?>
                                            <input type="hidden" name="step_id" value="<?= (int) $step['id'] ?>">
                                            <button type="submit" name="delete_step" value="1" class="btn btn-sm btn-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

        </div><!-- End Steps Panel -->
    </div><!-- End Tab Content -->

</div>

<!-- Session Detail Modal (hidden by default, shown via JS) -->
<div class="admin-modal-overlay" id="sessionDetailModal" hidden>
    <div class="admin-modal">
        <div class="admin-modal-header">
            <h5><i class="fas fa-user-graduate"></i> Session Details</h5>
            <button type="button" class="admin-modal-close" onclick="closeSessionModal()">&times;</button>
        </div>
        <div class="admin-modal-body" id="sessionDetailContent">
            <div class="text-center py-4">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeSessionModal()">Close</button>
        </div>
    </div>
</div>


<script>
// CSRF Token for AJAX requests
const csrfToken = '<?= $csrf->getToken() ?>';


// =========================================
// Tab Switching with URL Hash Persistence
// =========================================
function switchToTab(targetId) {
    // Hide all tab panes
    document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('show', 'active');
    });

    // Remove active from all tabs
    document.querySelectorAll('#adminTabs .nav-link').forEach(link => {
        link.classList.remove('active');
    });

    // Show target pane
    const targetPane = document.querySelector(targetId);
    if (targetPane) {
        targetPane.classList.add('show', 'active');
    }

    // Set active tab
    const activeTab = document.querySelector(`[href="${targetId}"]`);
    if (activeTab) {
        activeTab.classList.add('active');
    }

    // Update URL hash
    history.replaceState(null, null, targetId);

    // Load sessions when tab is opened
    if (targetId === '#sessions-panel') {
        loadSessions();
    }
}

document.querySelectorAll('[data-toggle="tab"]').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        const targetId = this.getAttribute('href');
        switchToTab(targetId);
    });
});

// Restore tab from URL hash on page load
document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash;
    if (hash && document.querySelector(hash)) {
        switchToTab(hash);
    }
});

// =========================================
// Sessions Management
// =========================================
let sessionsCurrentPage = 0;
const sessionsPerPage = 10;

async function loadSessions(page = 0) {
    sessionsCurrentPage = page;
    const offset = page * sessionsPerPage;

    const tbody = document.getElementById('sessionsTableBody');
    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4">
        <i class="fas fa-spinner fa-spin"></i> Loading sessions...
    </td></tr>`;

    try {
        const response = await fetch(`tutorial-sessions-api.php?action=list&limit=${sessionsPerPage}&offset=${offset}`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to load sessions');
        }

        renderSessions(data.sessions, data.total);
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">
            <i class="fas fa-exclamation-triangle"></i> ${error.message}
        </td></tr>`;
    }
}

function renderSessions(sessions, total) {
    const tbody = document.getElementById('sessionsTableBody');

    if (sessions.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">
            <i class="fas fa-inbox"></i> No tutorial sessions yet
        </td></tr>`;
        document.getElementById('sessionsPaginationInfo').textContent = 'No sessions';
        return;
    }

    tbody.innerHTML = sessions.map(session => {
        const progress = session.step_number && session.total_steps
            ? Math.round((session.step_number / session.total_steps) * 100)
            : 0;

        const statusBadge = session.completed
            ? '<span class="badge badge-success"><i class="fas fa-check"></i> Completed</span>'
            : '<span class="badge badge-primary"><i class="fas fa-spinner"></i> In Progress</span>';

        const lastUpdate = new Date(session.updated_at).toLocaleString('fr-FR');

        return `
            <tr>
                <td>
                    <strong>${escapeHtml(session.player_name || 'Unknown')}</strong>
                    <br><small class="text-muted">${escapeHtml(session.player_race || '')}</small>
                </td>
                <td>
                    <div class="progress" style="height: 20px; min-width: 100px;">
                        <div class="progress-bar ${session.completed ? 'bg-success' : ''}"
                             role="progressbar"
                             style="width: ${progress}%"
                             aria-valuenow="${progress}"
                             aria-valuemin="0"
                             aria-valuemax="100">
                            ${progress}%
                        </div>
                    </div>
                    <small class="text-muted">Step ${session.step_number || '?'} / ${session.total_steps || '?'}</small>
                </td>
                <td>
                    <code>${escapeHtml(session.current_step || 'N/A')}</code>
                    <br><small class="text-muted">${escapeHtml(session.current_step_title || '')}</small>
                </td>
                <td class="text-center">
                    <span class="badge badge-warning">${session.xp_earned || 0} XP</span>
                </td>
                <td>${statusBadge}</td>
                <td><small>${lastUpdate}</small></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="viewSessionDetail('${session.tutorial_session_id}')" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-warning" onclick="resetSession('${session.tutorial_session_id}')" title="Reset Session">
                            <i class="fas fa-undo"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteSession('${session.tutorial_session_id}')" title="Delete Session">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    // Update pagination info
    const start = sessionsCurrentPage * sessionsPerPage + 1;
    const end = Math.min(start + sessions.length - 1, total);
    document.getElementById('sessionsPaginationInfo').textContent =
        `Showing ${start}-${end} of ${total} sessions`;

    // Render pagination
    renderSessionsPagination(total);
}

function renderSessionsPagination(total) {
    const totalPages = Math.ceil(total / sessionsPerPage);
    const pagination = document.getElementById('sessionsPagination');

    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }

    let html = '';

    // Previous button
    html += `<li class="page-item ${sessionsCurrentPage === 0 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadSessions(${sessionsCurrentPage - 1}); return false;">
            <i class="fas fa-chevron-left"></i>
        </a>
    </li>`;

    // Page numbers
    for (let i = 0; i < totalPages; i++) {
        if (i === 0 || i === totalPages - 1 || Math.abs(i - sessionsCurrentPage) <= 2) {
            html += `<li class="page-item ${i === sessionsCurrentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadSessions(${i}); return false;">${i + 1}</a>
            </li>`;
        } else if (Math.abs(i - sessionsCurrentPage) === 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    // Next button
    html += `<li class="page-item ${sessionsCurrentPage >= totalPages - 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadSessions(${sessionsCurrentPage + 1}); return false;">
            <i class="fas fa-chevron-right"></i>
        </a>
    </li>`;

    pagination.innerHTML = html;
}

function closeSessionModal() {
    document.getElementById('sessionDetailModal').hidden = true;
}

async function viewSessionDetail(sessionId) {
    const modal = document.getElementById('sessionDetailModal');
    const content = document.getElementById('sessionDetailContent');

    content.innerHTML = `<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>`;
    modal.hidden = false;

    try {
        const response = await fetch(`tutorial-sessions-api.php?action=detail&session_id=${sessionId}`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to load session details');
        }

        content.innerHTML = renderSessionDetail(data);
    } catch (error) {
        content.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
    }
}

function renderSessionDetail(data) {
    const session = data.session;
    const steps = data.steps;
    const tutorialPlayer = data.tutorial_player;

    // Find current step index
    const currentIndex = steps.findIndex(s => s.is_current);

    let html = `
        <div class="row mb-4">
            <div class="col-md-6">
                <h5><i class="fas fa-user"></i> Player Info</h5>
                <table class="table table-sm">
                    <tr><td>Name:</td><td><strong>${escapeHtml(session.player_name)}</strong></td></tr>
                    <tr><td>Race:</td><td>${escapeHtml(session.player_race)}</td></tr>
                    <tr><td>XP Earned:</td><td><span class="badge badge-warning">${session.xp_earned} XP</span></td></tr>
                    <tr><td>Status:</td><td>${session.completed
                        ? '<span class="badge badge-success">Completed</span>'
                        : '<span class="badge badge-primary">In Progress</span>'}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h5><i class="fas fa-info-circle"></i> Session Info</h5>
                <table class="table table-sm">
                    <tr><td>Session ID:</td><td><code>${session.tutorial_session_id}</code></td></tr>
                    <tr><td>Version:</td><td>${session.tutorial_version}</td></tr>
                    <tr><td>Started:</td><td>${new Date(session.created_at).toLocaleString('fr-FR')}</td></tr>
                    <tr><td>Last Update:</td><td>${new Date(session.updated_at).toLocaleString('fr-FR')}</td></tr>
                </table>
            </div>
        </div>
    `;

    // Tutorial player info if exists
    if (tutorialPlayer) {
        html += `
            <div class="alert alert-info mb-4">
                <h6><i class="fas fa-user-graduate"></i> Tutorial Player</h6>
                <small>
                    Name: <strong>${escapeHtml(tutorialPlayer.tutorial_player_name)}</strong> |
                    Position: (${tutorialPlayer.x}, ${tutorialPlayer.y}) on ${tutorialPlayer.plan} |
                    Active: ${tutorialPlayer.is_active ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>'}
                </small>
            </div>
        `;
    }

    // Step progress timeline
    html += `<h5><i class="fas fa-tasks"></i> Progress Timeline</h5>`;
    html += `<div class="timeline">`;

    steps.forEach((step, index) => {
        const isCompleted = index < currentIndex;
        const isCurrent = step.is_current;
        const isPending = index > currentIndex;

        let markerClass = 'bg-secondary';
        if (isCompleted) markerClass = 'bg-success';
        if (isCurrent) markerClass = 'bg-primary';

        html += `
            <div class="timeline-item ${isCurrent ? 'in-progress' : ''}">
                <div class="timeline-marker ${markerClass}"></div>
                <div class="timeline-content ${isPending ? 'text-muted' : ''}">
                    <div class="d-flex justify-content-between">
                        <strong>${step.step_number}. ${escapeHtml(step.title)}</strong>
                        <span class="badge badge-light">${step.step_type}</span>
                    </div>
                    <small>
                        <code>${step.step_id}</code>
                        ${step.xp_reward ? `<span class="ml-2 badge badge-warning">${step.xp_reward} XP</span>` : ''}
                    </small>
                </div>
            </div>
        `;
    });

    html += `</div>`;

    return html;
}

async function resetSession(sessionId) {
    if (!confirm('Reset this session to the beginning?\n\nThe player will start from step 1 with 0 XP.')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('session_id', sessionId);
        formData.append('csrf_token', csrfToken);

        const response = await fetch('tutorial-sessions-api.php?action=reset', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Failed to reset session');
        }

        showToast('success', 'Session reset successfully');
        loadSessions(sessionsCurrentPage);
    } catch (error) {
        showToast('danger', error.message);
    }
}

async function deleteSession(sessionId) {
    if (!confirm('Delete this session?\n\nThis will remove all progress data. This action cannot be undone.')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('session_id', sessionId);
        formData.append('csrf_token', csrfToken);

        const response = await fetch('tutorial-sessions-api.php?action=delete', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Failed to delete session');
        }

        showToast('success', 'Session deleted successfully');
        loadSessions(sessionsCurrentPage);
    } catch (error) {
        showToast('danger', error.message);
    }
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function showToast(type, message) {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show toast-notification`;
    toast.innerHTML = `
        ${message}
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

// Refresh button handler
document.getElementById('refreshSessions').addEventListener('click', () => {
    loadSessions(sessionsCurrentPage);
});

// =========================================
// Export All / Import All Steps
// =========================================

// Export All Steps
document.getElementById('btn-export-all').addEventListener('click', async function() {
    try {
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';

        const response = await fetch('tutorial-sessions-api.php?action=export_all_steps');
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to export steps');
        }

        // Download as JSON file
        const blob = new Blob([JSON.stringify(data.steps, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `tutorial-steps-export-${new Date().toISOString().slice(0, 10)}.json`;
        a.click();
        URL.revokeObjectURL(url);

        showToast('success', `Exported ${data.steps.length} steps successfully!`);
    } catch (error) {
        showToast('danger', error.message);
    } finally {
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-upload"></i> Export All';
    }
});

// Import All Steps
document.getElementById('btn-import-all').addEventListener('click', function() {
    document.getElementById('import-all-file-input').click();
});

document.getElementById('import-all-file-input').addEventListener('change', async function(e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async function(event) {
        try {
            const steps = JSON.parse(event.target.result);

            if (!Array.isArray(steps)) {
                throw new Error('Invalid format: expected an array of steps');
            }

            const confirmMsg = `Import ${steps.length} steps?\n\n` +
                `This will add/update steps based on step_id.\n` +
                `Existing steps with matching step_id will be updated.\n` +
                `New step_ids will be created as new steps.`;

            if (!confirm(confirmMsg)) {
                return;
            }

            const formData = new FormData();
            formData.append('steps', JSON.stringify(steps));
            formData.append('csrf_token', csrfToken);

            const response = await fetch('tutorial-sessions-api.php?action=import_all_steps', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to import steps');
            }

            showToast('success', `Import complete! Created: ${data.created}, Updated: ${data.updated}`);

            // Reload page to show new steps
            setTimeout(() => window.location.reload(), 1500);
        } catch (err) {
            showToast('danger', 'Import failed: ' + err.message);
        }
    };
    reader.readAsText(file);

    // Reset input
    this.value = '';
});
</script>


<?php
$content = ob_get_clean();
echo admin_layout('Étapes du tutoriel', $content, [
    'styles' => ['/admin/css/tutorial-admin.css'],
]);
?>
