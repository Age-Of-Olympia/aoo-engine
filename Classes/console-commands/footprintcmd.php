<?php
use Classes\Command;
use Classes\Argument;
use App\Service\Map\EntityTypeFootprintService;
use App\Service\Map\SceneryArtAudit;
use App\Service\Map\SceneryFootprintDeriver;

/**
 * Cut-outs of multi-piece scenery, read off the map.
 *
 * Look before writing: the catalogue the conversion will lay down, and the
 * incomplete instances someone has to redo by hand. Two verbs write or judge,
 * because the table carrying these figures is on its way out — `freeze` puts
 * a shape out of its reach, `verify` says whether a family's whole-object
 * drawing really redraws its pieces.
 */
class FootprintCmd extends Command
{
    public function __construct()
    {
        parent::__construct('footprint', [new Argument('what', true), new Argument('option', true)]);
        parent::setDescription(<<<EOT
Découpes des décors multi-cases, dérivées de la carte.
Exemple:
> footprint             (le catalogue : taille, cases, exemplaires)
> footprint truncated   (les exemplaires incomplets, à reprendre)
> footprint <famille>   (le détail d'une famille, morceau par morceau)
> footprint verify      (l'image d'ensemble redit-elle les morceaux ?)
> footprint freeze      (ce que « freeze write » enregistrerait)
> footprint freeze write (met les formes devinées sur la carte à l'abri)
> footprint compose     (fabrique l'image entière que la carte dessinera)
EOT);
    }

    public function execute(array $argumentValues): string
    {
        $deriver = new SceneryFootprintDeriver();
        $what = trim((string) ($argumentValues[0] ?? ''));
        $option = trim((string) ($argumentValues[1] ?? ''));

        if ($what === 'verify') {
            return $this->verify();
        }

        if ($what === 'freeze') {
            return $this->freeze($option === 'write');
        }

        if ($what === 'compose') {
            return $this->compose();
        }

        $catalogue = $deriver->derive();

        if ($what !== '' && $what !== 'truncated' && isset($catalogue[$what])) {
            return $this->detail($what, $catalogue[$what]);
        }

        if ($what === 'truncated') {
            return $this->truncated($catalogue);
        }

        return $this->summary($catalogue, $deriver->undecidable());
    }

    /**
     * Does a family's whole-object drawing redraw its pieces?
     *
     * In pixels, not in dimensions: `triton_statue`'s measures exactly what
     * its figure announces, and is still its mirror.
     */
    private function verify(): string
    {
        $service = new EntityTypeFootprintService();
        $audit = new SceneryArtAudit();
        $pieces = (new SceneryFootprintDeriver())->piecesOnDisk();

        $verdicts = [];

        foreach ($service->catalogue() as $family => $footprint) {
            if ($footprint->isSingleCell()) {
                continue;
            }

            $result = $audit->audit('foregrounds', (string) $family, $footprint, $pieces[$family] ?? []);
            $verdicts[(string) $family] = $result;
        }

        $wrong = array_filter(
            $verdicts,
            static fn (array $v): bool => in_array(
                $v['verdict'],
                [SceneryArtAudit::MIRRORED, SceneryArtAudit::MISPLACED, SceneryArtAudit::WRONG_SIZE],
                true
            )
        );

        $lines = ['Image d\'ensemble contre morceaux posés — comparaison pixel à pixel :', ''];

        foreach ($verdicts as $family => $v) {
            $lines[] = sprintf('  %-28s %-11s %s', $family, $v['verdict'], $v['detail']);
        }

        $lines[] = '';

        if ($wrong === []) {
            $lines[] = 'Aucune image d\'ensemble ne contredit ses morceaux.';
        } else {
            $lines[] = sprintf(
                '%d famille(s) dont l\'image contredit les morceaux : %s.',
                count($wrong),
                implode(', ', array_keys($wrong))
            );
            $lines[] = 'Ces figures se composeront depuis leurs morceaux — l\'image ne fait pas foi.';
        }

        return implode('<br />', $lines);
    }

    /**
     * Builds the picture the board draws a figure with.
     *
     * Stitched from the family's own pieces, never from the artist's
     * whole-object drawing: two of those contradict their pieces. Done here
     * and not on a render, because composing globs a folder and stats every
     * piece — fine once, absurd on every board.
     */
    private function compose(): string
    {
        $service = new EntityTypeFootprintService();
        $sprites = new \App\Service\Map\CompositeSpriteService();
        $pieces = (new SceneryFootprintDeriver())->piecesOnDisk();

        $made = [];
        $missed = [];

        foreach ($service->catalogue() as $family => $footprint) {
            if ($footprint->isSingleCell()) {
                continue;
            }

            $image = $sprites->composedSprite('foregrounds', (string) $family, $footprint, $pieces[$family] ?? []);

            if ($image === null) {
                $missed[] = (string) $family;

                continue;
            }

            $made[(string) $family] = $image;
        }

        $lines = ['Images entières fabriquées depuis les morceaux :', ''];

        foreach ($made as $family => $image) {
            $lines[] = sprintf('  %-28s %s', $family, $image);
        }

        $lines[] = '';
        $lines[] = sprintf('%d figure(s) prête(s) à être dessinée d\'un bloc.', count($made));

        if ($missed !== []) {
            $lines[] = sprintf(
                'Sans morceaux sur le disque, donc toujours dessinée(s) pièce par pièce : %s.',
                implode(', ', $missed)
            );
        }

        return implode('<br />', $lines);
    }

    /**
     * Puts the shapes only the map knows out of its reach.
     *
     * A family whose shape is derived from the pieces standing on the board
     * loses it the day the scenery table goes. Freezing writes it to the
     * catalogue as read today — moving nothing: `declare()` writes the shape
     * alone, and it is the admin saves that re-lay the cells.
     */
    private function freeze(bool $write): string
    {
        $service = new EntityTypeFootprintService();
        $catalogue = $service->catalogue();

        $fromMap = [];

        foreach (array_keys($catalogue) as $family) {
            if ($service->sourceOf((string) $family) === 'map') {
                $fromMap[(string) $family] = $catalogue[$family];
            }
        }

        if ($fromMap === []) {
            return 'Aucune forme ne dépend plus de la carte : rien à figer.';
        }

        $lines = [$write
            ? 'Formes figées au catalogue :'
            : 'Formes qui ne tiennent qu\'à la carte (essai à blanc — « footprint freeze write » pour enregistrer) :'];
        $lines[] = '';

        foreach ($fromMap as $family => $footprint) {
            $lines[] = sprintf(
                '  %-28s %d×%d, %d case(s)%s',
                $family,
                $footprint->width(),
                $footprint->height(),
                $footprint->cells(),
                $footprint->isHoled() ? ' (trouée)' : ''
            );

            if ($write) {
                $service->declare(
                    (string) $family,
                    $footprint->width(),
                    $footprint->height(),
                    $footprint->offsets(),
                    $footprint->roles()
                );
            }
        }

        $lines[] = '';
        $lines[] = $write
            ? sprintf('%d forme(s) enregistrée(s) : la table du décor ne les porte plus.', count($fromMap))
            : sprintf('%d forme(s) à figer.', count($fromMap));

        return implode('<br />', $lines);
    }

    /**
     * @param array<string, array{w:int,h:int,cells:int,holed:bool,instances:int,truncated:int,offsets:array<int,array{0:int,1:int}>}> $catalogue
     * @param array<string, array{pieces:int,groups:int}> $undecidable
     */
    private function summary(array $catalogue, array $undecidable): string
    {
        $lines = [sprintf(
            '%-30s %4s %4s %6s %8s %9s',
            'famille',
            'l',
            'h',
            'cases',
            'exempl.',
            'tronqués'
        )];

        foreach ($catalogue as $family => $f) {
            $lines[] = sprintf(
                '%-30s %4d %4d %6d %8d %9s',
                $family . ($f['holed'] ? ' *' : ''),
                $f['w'],
                $f['h'],
                $f['cells'],
                $f['instances'],
                $f['truncated'] > 0 ? (string) $f['truncated'] : ''
            );
        }

        $holed = count(array_filter($catalogue, static fn (array $f): bool => $f['holed']));
        $truncated = array_sum(array_column($catalogue, 'truncated'));

        $lines[] = '';
        $lines[] = sprintf('%d découpe(s) dérivée(s), dont %d trouée(s) — marquées *', count($catalogue), $holed);
        $lines[] = sprintf('%d exemplaire(s) tronqué(s) — voir : footprint truncated', $truncated);

        if ($undecidable !== []) {
            $lines[] = '';
            $lines[] = 'Familles SANS exemplaire complet — découpe indérivable, à trancher :';
            foreach ($undecidable as $family => $u) {
                $lines[] = sprintf(
                    '  %-28s %d morceaux, %d groupe(s) posé(s)',
                    $family,
                    $u['pieces'],
                    $u['groups']
                );
            }
        }

        return implode('<br />', $lines);
    }

    /**
     * @param array<string, array{truncated:int,cells:int,instances:int}> $catalogue
     */
    private function truncated(array $catalogue): string
    {
        $incomplete = array_filter($catalogue, static fn (array $f): bool => $f['truncated'] > 0);

        if ($incomplete === []) {
            return 'Aucun exemplaire tronqué : toutes les figures posées sont complètes.';
        }

        uasort($incomplete, static fn (array $a, array $b): int => $b['truncated'] <=> $a['truncated']);

        $lines = ['Exemplaires incomplets — la figure complète fait foi, ceux-ci sont à reprendre :'];

        foreach ($incomplete as $family => $f) {
            $lines[] = sprintf(
                '  %-28s %d tronqué(s) sur %d exemplaire(s) — figure de %d cases',
                $family,
                $f['truncated'],
                $f['instances'],
                $f['cells']
            );
        }

        $lines[] = '';
        $lines[] = sprintf('Total : %d à reprendre.', array_sum(array_column($incomplete, 'truncated')));

        return implode('<br />', $lines);
    }

    /**
     * @param array{w:int,h:int,cells:int,holed:bool,instances:int,truncated:int,offsets:array<int,array{0:int,1:int}>} $f
     */
    private function detail(string $family, array $f): string
    {
        $lines = [sprintf(
            '%s — %d×%d, %d case(s)%s, %d exemplaire(s) dont %d tronqué(s)',
            $family,
            $f['w'],
            $f['h'],
            $f['cells'],
            $f['holed'] ? ' (figure trouée)' : '',
            $f['instances'],
            $f['truncated']
        )];

        $lines[] = 'Décalages, relatifs au premier morceau :';

        foreach ($f['offsets'] as $piece => [$dx, $dy]) {
            $lines[] = sprintf('  morceau %02d → dx=%+d dy=%+d', $piece, $dx, $dy);
        }

        return implode('<br />', $lines);
    }
}
