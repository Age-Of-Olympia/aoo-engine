<?php
/**
 * Tuiles & images (admin dashboard → Cartes) : inventaire des images de
 * chaque couche de carte (sol, murs, éléments, décors…) avec diagnostics —
 * PNG à palette (source des fondus noirs), formats multiples, noms
 * invalides, images posées sur les cartes mais absentes du serveur, images
 * inutilisées — et gestion : ajout (normalisé en PNG vraies couleurs),
 * suppression et renommage avec garde-fous (voir TileAssetService).
 */
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php';

use App\Service\CsrfProtectionService;
use App\Service\TileAssetService;

$csrf = new CsrfProtectionService();
$service = new TileAssetService();

/** Board layers by what an admin calls them; the key is the image folder and the Tiled layer. */
const TILE_LAYER_LABELS = [
    'tiles' => 'Sols', 'routes' => 'Routes', 'plants' => 'Plantes', 'resources' => 'Ressources',
    'elements' => 'Éléments', 'marks' => 'Marques', 'foregrounds' => 'Premiers plans',
    'triggers' => 'Déclencheurs', 'dialogs' => 'Dialogues',
];

$layer = stringWithDefault('layer', (string) ($_GET['layer'] ?? 'tiles'));
if (!in_array($layer, $service->layers(), true)) {
    $layer = 'tiles';
}

$isStateChangingPost = $_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['asset_upload']) || isset($_POST['asset_delete'])
        || isset($_POST['asset_rename']) || isset($_POST['asset_move']));
if ($isStateChangingPost) {
    requireAdminPost('tile-assets.php?layer=' . urlencode($layer));

    try {
        if (isset($_POST['asset_upload'])) {
            $name = trim((string) ($_POST['new_name'] ?? ''));
            $file = $_FILES['asset_file'] ?? null;
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                || !is_uploaded_file((string) $file['tmp_name'])) {
                throw new RuntimeException('Aucun fichier reçu (ou upload incomplet).');
            }
            $service->add($layer, $name, (string) $file['tmp_name']);
            setFlash('success', "Image « {$name} » ajoutée à img/{$layer}/.");
        } elseif (isset($_POST['asset_delete'])) {
            $name = trim((string) ($_POST['name'] ?? ''));
            $service->delete($layer, $name);
            setFlash('success', "Image « {$name} » supprimée.");
        } elseif (isset($_POST['asset_rename'])) {
            $old = trim((string) ($_POST['name'] ?? ''));
            $new = trim((string) ($_POST['new_name'] ?? ''));
            $result = $service->rename($layer, $old, $new);
            $notice = $result['warnings'] === [] ? '' : ' ⚠ ' . implode(' ; ', $result['warnings']) . '.';
            $renamed = $result['transitions']['renamed'];
            if ($renamed !== []) {
                $notice .= ' ' . count($renamed) . ' fondu(s) renommé(s) : ' . implode(', ', array_keys($renamed)) . '.';
            }
            if ($result['transitions']['unparsed'] !== []) {
                $notice .= ' ⚠ Fondus indéchiffrables laissés tels quels (ils fonctionnent toujours) : '
                    . implode(', ', $result['transitions']['unparsed']) . '.';
            }
            setFlash('success', "« {$old} » renommée en « {$new} » — "
                . $result['rowsUpdated'] . ' case(s) de carte mises à jour.' . $notice);
        } elseif (isset($_POST['asset_move'])) {
            $name = trim((string) ($_POST['name'] ?? ''));
            $target = trim((string) ($_POST['target_layer'] ?? ''));
            $service->move($layer, $name, $target);
            setFlash('success', "« {$name} » déplacée de la couche {$layer} vers {$target}.");
        }
    } catch (Throwable $e) {
        setFlash('danger', $e->getMessage());
    }
    redirectTo('tile-assets.php?layer=' . urlencode($layer)); // PRG : pas de re-soumission au refresh
}

// Positions of one tile, fetched when its popover opens (admin-list.js data-lazy-src)
if (isset($_GET['positions'])) {
    $positions = $service->positionsOfTile($layer, (string) $_GET['positions']);
    echo implode('', array_map(static fn (string $position): string => '<div>' . e($position) . '</div>', $positions));
    if (count($positions) >= 200) {
        echo '<div class="text-muted">… (200 premières)</div>';
    }
    exit;
}

