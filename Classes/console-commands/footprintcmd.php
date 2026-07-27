<?php
use Classes\Command;
use Classes\Argument;
use App\Service\Map\SceneryFootprintDeriver;

/**
 * Les découpes des décors multi-cases, lues sur la carte.
 *
 * Ne modifie RIEN. Elle sert à regarder avant d'écrire : le catalogue que la
 * conversion posera, et la liste des exemplaires incomplets qu'il faudra
 * reprendre à la main.
 */
class FootprintCmd extends Command
{
    public function __construct()
    {
        parent::__construct('footprint', [new Argument('quoi', true)]);
        parent::setDescription(<<<EOT
Découpes des décors multi-cases, dérivées de la carte (lecture seule).
Exemple:
> footprint             (le catalogue : taille, cases, exemplaires)
> footprint tronques    (les exemplaires incomplets, à reprendre)
> footprint <famille>   (le détail d'une famille, morceau par morceau)
EOT);
    }

    public function execute(array $argumentValues): string
    {
        $deriver = new SceneryFootprintDeriver();
        $what = trim((string) ($argumentValues[0] ?? ''));
        $catalogue = $deriver->derive();

        if ($what !== '' && $what !== 'tronques' && isset($catalogue[$what])) {
            return $this->detail($what, $catalogue[$what]);
        }

        if ($what === 'tronques') {
            return $this->truncated($catalogue);
        }

        return $this->summary($catalogue, $deriver->undecidable());
    }

    /**
     * @param array<string, array{w:int,h:int,cells:int,holed:bool,instances:int,truncated:int,offsets:array<int,array{0:int,1:int}>}> $catalogue
     * @param array<string, array{pieces:int,components:int}> $undecidable
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
        $lines[] = sprintf('%d exemplaire(s) tronqué(s) — voir : footprint tronques', $truncated);

        if ($undecidable !== []) {
            $lines[] = '';
            $lines[] = 'Familles SANS exemplaire complet — découpe indérivable, à trancher :';
            foreach ($undecidable as $family => $u) {
                $lines[] = sprintf('  %-28s %d morceaux, %d composantes', $family, $u['pieces'], $u['components']);
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
