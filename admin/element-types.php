<?php
/**
 * Element types (admin → Éléments → Types): what the Tiled brush can lay on
 * a cell, and the effect stepping on it applies.
 *
 * The list is the IMAGES in `img/elements/` (what can be painted) plus the
 * names already placed. The effect comes from `element_types`
 * (MapElementService::effectOf): a type without a row applies the effect
 * of its own name, a row without an effect makes it decor.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\MapElementService;
use Classes\Db;

$db = new Db();
$csrf = new CsrfProtectionService();
$elements = new MapElementService();

if (isset($_POST['element_type'])) {
    handleAdminPost('element-types.php', function () use ($elements): string {
        $name = (string) $_POST['element_type'];
        $effect = trim((string) ($_POST['effect'] ?? ''));
        $elements->setEffect($name, $effect === '' ? null : $effect);
        $elements->setFluid($name, isset($_POST['fluid']));
        $opacity = trim((string) ($_POST['opacity'] ?? ''));
        $elements->setOpacity($name, $opacity === '' ? null : (int) $opacity);
        $elements->setLabel($name, trim((string) ($_POST['label'] ?? '')));
        $elements->setEffectStrength($name, (int) ($_POST['effect_duration'] ?? 1), (int) ($_POST['effect_value'] ?? 1));
        $elements->setSpill($name, (int) ($_POST['lifetime_turns'] ?? 2), (int) ($_POST['spill_chance'] ?? 100));
        // Last: it needs element_types.depth, added by a migration
        $elements->setDepth($name, (int) ($_POST['depth'] ?? MapElementService::DEPTH_VOLUME));

        return $effect === ''
            ? "« {$name} » est maintenant un décor, sans effet."
            : "Marcher sur « {$name} » applique maintenant l'effet « {$effect} ».";
    });
}

$effects = [];
$res = $db->exe('SELECT name, label FROM effects ORDER BY name');
while ($row = $res->fetch_object()) {
    $effects[(string) $row->name] = $row->label !== '' ? $row->label . ' (' . $row->name . ')' : (string) $row->name;
}

$placed = [];
$res = $db->exe('SELECT name, COUNT(*) AS n FROM map_elements GROUP BY name');

while ($row = $res->fetch_assoc()) {
    $placed[(string) $row['name']] = (int) $row['n'];
}

// One image per name, the one the board draws
$images = [];
foreach ($elements->placeableNames() as $name) {
    $images[$name] = $elements->imagePath($name);
}

/* The catalogue is the union: what can be painted, plus what is already
 * placed — a placed name whose image is gone must show, not vanish. */
$names = array_keys($images + $placed);
sort($names);

$rows = [];

// Each control under its caption: the row is too long to read on tooltips alone
$field = static fn (string $caption, string $control): string =>
    '<label class="mb-0 small text-muted d-flex flex-column align-items-start gap-1">' . e($caption) . $control . '</label>';

