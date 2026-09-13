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

use App\Entity\Race;
use App\Factory\EntityManagerFactory;
use App\Service\BuildingService;
use App\Service\CsrfProtectionService;
use App\Service\Map\EntityTypeFootprintService;
use App\Service\Map\Footprint;
use App\Service\Map\SceneryObjectService;
use App\Service\Map\MapForegroundsRetirement;
use App\Service\Map\SceneryFootprintDeriver;
use App\View\Admin\TypeEditorFace;

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
$scenery = new SceneryObjectService();

$catalogue = $service->catalogue();
$onDisk = $deriver->piecesOnDisk();

/* Every type that can stand on the board, whatever its kind: the races
 * catalogue (characters, buildings, plants…), the scenery families cut in
 * pieces on disk, and whatever already has a declared cut-out. */
$kinds = [];
$labels = [];

foreach (EntityManagerFactory::getEntityManager()->getRepository(Race::class)->findAll() as $race) {
    $kinds[$race->getName()] = TypeEditorFace::of($race)->key;
    $labels[$race->getName()] = $race->getLabel();
}

$families = [];

foreach ($onDisk as $family => $pieces) {
    if (count($pieces) > 1) {
        $families[(string) $family] = $pieces;
    }
}

foreach (array_merge(array_keys($catalogue), array_keys($kinds)) as $family) {
    $families[(string) $family] ??= $onDisk[$family] ?? [];
}

$kindOf = static fn(string $family): string => $kinds[$family] ?? TypeEditorFace::SCENERY;

$faces = TypeEditorFace::all();

/* One type's own page (`?type=`), or one kind's (`?kind=`). */
$onlyType = trim((string) ($_GET['type'] ?? ''));
$onlyKind = isset($faces[(string) ($_GET['kind'] ?? '')]) ? (string) $_GET['kind'] : '';
$back = $onlyType !== '' ? '?type=' . urlencode($onlyType) : ($onlyKind !== '' ? '?kind=' . $onlyKind : '');

if ($onlyType !== '') {
    $families = [$onlyType => $families[$onlyType] ?? []];
} elseif ($onlyKind !== '') {
    $families = array_filter($families, static fn(string $f): bool => $kindOf($f) === $onlyKind, ARRAY_FILTER_USE_KEY);
}

/* Unsettled families first: that is the work left to do. */
uksort($families, static function (string $a, string $b) use ($service): int {
    $settled = static fn(string $family): int => $service->sourceOf($family) === 'declared' ? 1 : 0;

    return [$settled($a), $a] <=> [$settled($b), $b];
});

/* The foreground leftovers only concern scenery: the whole list and the
 * Décors page carry them, a scenery type's page only its own figures, any
 * other kind nothing. */
$aboutScenery = $onlyType !== ''
    ? $kindOf($onlyType) === TypeEditorFace::SCENERY
    : ($onlyKind === '' || $onlyKind === TypeEditorFace::SCENERY);

$retirement = $aboutScenery && $onlyType === '' ? (new MapForegroundsRetirement())->status() : null;
$halfErased = $aboutScenery ? $scenery->halfErased() : [];

if ($onlyType !== '') {
    $halfErased = array_values(array_filter(
        $halfErased,
        static fn(array $figure): bool => $figure['family'] === $onlyType
    ));
}

$counts = ['all' => count($families), 'todo' => 0, 'set' => 0];

foreach (array_keys($families) as $family) {
    $counts[$service->sourceOf((string) $family) === 'declared' ? 'set' : 'todo']++;
}

ob_start();
?>

