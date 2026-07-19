<?php
// admin/avatars-portraits.php
/**
 * Avatars & portraits (admin dashboard → Joueurs) : inventaire des images de
 * personnage par type et par race (img/avatars/<race>, img/portraits/<race>)
 * avec diagnostics — dimensions hors canon, portrait sans miniature, image
 * choisie par des joueurs mais absente du disque — le nombre de joueurs qui
 * utilisent chaque image, l'ajout (redimensionné au canon, numéroté par le
 * compteur de la race) et la suppression gardée. Remplace l'ancien
 * « Importer images » ; même socle d'affichage que Tuiles & images
 * (admin-list.js : recherche + pagination).
 */
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php';

use App\Enum\ImageType;
use App\Service\CsrfProtectionService;
use App\Service\RaceImageService;

$csrf = new CsrfProtectionService();
$service = new RaceImageService();

// Deux sections pour un même stock (même séparation que Races ×
// Types de bâtiments) : ici les personnages, admin/structure-images.php
// (wrapper qui pose ?kind=structure) pour les types.
$structureMode = (($_GET['kind'] ?? '') === 'structure');
$selfPage = $structureMode ? '/admin/structure-images.php' : '/admin/avatars-portraits.php';
$pageTitle = $structureMode ? 'Images des bâtiments' : 'Avatars & portraits';

$type = ImageType::tryFrom(stringWithDefault('type', (string) ($_GET['type'] ?? 'avatar'))) ?? ImageType::AVATAR;
$races = $service->raceNames($type, $structureMode ? 'structure' : 'character');
$race = stringWithDefault('race', (string) ($_GET['race'] ?? ''));
if (!in_array($race, $races, true)) {
    $race = $races[0] ?? '';
}
$backTo = $selfPage . '?type=' . urlencode($type->value) . '&race=' . urlencode($race);

$isStateChangingPost = $_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['image_upload']) || isset($_POST['image_delete']));
if ($isStateChangingPost) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
    } catch (RuntimeException $e) {
        setFlash('danger', $e->getMessage());
        redirectTo($backTo);
    }

    try {
        if (isset($_POST['image_upload'])) {
            $file = $_FILES['image_file'] ?? null;
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                || !is_uploaded_file((string) $file['tmp_name'])) {
                throw new RuntimeException('Aucun fichier reçu (ou upload incomplet).');
            }
            $created = $service->upload($type, $race, (string) $file['tmp_name']);
            setFlash('success', ucfirst($type->value) . " « {$created} » ajouté pour la race {$race}"
                . ($type === ImageType::PORTRAIT ? ' (miniature générée)' : '') . '.');
        } elseif (isset($_POST['image_delete'])) {
            $name = trim((string) ($_POST['file'] ?? ''));
            $service->delete($type, $race, $name);
            setFlash('success', ucfirst($type->value) . " « {$name} » supprimé"
                . ($type === ImageType::PORTRAIT ? ' (miniature comprise)' : '') . '.');
        }
    } catch (Throwable $e) {
        setFlash('danger', $e->getMessage());
    }
    redirectTo($backTo); // PRG
}

try {
    $entries = $race !== '' ? $service->inventory($type, $race) : [];
} catch (Throwable $e) {
    $entries = [];
    setFlash('danger', 'Inventaire impossible : ' . $e->getMessage());
}
$withProblems = array_filter($entries, fn(array $entry) => $entry['problems'] !== []);
$thumbHeight = $type === ImageType::PORTRAIT ? 76 : 50;
$usersByPath = $entries !== [] ? $service->usersByPath($type) : [];
$imageDir = '/img/' . ($type === ImageType::PORTRAIT ? 'portraits' : 'avatars') . '/' . $race . '/';

ob_start();
?>

