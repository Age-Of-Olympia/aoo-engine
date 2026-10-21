<?php
/**
 * Missive d'accueil : la missive où chaque nouveau joueur est ajouté comme
 * destinataire à l'inscription (register.php). Expéditeur, choix de la
 * missive parmi les siennes, édition du titre et du texte (BBCode).
 * Les mutations POSTent vers welcome-missive-save.php (CSRF, PRG).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\WelcomeMissiveService;

$csrf = new CsrfProtectionService();
$service = new WelcomeMissiveService();

$authorId = $service->authorId();
// A deleted author must not lock the page that picks another one (get_data() exits).
$authorRow = (new \Classes\Db())->exe('SELECT name FROM players WHERE id = ?', $authorId)->fetch_object();
$authorName = $authorRow->name ?? '(introuvable)';
$missives = $service->authorMissives();
$current = $service->topicName();
$content = $service->content();

ob_start();
?>

<div class="container">
    <div class="card">
        <div class="card-header"><strong>Expéditeur</strong></div>
        <div class="card-body">
            <form method="post" action="welcome-missive-save.php?action=author" class="d-flex gap-2 align-items-end">
                <?= $csrf->renderTokenField() ?>
                <div>
                    <label class="form-label mb-0">Matricule</label>
                    <input type="number" name="author" class="form-control w-auto" value="<?= $authorId ?>" required />
                </div>
                <span class="mb-2"><?= e($authorName) ?></span>
                <button type="submit" class="btn btn-sm btn-primary mb-1">Enregistrer</button>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header"><strong>Missive</strong> — parmi celles de <?= e($authorName) ?></div>
        <div class="card-body">
            <form method="post" action="welcome-missive-save.php?action=choose" class="d-flex gap-2 align-items-end mb-3">
                <?= $csrf->renderTokenField() ?>
                <select name="missive" class="form-select w-auto" required>
                    <?php if ($current === ''): ?><option value="">— aucune —</option><?php endif; ?>
                    <?php foreach ($missives as $name => $title): ?>
                    <option value="<?= e($name) ?>" <?= selected($name === $current) ?>><?= e($title) ?> (<?= e($name) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-primary mb-1">Choisir</button>
            </form>

            <?php if ($content !== null): ?>
            <form method="post" action="welcome-missive-save.php?action=save">
                <?= $csrf->renderTokenField() ?>
                <label class="form-label mb-0">Titre</label>
                <input type="text" name="title" class="form-control mb-2" value="<?= e($content['title']) ?>" required />
                <label class="form-label mb-0">Texte (BBCode : [b], [i]…)</label>
                <textarea name="text" class="form-control font-monospace" rows="14" required><?= e($content['text']) ?></textarea>
                <button type="submit" class="btn btn-sm btn-primary mt-2">Enregistrer</button>
            </form>
            <?php endif; ?>

            <details class="mt-3"<?= $content === null ? ' open' : '' ?>>
                <summary class="mb-2">Écrire une nouvelle missive d'accueil</summary>
                <p class="text-muted small">Les joueurs déjà inscrits gardent l'ancienne missive ;
                    seuls les nouveaux reçoivent celle-ci. Le formulaire part du texte actuel.</p>
                <form method="post" action="welcome-missive-save.php?action=create">
                    <?= $csrf->renderTokenField() ?>
                    <label class="form-label mb-0">Titre</label>
                    <input type="text" name="title" class="form-control mb-2" value="<?= e($content['title'] ?? '') ?>" required />
                    <label class="form-label mb-0">Texte (BBCode)</label>
                    <textarea name="text" class="form-control font-monospace" rows="14" required><?= e($content['text'] ?? '') ?></textarea>
                    <button type="submit" class="btn btn-sm btn-primary mt-2">Créer et choisir</button>
                </form>
            </details>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
echo admin_layout('Missive d\'accueil', $content, [
    'intro' => 'À l\'inscription, chaque nouveau joueur devient destinataire de cette missive : tous les joueurs'
        . ' partagent donc le même fil. Sans missive choisie, aucun message d\'accueil n\'est envoyé.',
]);
