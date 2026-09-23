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

/** The images the Tiled palette offers, by name. */
function element_images(): array
{
    $images = [];
    $dir = $_SERVER['DOCUMENT_ROOT'] . '/img/elements/';

    foreach (glob($dir . '*') ?: [] as $file) {
        $name = pathinfo($file, PATHINFO_FILENAME);

        /* Tiled leaves .gif out of its palette; show the same set. */
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'gif') {
            $images[$name] = 'img/elements/' . basename($file);
        }
    }

    return $images;
}

$db = new Db();
$csrf = new CsrfProtectionService();
$elements = new MapElementService();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['element_type'])) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
        $name = (string) $_POST['element_type'];
        $effect = trim((string) ($_POST['effect'] ?? ''));
        $elements->setEffect($name, $effect === '' ? null : $effect);
        setFlash('success', $effect === ''
            ? "« {$name} » est maintenant un décor, sans effet."
            : "Marcher sur « {$name} » applique maintenant l'effet « {$effect} ».");
    } catch (Throwable $e) {
        setFlash('danger', $e->getMessage());
    }
    redirectTo('element-types.php'); // PRG
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

$images = element_images();

/* The catalogue is the union: what can be painted, plus what is already
 * placed — a placed name whose image is gone must show, not vanish. */
$names = array_keys($images + $placed);
sort($names);

$rows = [];

foreach ($names as $name) {
    $effect = $elements->effectOf($name);
    $count = $placed[$name] ?? 0;
    $image = $images[$name] ?? null;

    $thumb = $image !== null
        ? '<img src="/' . e($image) . '" height="28" loading="lazy" alt=""'
            . ' style="image-rendering:pixelated;background:#e7ded0;border:1px solid #ddd;">'
        : '<span class="badge badge-warning" title="Posé sur la carte mais aucune image dans img/elements/ :'
            . ' rien n\'est affiché sur la case">sans image</span>';

    $options = '<option value="">— aucun (décor) —</option>';
    foreach ($effects as $effectName => $label) {
        $options .= '<option value="' . e($effectName) . '"' . ($effectName === $effect ? ' selected' : '') . '>'
            . e($label) . '</option>';
    }
    $does = '<form method="post" class="d-flex gap-2 mb-0">' . $csrf->renderTokenField()
        . '<input type="hidden" name="element_type" value="' . e($name) . '">'
        . '<select name="effect" class="form-control form-control-sm" style="width:auto">' . $options . '</select>'
        . '<button class="btn btn-sm btn-outline-primary">Enregistrer</button>'
        . ($effect !== null
            ? ' <a class="btn btn-sm btn-link" href="/admin/effects.php?action=edit&amp;name=' . e(urlencode($effect)) . '">voir</a>'
            : '')
        . '</form>';

    $rows[] = '<tr>'
        . '<td>' . $thumb . '</td>'
        . '<td><code style="display:inline">' . e($name) . '</code></td>'
        . '<td>' . $does . '</td>'
        . '<td>' . ($count > 0
            ? '<a href="/admin/map-elements.php">' . $count . '</a>'
            : '<span class="text-muted">0</span>') . '</td>'
        . '</tr>';
}

$inert = count(array_filter($names, static fn (string $name): bool => $elements->effectOf($name) === null));

$content = '<div class="d-flex justify-content-between align-items-center mb-3">'
    . '<h1 class="mb-0">Types d\'éléments</h1></div>'
    . '<p class="text-muted">Éléments que le pinceau de Tiled peut poser sur une case. '
    . 'La liste vient des <strong>images</strong> de <code>img/elements/</code> ; '
    . 'l\'<strong>effet choisi</strong> est appliqué quand on marche sur la case. '
    . 'Tant qu\'aucun n\'a été choisi, c\'est l\'effet du même nom, s\'il existe. '
    . 'Un type sans effet est purement décoratif.'
    . ($inert > 0
        ? ' <strong>' . $inert . ' type(s) sans effet</strong> sur cette carte.'
        : '')
    . '</p>'
    . renderTable(
        [
            '',
            'Nom',
            ['Effet appliqué', 'title="Appliqué en marchant sur la case"'],
            ['Posés', 'title="Cases map_elements de ce nom"'],
        ],
        $rows,
        'class="table table-striped table-sm" data-admin-list data-page-size="30"'
    );

echo admin_layout('Types d\'éléments', renderFlashMessage() . $content);