try {
    $inventory = $service->inventory($layer);
} catch (Throwable $e) {
    $inventory = ['entries' => [], 'transitions' => 0];
    setFlash('danger', 'Inventaire impossible : ' . $e->getMessage());
}

$withProblems = array_filter($inventory['entries'], fn(array $entry) => $entry['problems'] !== []);

ob_start();
?>

<div class="container">
    <div class="card">
        <div class="card-body py-2 d-flex align-items-center gap-2 flex-wrap">
            <span class="text-muted"><i class="fas fa-layer-group"></i> Couche :</span>
            <?php foreach ($service->layers() as $candidate): ?>
                <a href="/admin/tile-assets.php?layer=<?= e(urlencode($candidate)) ?>"
                   class="btn btn-sm <?= $candidate === $layer ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= techLabel(TILE_LAYER_LABELS[$candidate] ?? $candidate, $candidate) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <h5 class="card-title">Ajouter une image (<?= e(TILE_LAYER_LABELS[$layer] ?? $layer) ?>)</h5>
            <form method="post" enctype="multipart/form-data" class="d-flex align-items-end gap-3 flex-wrap">
                <?= $csrf->renderTokenField() ?>
                <input type="hidden" name="layer" value="<?= e($layer) ?>">
                <div class="form-group mb-0">
                    <label>Nom (sans extension)</label>
                    <input type="text" class="form-control form-control-sm" name="new_name" required
                           pattern="[a-zA-Z0-9_.-]+" placeholder="ex: sable_noir">
                </div>
                <div class="form-group mb-0">
                    <label>Image (png / webp / gif, max 4 Mo)</label>
                    <input type="file" class="form-control-file" name="asset_file" accept=".png,.webp,.gif" required>
                </div>
                <button type="submit" name="asset_upload" value="1" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Ajouter
                </button>
                <small class="text-muted">PNG et GIF convertis en PNG vraies couleurs, WebP conservé tel quel. Les tuiles du sol doivent faire 50×50
                    pour apparaître dans les palettes d'éditeur.</small>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                <h5 class="card-title mb-0">Inventaire — <?= e(TILE_LAYER_LABELS[$layer] ?? $layer) ?></h5>
                <span class="badge bg-secondary"><?= count($inventory['entries']) ?> images</span>
                <?php if (count($withProblems) > 0): ?>
                    <span class="badge badge-solid badge-warning"><?= count($withProblems) ?> avec problème(s)</span>
                <?php else: ?>
                    <span class="badge badge-solid badge-success">aucun problème</span>
                <?php endif; ?>
                <?php if ($inventory['transitions'] > 0): ?>
                    <small class="text-muted"><?= $inventory['transitions'] ?> fondus non listés (voir Transitions de terrain)</small>
                <?php endif; ?>
            </div>

            <?php if ($inventory['entries'] === []): ?>
                <div class="alert alert-info mb-0">Aucune image dans cette couche<code class="tech-name">img/<?= e($layer) ?>/</code>.</div>
            <?php else: ?>
                <table class="table table-sm table-striped" data-admin-list data-page-size="50">
                    <thead><tr>
                        <th></th><th>Nom</th><th>Fichier(s)</th><th>Taille</th>
                        <th title="Cases posées sur l'ensemble des cartes">Usage</th><th>Problèmes</th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($inventory['entries'] as $entry): ?>
                        <tr>
                            <td style="width:56px;">
                                <?php if (!$entry['missing']): ?>
                                    <img class="tile-thumb" src="/img/<?= e($layer) ?>/<?= e($entry['files'][0]) ?>" width="50" height="50"
                                         loading="lazy" style="image-rendering:pixelated;object-fit:contain;" alt="">
                                <?php else: ?>
                                    <span class="badge badge-danger">absente</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?= e($entry['name']) ?></code>
                                <?= $entry['isTerrain'] ? ' <span class="badge badge-success" title="Déclarée terrain (fondable)">terrain</span>' : '' ?></td>
                            <td class="small" style="word-break:break-all;"><?= e(implode(', ', $entry['files'])) ?></td>
                            <td><?= $entry['missing'] ? '—' : $entry['width'] . '×' . $entry['height'] ?></td>
                            <td>
                                <?php if ($entry['usage'] > 0): ?>
                                    <details class="row-popover">
                                        <summary class="btn btn-sm btn-outline-secondary"
                                                 title="Positions de cette tuile sur les cartes"><strong><?= $entry['usage'] ?></strong></summary>
                                        <div class="row-popover-panel small" style="max-height:12rem;overflow:auto;"
                                             data-lazy-src="?layer=<?= e(urlencode($layer)) ?>&amp;positions=<?= e(urlencode($entry['name'])) ?>">…</div>
                                    </details>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                                <?php if ($entry['plans'] !== []): ?>
                                    <span class="badge badge-solid badge-info" title="<?= e(implode(', ', $entry['plans'])) ?>">fond de <?= count($entry['plans']) ?> plan(s)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php foreach ($entry['problems'] as $problem): ?>
                                    <div class="text-warning small"><i class="fas fa-exclamation-triangle"></i> <?= e($problem) ?></div>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-nowrap">
                                <?php if (!$entry['missing']): ?>
                                    <details class="row-popover">
                                        <summary class="btn btn-sm btn-outline-secondary">Renommer</summary>
                                        <form method="post" class="row-popover-panel d-flex gap-2"
                                              data-confirm="Renommer « <?= e($entry['name']) ?> » partout (cartes comprises) ?">
                                            <?= $csrf->renderTokenField() ?>
                                            <input type="hidden" name="layer" value="<?= e($layer) ?>">
                                            <input type="hidden" name="name" value="<?= e($entry['name']) ?>">
                                            <input type="text" class="form-control form-control-sm" name="new_name"
                                                   pattern="[a-zA-Z0-9_.-]+" required placeholder="nouveau nom" style="width:9rem;">
                                            <button type="submit" name="asset_rename" value="1" class="btn btn-sm btn-primary">OK</button>
                                        </form>
                                    </details>
                                    <details class="row-popover">
                                        <summary class="btn btn-sm btn-outline-secondary"
                                                 title="Changer de type : déplacer l'image vers une autre couche">Déplacer</summary>
                                        <form method="post" class="row-popover-panel d-flex gap-2"
                                              data-confirm="Déplacer « <?= e($entry['name']) ?> » vers la couche sélectionnée ?">
                                            <?= $csrf->renderTokenField() ?>
                                            <input type="hidden" name="layer" value="<?= e($layer) ?>">
                                            <input type="hidden" name="name" value="<?= e($entry['name']) ?>">
                                            <?php
                                            $layerChoices = [];
                                            foreach ($service->layers() as $target) {
                                                if ($target !== $layer) {
                                                    $layerChoices[$target] = TILE_LAYER_LABELS[$target] ?? $target;
                                                }
                                            }
                                            echo formSelect('target_layer', $layerChoices, null, null,
                                                'class="form-control form-control-sm" style="width:9rem;"');
                                            ?>
                                            <button type="submit" name="asset_move" value="1" class="btn btn-sm btn-primary">OK</button>
                                        </form>
                                    </details>
                                    <form method="post" class="d-inline-block"
                                          data-confirm="Supprimer définitivement « <?= e($entry['name']) ?> » ?">
                                        <?= $csrf->renderTokenField() ?>
                                        <input type="hidden" name="layer" value="<?= e($layer) ?>">
                                        <input type="hidden" name="name" value="<?= e($entry['name']) ?>">
                                        <button type="submit" name="asset_delete" value="1" class="btn btn-sm btn-outline-danger"
                                                <?= $entry['usage'] > 0 || $entry['isTerrain'] || $entry['plans'] !== [] ? 'disabled title="Encore utilisée, déclarée terrain ou fond de plan"' : '' ?>>
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
</div>

<?php
$content = ob_get_clean();
echo admin_layout('Tuiles & images', $content, ['intro' => 'Les images de chaque couche de la carte et leurs problèmes'
        . ' (image à palette qui noircit les fondus, nom invalide, image posée mais absente du serveur…).'
        . ' Une image posée quelque part ou servant de fond à un plan ne peut pas être supprimée ; la renommer met à jour les cartes.'
        . ' Les fondus se gèrent dans Transitions de terrain.']);
