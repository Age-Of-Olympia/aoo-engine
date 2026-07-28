<?php
/**
 * Shape and passability of multi-piece scenery (admin → Cartes).
 *
 * Each family shows its figure recomposed at map scale; a click marks a cell
 * as blocking, a drag moves a piece. What is saved here overrides the shape
 * derived from the map and from the whole-object images.
 *
 * The grid is drawn by JavaScript, which has to redraw it on every gesture
 * anyway; without it the card still shows the pieces, just not editable.
 * Mutations POST to footprints-save.php (CSRF, PRG).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\Map\EntityTypeFootprintService;
use App\Service\Map\Footprint;
use App\Service\Map\SceneryFootprintDeriver;

/** @return array{0: string, 1: string, 2: string} label, css class, tooltip */
function footprint_origin(string $source): array
{
    return match ($source) {
        'declared' => [
            'réglé ici',
            'fp-badge--set',
            'Quelqu\'un a réglé cette figure à la main : elle fait autorité.',
        ],
        'map' => [
            'deviné d\'après la carte',
            'fp-badge--guessed',
            'Forme relevée sur un exemplaire complet posé sur la carte. Le passage, lui, n\'est pas réglé.',
        ],
        'image' => [
            'deviné d\'après l\'image',
            'fp-badge--guessed',
            'Forme relevée sur l\'image d\'ensemble du décor. Le passage, lui, n\'est pas réglé.',
        ],
        default => [
            'forme inconnue',
            'fp-badge--unknown',
            'Ni la carte ni l\'image ne savent dire la figure : les morceaux sont alignés au hasard, à corriger.',
        ],
    };
}

/**
 * Fallback figure when no source knows the shape: pieces laid in a square,
 * in reading order — the layout whole-object images already use, and narrow
 * enough not to overflow its card.
 *
 * @param list<int> $pieces
 */
function footprint_in_a_square(array $pieces): Footprint
{
    if ($pieces === []) {
        return Footprint::fromOffsets([0 => [0, 0]]);
    }

    $columns = max(1, (int) ceil(sqrt(count($pieces))));
    $offsets = [];

    foreach (array_values($pieces) as $rank => $piece) {
        $offsets[$piece] = [$rank % $columns, -intdiv($rank, $columns)];
    }

    return Footprint::fromOffsets($offsets);
}

$csrfToken = (new CsrfProtectionService())->generateToken();

$service = new EntityTypeFootprintService();
$deriver = new SceneryFootprintDeriver();

$catalogue = $service->catalogue();
$onDisk = $deriver->piecesOnDisk();

/* Families worth showing: several pieces on disk, plus those the map knows
 * about without the disk carrying them. */
$families = [];

foreach ($onDisk as $family => $pieces) {
    if (count($pieces) > 1) {
        $families[(string) $family] = $pieces;
    }
}

foreach (array_keys($catalogue) as $family) {
    $families[(string) $family] ??= $onDisk[$family] ?? [];
}

/* Unsettled families first: that is the work left to do. */
uksort($families, static function (string $a, string $b) use ($service): int {
    $settled = static fn(string $family): int => $service->sourceOf($family) === 'declared' ? 1 : 0;

    return [$settled($a), $a] <=> [$settled($b), $b];
});

$counts = ['all' => count($families), 'todo' => 0, 'set' => 0];

foreach (array_keys($families) as $family) {
    $counts[$service->sourceOf((string) $family) === 'declared' ? 'set' : 'todo']++;
}

ob_start();
?>