<div class="container">
    <h3><?= e($pageTitle) ?></h3>

    <?= renderFlashMessage() ?>

    <div class="alert alert-info" style="font-size: 13px; line-height: 1.5;">
        <?php if ($structureMode): ?>
            Images des types de bâtiments : la <strong>première image du stock</strong> est le
            sprite des entités posées sur le plateau (à défaut, le sprite de mur du même nom).
            L'ajout redimensionne au canon et numérote avec le compteur du type ; la suppression
            est refusée tant qu'une entité posée utilise l'image.
        <?php else: ?>
            Images de personnage par race : avatars (50×50, carte et listes) et portraits
            (210×320 + miniature 50×79, fiche de personnage). L'ajout redimensionne au canon et
            numérote avec le compteur de la race ; la suppression est refusée tant qu'un joueur
            utilise l'image. Les joueurs choisissent leurs images en jeu — ici c'est le stock.
        <?php endif; ?>
    </div>

    <div class="card mt-3">
        <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
            <span class="text-muted" style="font-size:13px;"><i class="fas fa-image"></i> Image :</span>
            <?php foreach (ImageType::cases() as $candidate): ?>
                <a href="<?= e($selfPage) ?>?type=<?= e($candidate->value) ?>"
                   class="btn btn-sm <?= $candidate === $type ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= e(ucfirst($candidate->value)) ?>s
                </a>
            <?php endforeach; ?>
            <span class="text-muted" style="font-size:13px;margin-left:1rem;">
                <i class="fas fa-dragon"></i> <?= $structureMode ? 'Type :' : 'Race :' ?></span>
            <?php foreach ($races as $candidate): ?>
                <a href="<?= e($selfPage) ?>?type=<?= e($type->value) ?>&amp;race=<?= e(urlencode($candidate)) ?>"
                   class="btn btn-sm <?= $candidate === $race ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= e($candidate) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($race !== ''): ?>
    <div class="card mt-3">
        <div class="card-body">
            <h5 class="card-title">Ajouter un <?= e($type->value) ?> — <?= $structureMode ? 'type' : 'race' ?> <?= e($race) ?></h5>
            <form method="post" enctype="multipart/form-data" class="d-flex align-items-end gap-3 flex-wrap">
                <?= $csrf->renderTokenField() ?>
                <input type="hidden" name="type" value="<?= e($type->value) ?>">
                <input type="hidden" name="race" value="<?= e($race) ?>">
                <div class="form-group mb-0">
                    <label style="font-size:13px;">Image (png / jpeg / webp / gif, max 4 Mo)</label>
                    <input type="file" class="form-control-file" name="image_file" accept=".png,.jpg,.jpeg,.webp,.gif" required>
                </div>
                <button type="submit" name="image_upload" value="1" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Ajouter
                </button>
                <small class="text-muted">Redimensionnée en <?= implode('×', $type->dimensions()) ?>,
                    numérotée automatiquement<?= $type === ImageType::PORTRAIT ? ', miniature 50×79 générée' : '' ?>.</small>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                <h5 class="card-title mb-0"><?= e(ucfirst($type->value)) ?>s — <?= e($race) ?></h5>
                <span class="badge bg-secondary"><?= count($entries) ?> images</span>
                <?php if (count($withProblems) > 0): ?>
                    <span class="badge" style="background-color:#f0ad4e;color:#fff;"><?= count($withProblems) ?> avec problème(s)</span>
                <?php else: ?>
                    <span class="badge" style="background-color:#198754;color:#fff;">aucun problème</span>
                <?php endif; ?>
            </div>

            <?php if ($entries === []): ?>
                <div class="alert alert-info mb-0">Aucune image pour cette race.</div>
            <?php else: ?>
                <table class="table table-sm table-striped" style="font-size:13px;" data-admin-list data-page-size="40">
                    <thead><tr>
                        <th></th><th>Fichier</th><th>Taille</th>
                        <?php if ($type === ImageType::PORTRAIT): ?><th>Miniature</th><?php endif; ?>
                        <th title="Joueurs utilisant cette image">Joueurs</th><th>Problèmes</th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td style="width:64px;">
                                <?php if (!$entry['missing']): ?>
                                    <a href="<?= e($imageDir . $entry['file']) ?>" target="_blank"
                                       title="Afficher en taille réelle (clic droit pour télécharger)">
                                        <img src="<?= e($imageDir . $entry['file']) ?>"
                                             height="<?= $thumbHeight ?>" loading="lazy"
                                             style="object-fit:contain;border:1px solid #ddd;" alt="">
                                    </a>
                                <?php else: ?>
                                    <span class="badge badge-danger">absente</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?= e($entry['file']) ?></code></td>
                            <td><?= $entry['missing'] ? '—' : $entry['width'] . '×' . $entry['height'] ?></td>
                            <?php if ($type === ImageType::PORTRAIT): ?>
                                <td><?= $entry['hasMini'] ? '✓' : '<span class="text-muted">—</span>' ?></td>
                            <?php endif; ?>
                            <td>
                                <?php $imageUsers = $usersByPath['img/' . ($type === ImageType::PORTRAIT ? 'portraits' : 'avatars') . '/' . $race . '/' . $entry['file']] ?? []; ?>
                                <?php if ($imageUsers !== []): ?>
                                    <details class="row-popover">
                                        <summary class="btn btn-sm btn-outline-secondary" style="cursor:pointer;list-style:none;"
                                                 title="Joueurs utilisant cette image"><strong><?= count($imageUsers) ?></strong></summary>
                                        <div class="row-popover-panel" style="max-height:12rem;overflow:auto;">
                                            <?php foreach ($imageUsers as $userLabel): ?>
                                                <div><?= e($userLabel) ?></div>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php foreach ($entry['problems'] as $problem): ?>
                                    <div class="text-warning" style="font-size:12px;"><i class="fas fa-exclamation-triangle"></i> <?= e($problem) ?></div>
                                <?php endforeach; ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <?php if (!$entry['missing']): ?>
                                    <form method="post" style="display:inline-block;"
                                          onsubmit="return confirm('Supprimer définitivement « <?= e($entry['file']) ?> » ?');">
                                        <?= $csrf->renderTokenField() ?>
                                        <input type="hidden" name="type" value="<?= e($type->value) ?>">
                                        <input type="hidden" name="race" value="<?= e($race) ?>">
                                        <input type="hidden" name="file" value="<?= e($entry['file']) ?>">
                                        <button type="submit" name="image_delete" value="1" class="btn btn-sm btn-outline-danger"
                                                <?= $entry['usage'] > 0 ? 'disabled title="Encore utilisée par des joueurs"' : '' ?>>
                                            Supprimer
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
echo admin_layout($pageTitle, $content);
