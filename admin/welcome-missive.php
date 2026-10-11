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
use Classes\Player;

$csrf = new CsrfProtectionService();
$service = new WelcomeMissiveService();

$authorId = $service->authorId();
$author = new Player($authorId);
$author->get_data();
$authorName = $author->data->name ?? '(introuvable)';
$missives = $service->authorMissives();
$current = $service->topicName();
$content = $service->content();

ob_start();
?>

<div class="container">
    <h3>Missive d'accueil</h3>

    <?= renderFlashMessage() ?>

    <div class="alert alert-info" style="font-size: 13px; line-height: 1.5;">
        À l'inscription, chaque nouveau joueur devient destinataire de cette missive : tous les joueurs
        partagent donc le même fil. Sans missive choisie, aucun message d'accueil n'est envoyé.
    </div>

    <div class="card mt-3">
        <div class="card-header"><strong>Expéditeur</strong></div>
        <div class="card-body">
            <form method="post" action="welcome-missive-save.php?action=author" class="d-flex gap-2 align-items-end">
                <?= $csrf->renderTokenField() ?>
                <div>
                    <label class="form-label mb-0">Matricule</label>
                    <input type="number" name="author" class="form-control" style="width: 130px;" value="<?= $authorId ?>" required />
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
                <select name="missive" class="form-select" style="max-width: 480px;" required>
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
                <textarea name="text" class="form-control" rows="14" style="font-family: monospace; font-size: 12px;" required><?= e($content['text']) ?></textarea>
                <button type="submit" class="btn btn-sm btn-primary mt-2">Enregistrer</button>
            </form>
            <?php endif; ?>

            <details class="mt-3"<?= $content === null ? ' open' : '' ?>>
                <summary class="mb-2" style="cursor: pointer;">Écrire une nouvelle missive d'accueil</summary>
                <p class="text-muted" style="font-size: 13px;">Les joueurs déjà inscrits gardent l'ancienne missive ;
                    seuls les nouveaux reçoivent celle-ci. Le formulaire part du texte actuel.</p>
                <form method="post" action="welcome-missive-save.php?action=create">
                    <?= $csrf->renderTokenField() ?>
                    <label class="form-label mb-0">Titre</label>
                    <input type="text" name="title" class="form-control mb-2" value="<?= e($content['title'] ?? '') ?>" required />
                    <label class="form-label mb-0">Texte (BBCode)</label>
                    <textarea name="text" class="form-control" rows="14" style="font-family: monospace; font-size: 12px;" required><?= e($content['text'] ?? '') ?></textarea>
                    <button type="submit" class="btn btn-sm btn-primary mt-2">Créer et choisir</button>
                </form>
            </details>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
echo admin_layout('Missive d\'accueil', $content);