<div class="container">
    <h2 class="section-title">Décors en plusieurs morceaux</h2>

    <p class="text-content">
        Un décor plus grand qu'une case est posé en morceaux. Cette page dit
        <strong>quelles cases il occupe</strong> et <strong>lesquelles barrent le chemin</strong>.
        La forme est devinée quand c'est possible — d'après un exemplaire posé sur la carte, ou
        d'après l'image d'ensemble du décor — mais les deux se trompent parfois, et rien ne devine
        le passage. Ce qui est réglé ici l'emporte sur ce qui est deviné.
    </p>

    <p class="fp-note">
        <strong>Cliquez une case</strong> pour la faire barrer le chemin, ou le laisser libre.
        <strong>Faites glisser un morceau</strong> sur une case vide pour corriger la figure.
        Le passage sera appliqué quand les décors deviendront des entités du moteur ; d'ici là il
        est enregistré, sans effet en jeu.
    </p>

    <?= renderFlashMessage() ?>

    <div class="fp-toolbar">
        <input type="search" id="fp-search" class="form-select fp-search"
               placeholder="Chercher un décor…" aria-label="Chercher un décor" />

        <div class="fp-filters" role="group" aria-label="Filtrer les décors">
            <button type="button" class="btn btn-sm btn-secondary active" data-filter="all">
                Tous (<?= $counts['all'] ?>)
            </button>
            <button type="button" class="btn btn-sm btn-secondary" data-filter="todo">
                À régler (<?= $counts['todo'] ?>)
            </button>
            <button type="button" class="btn btn-sm btn-secondary" data-filter="set">
                Réglés (<?= $counts['set'] ?>)
            </button>
        </div>

        <p class="fp-legend">
            <span class="fp-legend--free">on peut passer</span>
            <span class="fp-legend--blocks">barre le chemin</span>
        </p>
    </div>

    <?php if ($families === []): ?>
        <p class="text-muted">Aucun décor en plusieurs morceaux sur ce déploiement.</p>
    <?php endif; ?>

    <div class="fp-grid">
    <?php foreach ($families as $family => $pieces): ?>
        <?php
        $name = (string) $family;
        $source = $service->sourceOf($name);
        [$originLabel, $originClass, $originHint] = footprint_origin($source);
        $footprint = $catalogue[$name] ?? null;

        $figure = $footprint ?? footprint_in_a_square(array_keys($pieces));

        $blocked = array_keys(array_filter(
            $figure->roles(),
            static fn(string $role): bool => $role === 'block'
        ));

        $editorJson = (string) json_encode([
            'family'  => $name,
            'w'       => $figure->width(),
            'h'       => $figure->height(),
            'pieces'  => $pieces,
            'offsets' => $figure->offsets(),
            'blocked' => $blocked,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        ?>
        <section class="fp-card" data-state="<?= $source === 'declared' ? 'set' : 'todo' ?>"
                 data-family="<?= e($name) ?>">
            <header class="fp-card__head">
                <code class="fp-card__name"><?= e($name) ?></code>
                <span class="fp-badge <?= $originClass ?>" title="<?= e($originHint) ?>"><?= e($originLabel) ?></span>
            </header>

            <form method="post" action="footprints-save.php" class="fp-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" />
                <input type="hidden" name="type" value="<?= e($name) ?>" />
                <?php /* The figure travels once: the editor reads this field,
                         rewrites it on every gesture, and it is what POSTs. */ ?>
                <input type="hidden" name="figure" class="fp-figure" value="<?= e($editorJson) ?>" />

                <div class="fp-board">
                    <?php /* No-JavaScript fallback: the pieces, without the gestures. */ ?>
                    <?php foreach (array_keys($figure->offsets()) as $piece): ?>
                        <?php if (isset($pieces[$piece])): ?>
                            <img class="fp-fallback" src="<?= e($pieces[$piece]) ?>" alt="" loading="lazy" />
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <p class="fp-summary">
                    <?= count($pieces) ?> morceau<?= count($pieces) > 1 ? 'x' : '' ?><?php
                    if ($pieces === []): ?> sur le disque — la carte seule en parle<?php endif; ?>
                </p>

                <div class="fp-actions">
                    <button type="submit" name="action" value="save" class="btn btn-sm btn-primary">
                        Enregistrer
                    </button>
                    <?php if ($source === 'declared'): ?>
                        <button type="submit" name="action" value="forget" class="btn btn-sm btn-secondary"
                                title="La forme sera de nouveau devinée d'après la carte ou l'image">
                            Revenir au calcul automatique
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </section>
    <?php endforeach; ?>
    </div>
</div>

<?php
$content = ob_get_clean();

echo admin_layout('Décors en plusieurs morceaux', $content, [
    'styles'  => ['/admin/css/footprints.css'],
    'scripts' => ['/admin/js/footprints.js'],
]);
