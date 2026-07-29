<?php
/**
 * Element types (admin → Éléments → Types): what the Tiled brush can lay on
 * a cell.
 *
 * An element type has no table of its own. It is the meeting of two things,
 * and this page exists to show where they fail to meet:
 *
 * - an IMAGE in `img/elements/`, which is what the Tiled palette offers, so
 *   it decides what can be painted;
 * - an EFFECT OF THE SAME NAME, which decides what it does — stepping on the
 *   cell applies it (`Player::go` → `add_effect`).
 *
 * That name match is the coupling to break: an element type will get a row of
 * its own, naming the effect it applies rather than being it. Two things
 * follow. The pairing is shown as a LINK, not as an identity, so this page
 * keeps its meaning afterwards — only its source changes. And the page is
 * the inventory the future table will be seeded from: what is paintable, and
 * what each one currently does.
 */


require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\EffectService;
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
$effectService = new EffectService();

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
    $effect = $effectService->getEffectByName($name);
    $count = $placed[$name] ?? 0;
    $image = $images[$name] ?? null;

    $thumb = $image !== null
        ? '<img src="/' . e($image) . '" height="28" loading="lazy" alt=""'
            . ' style="image-rendering:pixelated;background:#e7ded0;border:1px solid #ddd;">'
        : '<span class="badge badge-warning" title="Posé mais aucune image dans img/elements/ :'
            . ' la case ne dessine rien">sans image</span>';

    $does = $effect !== null
        ? '<a href="/admin/effects.php?action=edit&amp;name=' . e(urlencode($name)) . '">'
            . e($effect->getLabel() !== '' ? $effect->getLabel() : $name) . '</a>'
        : '<span class="badge badge-warning" title="Se peint, mais marcher dessus n\'applique rien">'
            . 'aucun effet</span>';

    $rows[] = '<tr>'
        . '<td>' . $thumb . '</td>'
        . '<td><code style="display:inline">' . e($name) . '</code></td>'
        . '<td>' . $does . '</td>'
        . '<td>' . ($count > 0
            ? '<a href="/admin/map-elements.php">' . $count . '</a>'
            : '<span class="text-muted">0</span>') . '</td>'
        . '</tr>';
}

$inert = count(array_filter(
    $names,
    static fn (string $name): bool => $effectService->getEffectByName($name) === null
));

$content = '<div class="d-flex justify-content-between align-items-center mb-3">'
    . '<h1 class="mb-0">Types d\'éléments</h1></div>'
    . '<p class="text-muted">Ce que le pinceau de Tiled peut poser sur une case. '
    . 'L\'<strong>image</strong> de <code>img/elements/</code> décide de ce qu\'on peut peindre ; '
    . 'l\'<strong>effet du même nom</strong> décide de ce que ça fait — marcher sur la case l\'applique. '
    . 'Un type sans effet se peint et ne fait rien. '
    . '<em>Le lien par le nom est provisoire : un type d\'élément aura sa propre ligne, '
    . 'qui NOMMERA l\'effet appliqué au lieu de l\'être.</em>'
    . ($inert > 0
        ? ' <strong>' . $inert . ' type(s) sans effet</strong> sur cette carte.'
        : '')
    . '</p>'
    . renderTable(
        [
            '',
            'Nom',
            ['Effet appliqué', 'title="L\'effet du même nom, appliqué en marchant sur la case"'],
            ['Posés', 'title="Instances map_elements portant ce nom"'],
        ],
        $rows,
        'class="table table-striped table-sm" data-admin-list data-page-size="30"'
    );

echo admin_layout('Types d\'éléments', renderFlashMessage() . $content);
