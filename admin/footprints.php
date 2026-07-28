<?php
/**
 * Forme et passage des décors en plusieurs morceaux (admin → Cartes).
 *
 * Un fort, une pyramide, un géant sont posés en morceaux de 50 px, et rien
 * dans les données ne disait lesquels vont ensemble ni lesquels barrent le
 * chemin. On sait deviner la forme de deux manières — un exemplaire complet
 * posé sur la carte, ou l'image d'ensemble du décor — mais elles se
 * contredisent : celle de `geant_petrifie` annonce deux cases quand quatre
 * morceaux existent et que la carte en montre une figure de 3×3 trouée.
 *
 * Cette page est l'endroit où un humain tranche, en VOYANT le décor. Chaque
 * famille montre sa figure recomposée à l'échelle de la carte ; on clique une
 * case pour dire si elle barre le chemin, on fait glisser un morceau pour
 * corriger la figure. Ce qu'on enregistre l'emporte ensuite sur la carte et
 * sur l'image.
 *
 * Le dessin de la grille appartient au JavaScript, qui doit de toute façon la
 * redessiner à chaque geste : la faire aussi en PHP donnerait deux dessins à
 * garder d'accord. Sans JavaScript, la carte de la famille montre les morceaux
 * — on ne peut simplement pas les déplacer.
 *
 * Les mutations POSTent vers footprints-save.php (CSRF, PRG).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\Map\EntityTypeFootprintService;
use App\Service\Map\SceneryFootprintDeriver;

/**
 * D'où vient la forme, dit en français plutôt qu'en jargon.
 *
 * @return array{0: string, 1: string, 2: string} libellé, classe, explication
 */
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

$csrfToken = (new CsrfProtectionService())->generateToken();

$service = new EntityTypeFootprintService();
$deriver = new SceneryFootprintDeriver();

$catalogue = $service->catalogue();
$onDisk = $deriver->piecesOnDisk();

/* Les familles à montrer : celles qui ont plusieurs morceaux sur le disque —
 * un décor d'une seule case n'a pas de figure à régler — plus celles que la
 * carte connaît sans que le disque les porte, qui sont justement les plus
 * suspectes. */
$families = [];

foreach ($onDisk as $family => $pieces) {
    if (count($pieces) > 1) {
        $families[(string) $family] = $pieces;
    }
}

foreach (array_keys($catalogue) as $family) {
    $families[(string) $family] ??= $onDisk[$family] ?? [];
}

/* Ce qui n'est pas réglé passe devant : c'est le travail qui reste. */
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

        /* Sans figure connue on range les morceaux en carré, dans l'ordre de
         * lecture : on montre qu'ils vont ensemble, sans prétendre savoir
         * comment. C'est un point de départ qu'un humain corrige en quelques
         * gestes.
         *
         * En carré et non sur une ligne : douze morceaux alignés faisaient une
         * figure de six cents pixels de large, qui débordait sur la carte
         * voisine et rendait les cases inaccessibles. Un carré est de surcroît
         * la meilleure hypothèse — c'est la disposition qu'emploie l'image
         * d'ensemble d'un décor. */
        $offsets = $footprint['offsets'] ?? [];

        if ($offsets === []) {
            $columns = max(1, (int) ceil(sqrt(max(count($pieces), 1))));
            $rank = 0;

            foreach (array_keys($pieces) as $piece) {
                $offsets[$piece] = [$rank % $columns, -intdiv($rank, $columns)];
                $rank++;
            }
        }

        $blocked = [];

        foreach ($footprint['roles'] ?? [] as $piece => $role) {
            if ($role === 'block') {
                $blocked[] = (int) $piece;
            }
        }

        $editor = [
            'family'  => $name,
            'w'       => (int) ($footprint['w'] ?? max(count($pieces), 1)),
            'h'       => (int) ($footprint['h'] ?? 1),
            'pieces'  => $pieces,
            'offsets' => $offsets,
            'blocked' => $blocked,
        ];

        $editorJson = (string) json_encode($editor, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
                <?php /* La figure ne voyage qu'une fois : l'éditeur lit ce champ,
                         le réécrit à chaque geste, et c'est lui qui part au POST. */ ?>
                <input type="hidden" name="figure" class="fp-figure" value="<?= e($editorJson) ?>" />

                <div class="fp-board">
                    <?php /* Repli sans JavaScript : les morceaux, sans les gestes. */ ?>
                    <?php foreach (array_keys($offsets) as $piece): ?>
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
