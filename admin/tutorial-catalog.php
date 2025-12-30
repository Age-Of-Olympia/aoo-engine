<?php
/**
 * Tutorial Catalog Management
 *
 * Admin panel for managing multiple tutorials and launching them for testing.
 */

require_once __DIR__ . '/../config.php';

// Check admin access
if (empty($_SESSION['playerId'])) {
    header('Location: /index.php');
    exit;
}

$player = new \Classes\Player($_SESSION['playerId']);
$player->get_options();
if (empty($player->options->isAdmin)) {
    header('Location: /index.php');
    exit;
}

$db = new \Classes\Db();

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $version = trim($_POST['version'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'ra-book');
        $difficulty = $_POST['difficulty'] ?? 'beginner';
        $estimatedMinutes = (int)($_POST['estimated_minutes'] ?? 10);
        $prerequisites = !empty($_POST['prerequisites']) ? $_POST['prerequisites'] : null;
        $plan = trim($_POST['plan'] ?? 'tutorial');
        $spawnX = (int)($_POST['spawn_x'] ?? 0);
        $spawnY = (int)($_POST['spawn_y'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $displayOrder = (int)($_POST['display_order'] ?? 0);

        if (empty($version) || empty($name)) {
            $message = 'Version et nom sont requis.';
            $messageType = 'danger';
        } else {
            try {
                if ($action === 'create') {
                    $db->exe("INSERT INTO tutorial_catalog
                        (version, name, description, icon, difficulty, estimated_minutes, prerequisites, plan, spawn_x, spawn_y, is_active, display_order)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$version, $name, $description, $icon, $difficulty, $estimatedMinutes, $prerequisites, $plan, $spawnX, $spawnY, $isActive, $displayOrder]
                    );
                    $message = "Tutoriel '$name' créé avec succès.";
                    $messageType = 'success';
                } else {
                    $db->exe("UPDATE tutorial_catalog SET
                        version = ?, name = ?, description = ?, icon = ?, difficulty = ?,
                        estimated_minutes = ?, prerequisites = ?, plan = ?, spawn_x = ?, spawn_y = ?,
                        is_active = ?, display_order = ?
                        WHERE id = ?",
                        [$version, $name, $description, $icon, $difficulty, $estimatedMinutes, $prerequisites, $plan, $spawnX, $spawnY, $isActive, $displayOrder, $id]
                    );
                    $message = "Tutoriel '$name' mis à jour.";
                    $messageType = 'success';
                }
            } catch (Exception $e) {
                $message = 'Erreur: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $db->exe("DELETE FROM tutorial_catalog WHERE id = ?", [$id]);
            $message = 'Tutoriel supprimé.';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Erreur: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

/* Fetch all tutorials */
$result = $db->exe("SELECT * FROM tutorial_catalog ORDER BY display_order, name");
$tutorials = [];
while ($row = $result->fetch_assoc()) {
    /* Count steps for this version */
    $stepCount = $db->exe("SELECT COUNT(*) as cnt FROM tutorial_steps WHERE version = ? AND is_active = 1", [$row['version']])->fetch_assoc();
    $row['step_count'] = $stepCount['cnt'] ?? 0;
    $tutorials[] = $row;
}

/* Fetch for edit if requested */
$editTutorial = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editResult = $db->exe("SELECT * FROM tutorial_catalog WHERE id = ?", [$editId]);
    $editTutorial = $editResult->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue des Tutoriels - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/rpg-awesome.min.css">
    <style>
        body { background: #1a1a2e; color: #eee; }
        .card { background: #16213e; border: 1px solid #0f3460; }
        .card-header { background: #0f3460; }
        .table { color: #eee; }
        .table-dark { background: #16213e; }
        .btn-launch { background: #28a745; border-color: #28a745; }
        .btn-launch:hover { background: #218838; }
        .tutorial-icon { font-size: 2rem; }
        .badge-beginner { background: #28a745; }
        .badge-intermediate { background: #ffc107; color: #000; }
        .badge-advanced { background: #dc3545; }
        .step-count { font-size: 0.8rem; color: #aaa; }
        .form-control, .form-select { background: #1a1a2e; color: #eee; border-color: #0f3460; }
        .form-control:focus, .form-select:focus { background: #1a1a2e; color: #eee; border-color: #e94560; }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="ra ra-book"></i> Catalogue des Tutoriels</h1>
            <div>
                <a href="/admin/tutorial-step-editor.php" class="btn btn-outline-info me-2">
                    <i class="ra ra-scroll-unfurled"></i> Éditeur de Steps
                </a>
                <a href="/index.php" class="btn btn-outline-secondary">
                    <i class="ra ra-player"></i> Retour au jeu
                </a>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Tutorial List -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="ra ra-scroll-unfurled"></i> Tutoriels disponibles</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tutorials)): ?>
                        <p class="text-muted">Aucun tutoriel configuré.</p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover">
                                <thead>
                                    <tr>
                                        <th>Tutoriel</th>
                                        <th>Version</th>
                                        <th>Difficulté</th>
                                        <th>Steps</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tutorials as $tut): ?>
                                    <tr>
                                        <td>
                                            <i class="ra <?= htmlspecialchars($tut['icon']) ?> tutorial-icon me-2"></i>
                                            <strong><?= htmlspecialchars($tut['name']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars(substr($tut['description'] ?? '', 0, 60)) ?>...</small>
                                        </td>
                                        <td><code><?= htmlspecialchars($tut['version']) ?></code></td>
                                        <td>
                                            <span class="badge badge-<?= $tut['difficulty'] ?>">
                                                <?= ucfirst($tut['difficulty']) ?>
                                            </span>
                                            <br>
                                            <small>~<?= $tut['estimated_minutes'] ?> min</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= $tut['step_count'] ?> steps</span>
                                        </td>
                                        <td>
                                            <?php if ($tut['is_active']): ?>
                                            <span class="badge bg-success">Actif</span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">Inactif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-launch btn-sm btn-launch-tutorial"
                                                        data-version="<?= htmlspecialchars($tut['version']) ?>"
                                                        data-name="<?= htmlspecialchars($tut['name']) ?>"
                                                        title="Lancer ce tutoriel">
                                                    <i class="ra ra-player-teleport"></i>
                                                </button>
                                                <a href="?edit=<?= $tut['id'] ?>" class="btn btn-warning btn-sm" title="Modifier">
                                                    <i class="ra ra-quill-ink"></i>
                                                </a>
                                                <a href="/admin/tutorial-step-editor.php?version=<?= urlencode($tut['version']) ?>" class="btn btn-info btn-sm" title="Éditer les steps">
                                                    <i class="ra ra-scroll-unfurled"></i>
                                                </a>
                                                <?php if ($tut['version'] !== '1.0.0'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce tutoriel ?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $tut['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" title="Supprimer">
                                                        <i class="ra ra-cancel"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Create/Edit Form -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="ra ra-quill-ink"></i>
                            <?= $editTutorial ? 'Modifier' : 'Nouveau' ?> Tutoriel
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?= $editTutorial ? 'update' : 'create' ?>">
                            <?php if ($editTutorial): ?>
                            <input type="hidden" name="id" value="<?= $editTutorial['id'] ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Version *</label>
                                <input type="text" name="version" class="form-control" required
                                       placeholder="ex: 2.0.0-craft"
                                       value="<?= htmlspecialchars($editTutorial['version'] ?? '') ?>">
                                <small class="text-muted">Identifiant unique (sera utilisé dans tutorial_steps)</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Nom *</label>
                                <input type="text" name="name" class="form-control" required
                                       placeholder="ex: Tutoriel Artisanat"
                                       value="<?= htmlspecialchars($editTutorial['name'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2"
                                          placeholder="Description courte du tutoriel"><?= htmlspecialchars($editTutorial['description'] ?? '') ?></textarea>
                            </div>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label">Icône</label>
                                    <input type="text" name="icon" class="form-control"
                                           placeholder="ra-anvil"
                                           value="<?= htmlspecialchars($editTutorial['icon'] ?? 'ra-book') ?>">
                                    <small class="text-muted"><a href="https://nagoshiashumern.github.io/Rpg-Awesome/" target="_blank">RPG Awesome</a></small>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Difficulté</label>
                                    <select name="difficulty" class="form-select">
                                        <option value="beginner" <?= ($editTutorial['difficulty'] ?? '') === 'beginner' ? 'selected' : '' ?>>Débutant</option>
                                        <option value="intermediate" <?= ($editTutorial['difficulty'] ?? '') === 'intermediate' ? 'selected' : '' ?>>Intermédiaire</option>
                                        <option value="advanced" <?= ($editTutorial['difficulty'] ?? '') === 'advanced' ? 'selected' : '' ?>>Avancé</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label">Durée (min)</label>
                                    <input type="number" name="estimated_minutes" class="form-control"
                                           value="<?= $editTutorial['estimated_minutes'] ?? 10 ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Ordre d'affichage</label>
                                    <input type="number" name="display_order" class="form-control"
                                           value="<?= $editTutorial['display_order'] ?? 0 ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Plan (carte)</label>
                                <input type="text" name="plan" class="form-control"
                                       placeholder="tutorial"
                                       value="<?= htmlspecialchars($editTutorial['plan'] ?? 'tutorial') ?>">
                            </div>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label">Spawn X</label>
                                    <input type="number" name="spawn_x" class="form-control"
                                           value="<?= $editTutorial['spawn_x'] ?? 0 ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Spawn Y</label>
                                    <input type="number" name="spawn_y" class="form-control"
                                           value="<?= $editTutorial['spawn_y'] ?? 0 ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Prérequis (versions JSON)</label>
                                <input type="text" name="prerequisites" class="form-control"
                                       placeholder='["1.0.0"]'
                                       value="<?= htmlspecialchars($editTutorial['prerequisites'] ?? '') ?>">
                                <small class="text-muted">Tutoriels à compléter avant (format JSON)</small>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" id="is_active"
                                       <?= ($editTutorial['is_active'] ?? true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">Tutoriel actif</label>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ra ra-save"></i> <?= $editTutorial ? 'Mettre à jour' : 'Créer' ?>
                                </button>
                                <?php if ($editTutorial): ?>
                                <a href="?" class="btn btn-outline-secondary">Annuler</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.querySelectorAll('.btn-launch-tutorial').forEach(btn => {
        btn.addEventListener('click', function() {
            const version = this.dataset.version;
            const name = this.dataset.name;

            if (!confirm(`Lancer le tutoriel "${name}" (v${version}) ?\n\nVous serez redirigé vers le jeu.`)) {
                return;
            }

            this.disabled = true;
            this.innerHTML = '<i class="ra ra-cycle"></i>';

            fetch('/admin/tutorial-launcher.php?version=' + encodeURIComponent(version))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Tutoriel lancé: ${data.tutorial_name}\n${data.step_count} étapes`);
                        window.location.href = data.redirect || '/index.php';
                    } else {
                        alert('Erreur: ' + (data.error || 'Erreur inconnue') + (data.hint ? '\n\n' + data.hint : ''));
                        this.disabled = false;
                        this.innerHTML = '<i class="ra ra-player-teleport"></i>';
                    }
                })
                .catch(err => {
                    alert('Erreur réseau: ' + err.message);
                    this.disabled = false;
                    this.innerHTML = '<i class="ra ra-player-teleport"></i>';
                });
        });
    });
    </script>
</body>
</html>