foreach ($names as $name) {
    $effect = $elements->effectOf($name);
    $count = $placed[$name] ?? 0;
    $image = $images[$name] ?? null;

    $thumb = $image !== null
        ? '<img class="tile-thumb" src="/' . e($image) . '" height="28" loading="lazy" alt=""'
            . ' style="image-rendering:pixelated;">'
        : '<span class="badge badge-warning" title="Posé sur la carte mais aucune image dans img/elements/ :'
            . ' rien n\'est affiché sur la case">sans image</span>';

    $options = '<option value="">— aucun (décor) —</option>';
    foreach ($effects as $effectName => $label) {
        $options .= '<option value="' . e($effectName) . '"' . ($effectName === $effect ? ' selected' : '') . '>'
            . e($label) . '</option>';
    }
    $does = '<form method="post" class="d-flex flex-wrap align-items-end gap-2 mb-0">' . $csrf->renderTokenField()
        . '<input type="hidden" name="element_type" value="' . e($name) . '">'
        . $field('Nom affiché', '<input name="label" class="form-control form-control-sm" style="width:10em" maxlength="100"'
            . ' placeholder="' . e($name) . '" value="' . e($elements->labelOf($name) === $name ? '' : $elements->labelOf($name)) . '"'
            . ' title="Nom affiché au joueur ; vide = le code">')
        . $field('Effet', '<select name="effect" class="form-control form-control-sm" style="width:auto">' . $options . '</select>')
        . $field('Durée effet', '<input type="number" name="effect_duration" min="-1" class="form-control form-control-sm" style="width:5em"'
            . ' value="' . $elements->effectDurationOf($name) . '"'
            . ' title="Durée de l\'effet, en tours : 0 jusqu\'au prochain tour, -1 sans fin">')
        . $field('Intensité', '<input type="number" name="effect_value" min="1" class="form-control form-control-sm" style="width:4em"'
            . ' value="' . $elements->effectValueOf($name) . '"'
            . ' title="Intensité : multiplie les modificateurs de l\'effet">')
        . $field('Durée au sol', '<input type="number" name="lifetime_turns" min="-1" class="form-control form-control-sm" style="width:5em"'
            . ' value="' . $elements->lifetimeTurnsOf($name) . '"'
            . ' title="Versé par une blessure (sang…) : tours passés au sol, -1 sans fin">')
        . $field('Chance %', '<input type="number" name="spill_chance" min="0" max="100" class="form-control form-control-sm" style="width:5em"'
            . ' value="' . $elements->spillChanceOf($name) . '"'
            . ' title="Versé par une blessure : chance en % que le coup le verse vraiment">')
        . $field('Fluide', '<span class="form-control-sm"><input type="checkbox" name="fluid"' . ($elements->isFluid($name) ? ' checked' : '')
            . ' title="Se fond avec ses voisins de la même famille : bords estompés, coudes. Décoché, chaque case est dessinée seule."></span>')
        . $field('Opacité %', '<input type="number" name="opacity" min="0" max="100" class="form-control form-control-sm" style="width:5em"'
            . ' placeholder="' . ($image !== null && str_ends_with($image, '.webp') ? 50 : 100) . '"'
            . ' value="' . ($elements->opacityOf($name) ?? '') . '"'
            . ' title="Opacité sur la carte, en % ; vide = 50 pour une texture webp, 100 sinon">')
        . $field('Niveau', '<select name="depth" class="form-control form-control-sm" style="width:auto"'
            . ' title="Place sur le damier : les nappes dessous, les traces au-dessus, les volumes et effets tout en haut">'
            . implode('', array_map(
                fn (int $level, string $label): string => '<option value="' . $level . '"'
                    . ($elements->depthOf($name) === $level ? ' selected' : '') . '>' . e($label) . '</option>',
                array_keys(MapElementService::DEPTH_LABELS),
                MapElementService::DEPTH_LABELS
            ))
            . '</select>')
        . '<button class="btn btn-sm btn-outline-primary">Enregistrer</button>'
        . ($effect !== null
            ? ' <a class="btn btn-sm btn-link" href="/admin/effects.php?action=edit&amp;name=' . e(urlencode($effect)) . '">voir</a>'
            : '')
        . '</form>';

    $rows[] = '<tr>'
        . '<td>' . $thumb . '</td>'
        . '<td><code>' . e($name) . '</code></td>'
        . '<td>' . $does . '</td>'
        . '<td>' . ($count > 0
            ? '<a href="/admin/map-elements.php">' . $count . '</a>'
            : '<span class="text-muted">0</span>') . '</td>'
        . '</tr>';
}

$inert = count(array_filter($names, static fn (string $name): bool => $elements->effectOf($name) === null));

$intro = 'Éléments que le pinceau de Tiled peut poser sur une case. '
    . 'La liste vient des <strong>images</strong> de <code>img/elements/</code> ; '
    . 'l\'<strong>effet choisi</strong> est appliqué quand on marche sur la case. '
    . 'Tant qu\'aucun n\'a été choisi, c\'est l\'effet du même nom, s\'il existe. '
    . 'Les deux nombres règlent sa <strong>durée</strong> en tours (0 jusqu\'au prochain tour, -1 sans fin) et son <strong>intensité</strong>. '
    . 'Un type sans effet est purement décoratif. '
    . 'Pour ce qu\'une blessure verse au sol (le <code>sang</code>, réglé par race) : les deux nombres suivants donnent '
    . 'sa <strong>durée au sol</strong> en tours (-1 sans fin) et la <strong>chance</strong>, en %, qu\'un coup le verse vraiment. '
    . 'Un type <strong>fluide</strong> (eau, lave…) se fond avec ses voisins de la même famille ; '
    . 'décoché, chaque case est dessinée seule. '
    . 'L\'<strong>opacité</strong> (en %) laisse voir le sol dessous ; vide, 50 pour une texture webp, 100 sinon. '
    . 'Le <strong>niveau</strong> règle l\'empilement sur le damier : les nappes (eau, lave, boue) dessous, '
    . 'les traces au sol (sang, pas) au-dessus, les volumes et effets (feu, ronces) tout en haut ; '
    . 'à niveau égal, l\'ordre alphabétique.'
    . ($inert > 0
        ? ' <strong>' . $inert . ' type(s) sans effet</strong> sur cette carte.'
        : '');

$content = renderTable(
        [
            '',
            'Nom',
            ['Nom affiché · effet appliqué', 'title="Nom vu par le joueur ; effet appliqué en marchant sur la case"'],
            ['Posés', 'title="Cases map_elements de ce nom"'],
        ],
        $rows,
        'class="table table-striped table-sm" data-admin-list data-page-size="30"'
    );

echo admin_layout('Types d\'éléments', $content, ['intro' => $intro]);