<div class="container">
    <h2 class="section-title">Emprises<?= $onlyType !== '' ? ' — ' . e($labels[$onlyType] ?? $onlyType) : ($onlyKind !== '' ? ' — ' . e($faces[$onlyKind]->title) : '') ?></h2>

    <p class="text-content">
        Tout ce qui se tient sur le plateau — personnage, bâtiment, décor, plante — peut occuper
        plusieurs cases. Cette page dit <strong>quelles cases un type occupe</strong> et
        <strong>lesquelles barrent le chemin</strong>. Son image du plateau couvre toute l'emprise
        (50 px par case) ; un décor en morceaux montre ses morceaux. La forme est devinée quand
        c'est possible — d'après un exemplaire posé sur la carte, ou d'après l'image d'ensemble —
        mais ce qui est réglé ici l'emporte.
    </p>

    <p class="fp-note">
        <strong>Cliquez une case vide</strong> pour l'ajouter à la figure,
        <strong>une case pleine</strong> pour la faire barrer le chemin ou le laisser libre,
        <strong>clic droit</strong> pour la retirer.
        <strong>Faites glisser un morceau</strong> sur une case vide pour corriger la figure.
        Enregistrer reprend les exemplaires déjà posés.
    </p>

    <?= renderFlashMessage() ?>

    <?php if ($retirement !== null && $retirement['droppable']): ?>
        <div class="alert alert-success">
            <strong>La table <code>map_foregrounds</code> peut être supprimée.</strong>
            Plus rien n'en dépend : le décor est dessiné depuis les entités, et toutes les formes
            sont réglées ici. Elle porte encore <?= $retirement['rows'] ?> ligne<?= $retirement['rows'] > 1 ? 's' : '' ?>,
            qui ne servent plus à rien.
        </div>
    <?php elseif ($retirement !== null): ?>
        <div class="alert alert-warning">
            <strong>La table <code>map_foregrounds</code> sert encore — ne la supprimez pas.</strong>
            <ul class="fp-blockers">
                <?php foreach ($retirement['blockers'] as $blocker): ?>
                    <li><?= htmlspecialchars($blocker, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($retirement['shapesFromMap'] !== []): ?>
                <p class="fp-blockers__hint">
                    Régler ces formes ici les met à l'abri : <?=
                        htmlspecialchars(implode(', ', array_slice($retirement['shapesFromMap'], 0, 12)), ENT_QUOTES, 'UTF-8')
                    ?><?= count($retirement['shapesFromMap']) > 12 ? '…' : '' ?>.
                </p>
            <?php endif; ?>
            <p class="fp-blockers__hint">
                Ce message deviendra vert de lui-même quand plus rien n'en dépendra.
            </p>
        </div>
    <?php endif; ?>

    <?php if ($halfErased !== []): ?>
        <section class="alert alert-warning fp-half-erased">
            <strong><?= count($halfErased) ?> décor<?= count($halfErased) > 1 ? 's' : '' ?> à moitié effacé<?= count($halfErased) > 1 ? 's' : '' ?>.</strong>
            Ces décors tiennent toutes leurs cases en jeu, où ils sont dessinés en entier, mais
            l'éditeur n'en montre plus qu'une partie — parfois un seul morceau transparent, que
            personne ne peut viser pour l'effacer. Cochez ceux à retirer. Pour en compléter un à
            la place, passez par l'info de case dans Tiled.
            <form method="post" action="footprints-save.php" class="fp-half-erased__form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" />
                <input type="hidden" name="action" value="remove" />
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th><input type="checkbox" data-check-all aria-label="Tout cocher" /></th>
                                <th>Décor</th>
                                <th>Plan</th>
                                <th>Case</th>
                                <th>Cases tenues</th>
                                <th>Morceaux visibles</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($halfErased as $figure): ?>
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="<?= $figure['id'] ?>" /></td>
                                <td><code><?= e($figure['family']) ?></code> <small class="text-muted">#<?= $figure['id'] ?></small></td>
                                <td><?= e($figure['plan']) ?></td>
                                <td><?= $figure['x'] ?>, <?= $figure['y'] ?>, <?= $figure['z'] ?></td>
                                <td><?= $figure['cells'] ?></td>
                                <td><?= $figure['pieces'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn btn-sm btn-danger">Retirer les décors cochés</button>
            </form>
        </section>
    <?php endif; ?>

    <div class="fp-toolbar">
        <input type="search" id="fp-search" class="form-select fp-search"
               placeholder="Chercher un type…" aria-label="Chercher un type" />

        <div class="fp-filters" role="group" aria-label="Filtrer par état">
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

        <?php if ($onlyType === '' && $onlyKind === ''): ?>
        <div class="fp-filters" role="group" aria-label="Filtrer par sorte">
            <?php foreach ($faces as $face): ?>
                <a class="btn btn-sm btn-outline-secondary" href="?kind=<?= e($face->key) ?>"><?= e($face->title) ?></a>
            <?php endforeach; ?>
        </div>
        <?php elseif ($onlyType === ''): ?>
        <a class="btn btn-sm btn-outline-secondary" href="footprints.php">Toutes les sortes</a>
        <?php endif; ?>

        <p class="fp-legend">
            <span class="fp-legend--free">on peut passer</span>
            <span class="fp-legend--blocks">barre le chemin</span>
        </p>
    </div>

    <?php if ($families === []): ?>
        <p class="text-muted">Aucun type à afficher.</p>
    <?php endif; ?>

    <div class="fp-grid">
    <?php foreach ($families as $family => $pieces): ?>
        <?php
        $name = (string) $family;
        $source = $service->sourceOf($name);
        [$originLabel, $originClass, $originHint] = footprint_origin($source);
        $footprint = $catalogue[$name] ?? null;

        $figure = $footprint ?? footprint_in_a_square(array_keys($pieces));

        /* What a red cell defers to. Without a type, nothing marked here has
         * any effect at all — which is the one thing a cut-out cannot say. */
        $settings = $scenery->typeSettings($name);

        $blocked = array_keys(array_filter(
            $figure->roles(),
            static fn(string $role): bool => $role === 'block'
        ));

        $kind = $kindOf($name);

        /* A type without pieces shows its board sprite, stretched over the
         * box and sliced per cell by the editor — the very image the board
         * spans (View::structureSprite / the race's first avatar). */
        $sheet = $pieces === [] ? BuildingService::resolveAvatar($name) : '';

        $editorJson = (string) json_encode([
            'family'  => $name,
            'w'       => $figure->width(),
            'h'       => $figure->height(),
            'pieces'  => $pieces,
            'sheet'   => $sheet === '' ? null : '/' . $sheet,
            'offsets' => $figure->offsets(),
            'blocked' => $blocked,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        ?>
        <section class="fp-card" data-state="<?= $source === 'declared' ? 'set' : 'todo' ?>"
                 data-family="<?= e($name) ?>" data-kind="<?= e($kind) ?>">
            <header class="fp-card__head">
                <span>
                    <code class="fp-card__name"><?= e($name) ?></code>
                    <small class="text-muted"><?= e($faces[$kind]->singular) ?></small>
                </span>
                <span class="fp-badge <?= $originClass ?>" title="<?= e($originHint) ?>"><?= e($originLabel) ?></span>
            </header>

            <?php if ($settings === null): ?>
                <p class="fp-warn">
                    Pas encore de type au catalogue : les cases marquées ici resteront sans effet
                    jusqu'à l'enregistrement, qui le créera.
                </p>
            <?php endif; ?>

            <form method="post" action="footprints-save.php" class="fp-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" />
                <input type="hidden" name="type" value="<?= e($name) ?>" />
                <?php /* The figure travels once: the editor reads this field,
                         rewrites it on every gesture, and it is what POSTs. */ ?>
                <input type="hidden" name="back" value="<?= e($back) ?>" />
                <input type="hidden" name="figure" class="fp-figure" value="<?= e($editorJson) ?>" />

                <div class="fp-board">
                    <?php /* No-JavaScript fallback: the pieces, without the gestures. */ ?>
                    <?php foreach (array_keys($figure->offsets()) as $piece): ?>
                        <?php if (isset($pieces[$piece])): ?>
                            <img class="fp-fallback" src="<?= e($pieces[$piece]) ?>" alt="" loading="lazy" />
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($sheet !== ''): ?>
                        <img class="fp-fallback" src="/<?= e($sheet) ?>" alt="" loading="lazy" />
                    <?php endif; ?>
                </div>

                <p class="fp-summary">
                    <?= $figure->cells() ?> case<?= $figure->cells() > 1 ? 's' : '' ?><?php
                    if ($pieces === [] && $sheet === ''): ?> — aucune image<?php endif; ?>
                </p>

                <?php /* The two dials a `block` cell defers to. Marking a cell
                         says WHICH cells are solid; these say what solid means.
                         A character is never solid that way: no dials. */ ?>
                <?php if ($kind !== TypeEditorFace::CHARACTER): ?>
                <fieldset class="fp-dials">
                    <legend>Ce qu'une case rouge fait</legend>
                    <input type="hidden" name="has_dials" value="1" />

                    <label>
                        <input type="checkbox" name="blocks_passage" value="1"
                               <?= ($settings['blocks_passage'] ?? true) ? 'checked' : '' ?> />
                        barre le chemin
                    </label>

                    <label>
                        <input type="checkbox" name="blocks_projectiles" value="1"
                               <?= ($settings['blocks_projectiles'] ?? true) ? 'checked' : '' ?> />
                        arrête les tirs
                        <small>— décocher pour une arche : on ne passe pas, la flèche si</small>
                    </label>
                </fieldset>
                <?php endif; ?>

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

echo admin_layout('Emprises', $content, [
    'styles'  => ['/admin/css/footprints.css'],
    'scripts' => ['/admin/js/footprints.js'],
]);
