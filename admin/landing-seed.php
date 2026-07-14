<?php
/**
 * Seed du contenu initial de la page d'accueil (admin dashboard →
 * Page d'accueil → Seed).
 *
 * Le contenu (rédaction validée en juillet 2026) vit dans
 * LandingSeedService ; les images ne sont pas dans git — elles se
 * déposent à la main dans img/ui/landing/ AVANT de lancer le seed
 * (les lignes dont les fichiers manquent sont rapportées et se sèment
 * au prochain lancement). Relançable sans risque : création seulement,
 * l'existant est préservé.
 *
 * Accès : hérite du niveau du menu landing.php (alias AdminMenuAccessService).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\LandingSeedService;

$csrf = new CsrfProtectionService();
$service = new LandingSeedService();
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seed_landing'])) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
        $report = $service->seed();
        $message = sprintf(
            'Seed appliqué : %d élément(s) créé(s), %d conservé(s).',
            count($report['created']),
            count($report['kept'])
        );
        if ($report['missingFiles'] !== []) {
            $message .= ' ⚠ ' . count($report['missingFiles'])
                . ' image(s) non semée(s), fichiers absents : déposez-les dans img/ui/landing/ puis relancez.';
        }
        setFlash($report['missingFiles'] === [] ? 'success' : 'warning', $message);
    } catch (\Throwable $e) {
        setFlash('danger', 'Échec du seed : ' . $e->getMessage());
    }
}

ob_start();
?>

<div class="container">
    <h3>Page d'accueil — seed du contenu initial</h3>

    <?= renderFlashMessage() ?>

    <div class="alert alert-info" style="font-size: 13px; line-height: 1.5;">
        Sème le contenu éditorial initial de l'accueil (présentation, 3 chroniques, 5 aperçus).
        <ul class="mb-0 mt-1">
            <li>Les <strong>images ne sont pas dans git</strong> : déposez d'abord les fichiers dans
                <code style="display:inline">img/ui/landing/</code> (archive fournie par l'équipe) —
                une ligne d'image n'est créée que si son fichier existe.</li>
            <li><strong>Création seulement</strong> : une section, chronique ou image déjà en base est
                préservée telle quelle ; relancer n'écrase jamais une édition admin.</li>
        </ul>
    </div>

    <form method="post">
        <?= $csrf->renderTokenField() ?>
        <button type="submit" name="seed_landing" value="1" class="btn btn-primary">Lancer le seed</button>
        <a href="landing.php" class="btn btn-outline-secondary">← Retour à la gestion du contenu</a>
    </form>

    <?php if ($report !== null): ?>
    <div class="card mt-3">
        <div class="card-header"><strong>Rapport</strong></div>
        <div class="card-body" style="font-size: 13px;">
            <?php foreach ($report['created'] as $item): ?>
                <div><span class="badge badge-success">créé</span> <?= e($item) ?></div>
            <?php endforeach; ?>
            <?php foreach ($report['kept'] as $item): ?>
                <div><span class="badge badge-secondary">conservé</span> <?= e($item) ?></div>
            <?php endforeach; ?>
            <?php foreach ($report['missingFiles'] as $item): ?>
                <div><span class="badge badge-warning">fichier absent</span> <?= e($item) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
echo admin_layout('Page d\'accueil — seed', $content);
