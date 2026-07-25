<?php
/**
 * Palette carte des tuiles/biomes (admin dashboard → Cartes · Couleurs de
 * carte) : la table tile_colors, ex-ColorService::initializePastelColors()
 * (Version20260723122000_TileColorsFromCode). C'est la couleur de chaque
 * tuile sur la carte monde générée (ViewService) ; les tuiles de transition
 * (trans_A_B_code) mélangent automatiquement les couleurs de leurs biomes,
 * elles n'ont pas d'entrée ici.
 *
 * Une seule vue : la liste entière, éditable en ligne au sélecteur de
 * couleur. « default » est la couleur de repli des tuiles inconnues, non
 * supprimable.
 *
 * Toutes les mutations POSTent vers tile-colors-save.php (CSRF, PRG).
 * Cette page ne fait que rendre. Accès via layout.php (AdminMenuAccessService).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\ColorService;
use App\Service\CsrfProtectionService;

/** @param array{int, int, int} $rgb */
function tile_color_hex(array $rgb): string
{
    return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
}

/**
 * Vignette de la tuile correspondante quand l'image existe dans
 * img/tiles — le nom de palette EST le nom d'asset. Couvre les tuiles
 * fixes (<nom>.png/webp…) et la première frame des tuiles animées
 * (<nom>-00.png). Une entrée de palette sans image (biome de
 * transition, entrée historique) garde un tiret : c'est justement le
 * genre d'écart que tile-assets.php audite.
 */
function tile_color_thumbnail(string $name): string
{
    /* La regex protège du path traversal sur un nom venu de la base :
     * ne pas la relâcher, les dossiers ci-dessous sont concaténés. */
    if (preg_match('/^[a-zA-Z0-9_.-]+$/', $name)) {

        /* La palette ne colore pas que le SOL : elle mélange les tuiles,
         * les murs et les éléments posés, et chaque couche range ses
         * images dans son propre dossier. Chercher dans le seul
         * img/tiles laissait un tiret sur des entrées bien vivantes —
         * arbre1..5 vivent dans img/walls, boue et lave dans
         * img/elements. La correspondance couche → dossier est celle de
         * TiledMapService, pas une seconde liste à tenir à jour. */
        $dirs = array_unique(array_map(
            [\App\Service\TiledMapService::class, 'layerImageDir'],
            ['tiles', 'resources', 'elements', 'foregrounds']
        ));

        $candidates = ['png', 'webp', 'jpg', 'jpeg', 'gif'];

        foreach ($dirs as $dir) {
            $paths = array_map(
                static fn (string $ext): string => 'img/' . $dir . '/' . $name . '.' . $ext,
                $candidates
            );
            /* Tuile animée : la première frame sert de vignette. */
            $paths[] = 'img/' . $dir . '/' . $name . '-00.png';

            foreach ($paths as $rel) {
                if (is_file($_SERVER['DOCUMENT_ROOT'] . '/' . $rel)) {
                    return '<img src="/' . e($rel) . '" width="28" height="28" loading="lazy"'
                        . ' style="border:1px solid #ccc;vertical-align:middle" alt="" title="' . e($rel) . '">';
                }
            }
        }
    }

    return '<span class="text-muted" title="Aucune image « ' . e($name) . ' » dans les dossiers de couches">—</span>';
}

$csrfToken = (new CsrfProtectionService())->generateToken();

$palette = ColorService::palette();
ksort($palette);

$rows = [];
foreach ($palette as $name => $rgb) {
    $hex = tile_color_hex($rgb);

    $editForm = '<form method="post" action="/admin/tile-colors-save.php?action=save" class="form-inline">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="name" value="' . e((string) $name) . '">'
        . '<input type="color" class="form-control form-control-sm mr-2" style="width:4em" name="color" value="' . e($hex) . '">'
        . '<button type="submit" class="btn btn-sm btn-outline-primary">Enregistrer</button>'
        . '</form>';

    $deleteCell = $name === 'default'
        ? '<span class="text-muted" title="Couleur de repli des tuiles inconnues — non supprimable">—</span>'
        : '<form method="post" action="/admin/tile-colors-save.php?action=delete"'
            . ' onsubmit="return confirm(\'Supprimer la couleur de « ' . e((string) $name) . ' » ? La tuile prendra la couleur default.\');">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="name" value="' . e((string) $name) . '">'
            . '<button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>'
            . '</form>';

    $rows[] = '<tr>'
        . '<td>' . tile_color_thumbnail((string) $name) . '</td>'
        . '<td><span style="display:inline-block;width:1.4em;height:1.4em;border:1px solid #ccc;vertical-align:middle;background:' . e($hex) . '"></span></td>'
        . '<td><code>' . e((string) $name) . '</code></td>'
        . '<td class="text-muted">' . e($hex) . ' · rgb(' . implode(', ', $rgb) . ')</td>'
        . '<td>' . $editForm . '</td>'
        . '<td>' . $deleteCell . '</td>'
        . '</tr>';
}

$addForm = '<form method="post" action="/admin/tile-colors-save.php?action=save" class="form-inline">'
    . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
    . '<input type="text" class="form-control form-control-sm mr-2" name="name" required'
    . ' pattern="[a-zA-Z0-9_.-]+" placeholder="nom de tuile/biome" title="Même règle que les noms de tuiles/assets">'
    . '<input type="color" class="form-control form-control-sm mr-2" style="width:4em" name="color" value="#649664">'
    . '<button type="submit" class="btn btn-primary btn-sm">+ Ajouter</button>'
    . '</form>';

$content = '<div class="d-flex justify-content-between align-items-center mb-3">'
    . '<h1 class="mb-0">Couleurs de carte</h1>' . $addForm . '</div>'
    . '<p class="text-muted">Couleur de chaque tuile/biome sur la carte monde générée. Une tuile absente de la '
    . 'palette prend la couleur <code>default</code> ; les tuiles de transition mélangent automatiquement '
    . 'les couleurs de leurs biomes. Regénérer la carte (Cartes · Carte monde) pour voir le résultat.</p>'
    . renderTable(
        [['Tuile', 'title="Image de la tuile dans img/tiles, quand elle existe"'], '', 'Nom', 'Valeur', 'Couleur', ''],
        $rows,
        'class="table table-striped table-sm" data-admin-list data-page-size="30"'
    );

echo admin_layout('Couleurs de carte', renderFlashMessage() . $content);
