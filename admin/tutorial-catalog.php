<?php
/**
 * Tutorial Catalog Management
 *
 * Admin panel for managing multiple tutorials and launching them for testing.
 */

require_once __DIR__ . '/layout.php';

use Classes\Db;

$db = new Db();

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

// Fetch all tutorials
$result = $db->exe("SELECT * FROM tutorial_catalog ORDER BY display_order, name");
$tutorials = [];
while ($row = $result->fetch_assoc()) {
    // Count steps for this version
    $stepCount = $db->exe("SELECT COUNT(*) as cnt FROM tutorial_steps WHERE version = ? AND is_active = 1", [$row['version']])->fetch_assoc();
    $row['step_count'] = $stepCount['cnt'] ?? 0;
    $tutorials[] = $row;
}

// Fetch for edit if requested
$editTutorial = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editResult = $db->exe("SELECT * FROM tutorial_catalog WHERE id = ?", [$editId]);
    $editTutorial = $editResult->fetch_assoc();
}

ob_start();
?>

<link rel="stylesheet" href="/css/rpg-awesome.min.css">
<style>
    .tutorial-icon { font-size: 1.5rem; vertical-align: middle; }
    .badge-beginner { background: #27ae60; color: white; }
    .badge-intermediate { background: #f39c12; color: white; }
    .badge-advanced { background: #e74c3c; color: white; }
    .btn-launch { background: #27ae60; color: white; border: none; }
    .btn-launch:hover { background: #229954; color: white; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Catalogue des Tutoriels</h1>
    <div>
        <a href="/admin/tutorial.php" class="btn btn-outline-info">
            Voir tous les Steps
        </a>
        <a href="/index.php" class="btn btn-outline-secondary">
            Retour au jeu
        </a>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?>">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="row">
    <!-- Tutorial List -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">Tutoriels disponibles</h5>
            </div>
            <div class="card-body">
                <?php if (empty($tutorials)): ?>
                <p class="text-muted">Aucun tutoriel configuré.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
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
                                    <i class="ra <?= htmlspecialchars($tut['icon']) ?> tutorial-icon"></i>
                                    <strong><?= htmlspecialchars($tut['name']) ?></strong>
                                    <br>
                                    <small class="text-muted"><?= htmlspecialchars(substr($tut['description'] ?? '', 0, 50)) ?>...</small>
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
                                    <span class="badge badge-info"><?= $tut['step_count'] ?> steps</span>
                                </td>
                                <td>
                                    <?php if ($tut['is_active']): ?>
                                    <span class="badge badge-success">Actif</span>
                                    <?php else: ?>
                                    <span class="badge badge-secondary">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-launch btn-sm btn-launch-tutorial"
                                                data-version="<?= htmlspecialchars($tut['version']) ?>"
                                                data-name="<?= htmlspecialchars($tut['name']) ?>"
                                                title="Lancer ce tutoriel">
                                            Lancer
                                        </button>
                                        <a href="?edit=<?= $tut['id'] ?>" class="btn btn-warning btn-sm" title="Modifier">
                                            Edit
                                        </a>
                                        <a href="/admin/tutorial.php?version=<?= urlencode($tut['version']) ?>" class="btn btn-info btn-sm" title="Voir les steps">
                                            Steps
                                        </a>
                                        <?php if ($tut['version'] !== '1.0.0'): ?>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce tutoriel ?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $tut['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Supprimer">
                                                X
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
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <?= $editTutorial ? 'Modifier' : 'Nouveau' ?> Tutoriel
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="<?= $editTutorial ? 'update' : 'create' ?>">
                    <?php if ($editTutorial): ?>
                    <input type="hidden" name="id" value="<?= $editTutorial['id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Version *</label>
                        <input type="text" name="version" class="form-control" required
                               placeholder="ex: 2.0.0-craft"
                               value="<?= htmlspecialchars($editTutorial['version'] ?? '') ?>">
                        <small class="form-text">Identifiant unique (sera utilisé dans tutorial_steps)</small>
                    </div>

                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="name" class="form-control" required
                               placeholder="ex: Tutoriel Artisanat"
                               value="<?= htmlspecialchars($editTutorial['name'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="2"
                                  placeholder="Description courte du tutoriel"><?= htmlspecialchars($editTutorial['description'] ?? '') ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Icône</label>
                                <input type="text" name="icon" class="form-control"
                                       placeholder="ra-anvil"
                                       value="<?= htmlspecialchars($editTutorial['icon'] ?? 'ra-book') ?>">
                                <small class="form-text"><a href="https://nagoshiashumern.github.io/Rpg-Awesome/" target="_blank">RPG Awesome</a></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Difficulté</label>
                                <select name="difficulty" class="form-control">
                                    <option value="beginner" <?= ($editTutorial['difficulty'] ?? '') === 'beginner' ? 'selected' : '' ?>>Débutant</option>
                                    <option value="intermediate" <?= ($editTutorial['difficulty'] ?? '') === 'intermediate' ? 'selected' : '' ?>>Intermédiaire</option>
                                    <option value="advanced" <?= ($editTutorial['difficulty'] ?? '') === 'advanced' ? 'selected' : '' ?>>Avancé</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Durée (min)</label>
                                <input type="number" name="estimated_minutes" class="form-control"
                                       value="<?= $editTutorial['estimated_minutes'] ?? 10 ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Ordre d'affichage</label>
                                <input type="number" name="display_order" class="form-control"
                                       value="<?= $editTutorial['display_order'] ?? 0 ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Plan (carte)</label>
                        <input type="text" name="plan" class="form-control"
                               placeholder="tutorial"
                               value="<?= htmlspecialchars($editTutorial['plan'] ?? 'tutorial') ?>">
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Spawn X</label>
                                <input type="number" name="spawn_x" class="form-control"
                                       value="<?= $editTutorial['spawn_x'] ?? 0 ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Spawn Y</label>
                                <input type="number" name="spawn_y" class="form-control"
                                       value="<?= $editTutorial['spawn_y'] ?? 0 ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Prérequis (versions JSON)</label>
                        <input type="text" name="prerequisites" class="form-control"
                               placeholder='["1.0.0"]'
                               value="<?= htmlspecialchars($editTutorial['prerequisites'] ?? '') ?>">
                        <small class="form-text">Tutoriels à compléter avant (format JSON)</small>
                    </div>

                    <div class="form-group form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="is_active"
                               <?= ($editTutorial['is_active'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Tutoriel actif</label>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <?= $editTutorial ? 'Mettre à jour' : 'Créer' ?>
                        </button>
                        <?php if ($editTutorial): ?>
                        <a href="?" class="btn btn-secondary">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-launch-tutorial').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var version = this.getAttribute('data-version');
        var name = this.getAttribute('data-name');

        if (!confirm('Lancer le tutoriel "' + name + '" (v' + version + ') ?\n\nVous serez redirigé vers le jeu.')) {
            return;
        }

        this.disabled = true;
        this.textContent = '...';

        fetch('/admin/tutorial-launcher.php?version=' + encodeURIComponent(version))
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    alert('Tutoriel lancé: ' + data.tutorial_name + '\n' + data.step_count + ' étapes');
                    window.location.href = data.redirect || '/index.php';
                } else {
                    alert('Erreur: ' + (data.error || 'Erreur inconnue') + (data.hint ? '\n\n' + data.hint : ''));
                    btn.disabled = false;
                    btn.textContent = 'Lancer';
                }
            })
            .catch(function(err) {
                alert('Erreur réseau: ' + err.message);
                btn.disabled = false;
                btn.textContent = 'Lancer';
            });
    });
});
</script>

<?php
$content = ob_get_clean();
echo admin_layout('Catalogue des Tutoriels', $content);
