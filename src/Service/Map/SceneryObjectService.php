<?php

namespace App\Service\Map;

use App\Entity\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * Poser et retirer un décor multi-cases D'UN SEUL GESTE.
 *
 * L'éditeur travaille aujourd'hui case par case. Poser un fort demande de
 * placer ses quatorze morceaux à la main ; en effacer un n'en retire qu'un,
 * et laisse treize orphelins derrière. C'est ainsi qu'on a fabriqué la
 * trentaine de fragments incomplets que la carte porte.
 *
 * Ce service donne à l'éditeur les deux gestes qui manquaient. Il s'appuie
 * sur les découpes dérivées de la carte (SceneryFootprintDeriver) et travaille
 * sur `map_foregrounds` tel quel : il n'attend pas que les décors soient
 * devenus des entités.
 *
 * # Poser
 *
 * L'animateur choisit un morceau dans la palette — n'importe lequel — et
 * clique. La figure entière se pose, alignée de sorte que **le morceau choisi
 * tombe sur la case cliquée**. C'est le geste qu'il fait déjà ; seul le
 * résultat change.
 *
 * # Effacer
 *
 * Cliquer sur n'importe quelle case d'un objet le retire en entier. Retirer
 * la tête d'un géant et lui laisser les pieds n'a jamais été un geste voulu.
 */
final class SceneryObjectService
{
    private Connection $conn;
    private SceneryFootprintDeriver $deriver;

    public function __construct(?Connection $conn = null, ?SceneryFootprintDeriver $deriver = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
        $this->deriver = $deriver ?? new SceneryFootprintDeriver($this->conn);
    }

    /**
     * Les cases d'un décor à poser, pour un morceau choisi et une case visée.
     *
     * Rend une liste `nom => (x, y)` prête à écrire. Vide si la famille n'a
     * pas de découpe connue : un décor d'une seule case se pose comme avant,
     * et une famille indérivable ne doit surtout pas être devinée.
     *
     * @return array<string, array{0:int,1:int}> nom du morceau => (x, y)
     */
    public function cellsToPlace(string $pickedName, int $x, int $y): array
    {
        [$family, $pickedPiece] = SceneryFootprintDeriver::splitPiece($pickedName);

        $footprint = $this->deriver->catalogue()[$family] ?? null;

        if ($footprint === null || !isset($footprint['offsets'][$pickedPiece])) {
            return [];
        }

        /* Les décalages sont relatifs au PREMIER morceau ; on les ramène au
         * morceau choisi, pour qu'il tombe là où l'animateur a cliqué. */
        [$px, $py] = $footprint['offsets'][$pickedPiece];

        $cells = [];

        foreach ($footprint['offsets'] as $piece => [$dx, $dy]) {
            $cells[$this->pieceName($pickedName, $family, $piece)] = [
                $x + $dx - $px,
                $y + $dy - $py,
            ];
        }

        return $cells;
    }

    /**
     * Les identifiants de case de l'objet auquel appartient une case donnée.
     *
     * L'objet est la composante connexe qui contient la case, **restreinte à
     * un seul exemplaire** : deux décors collés sont adjacents, et les
     * confondre ferait disparaître le voisin. La restriction se fait en
     * s'arrêtant au premier morceau déjà rencontré — c'est le même critère
     * d'unicité qui sert à compter les exemplaires.
     *
     * Rend au minimum la case demandée : effacer ne doit jamais ne rien faire.
     *
     * @return list<int>
     */
    public function objectCellsAt(int $coordsId, string $name): array
    {
        [$family, ] = SceneryFootprintDeriver::splitPiece($name);

        $origin = $this->conn->fetchAssociative(
            'SELECT x, y, z, plan FROM coords WHERE id = ?',
            [$coordsId]
        );

        if ($origin === false) {
            return [$coordsId];
        }

        /* Toutes les cases de la famille sur ce plan et ce niveau : l'objet
         * ne s'étend pas au-delà. */
        $rows = $this->conn->fetchAllAssociative(
            "SELECT f.name, f.coords_id, c.x, c.y
               FROM map_foregrounds f
               JOIN coords c ON c.id = f.coords_id
              WHERE c.plan = ? AND c.z = ?",
            [$origin['plan'], (int) $origin['z']]
        );

        $byKey = [];

        foreach ($rows as $row) {
            [$rowFamily, $piece] = SceneryFootprintDeriver::splitPiece((string) $row['name']);

            if ($rowFamily !== $family) {
                continue;
            }

            $cell = [
                'plan'      => $origin['plan'],
                'z'         => (int) $origin['z'],
                'x'         => (int) $row['x'],
                'y'         => (int) $row['y'],
                'piece'     => $piece,
                'coords_id' => (int) $row['coords_id'],
            ];

            $byKey[TouchingCells::key($cell)] = $cell;
        }

        $start = TouchingCells::key($origin);

        if (!isset($byKey[$start])) {
            return [$coordsId];
        }

        /* Le critère d'arrêt : un morceau dont l'indice est DÉJÀ dans la
         * composante appartient à l'exemplaire voisin, pas à celui-ci. Deux
         * décors collés sont adjacents ; sans cette règle, retirer l'un
         * emporterait l'autre. */
        $component = TouchingCells::groupAround(
            $byKey,
            $start,
            static function (array $candidate, array $taken): bool {
                foreach ($taken as $cell) {
                    if ($cell['piece'] === $candidate['piece']) {
                        return false;
                    }
                }

                return true;
            }
        );

        return array_values(array_map(
            static fn (array $cell): int => (int) $cell['coords_id'],
            $component
        ));
    }

    /**
     * L'état d'un objet de décor vu depuis l'une de ses cases.
     *
     * C'est ce que l'éditeur montre : de quelle figure il s'agit, ce qui est
     * posé, et ce qui MANQUE. Sur la carte de production, 38 exemplaires sont
     * incomplets — 21 géants sans leurs pieds — et rien ne le disait à
     * l'animateur qui passait dessus.
     *
     * @return array{
     *     family: string, w: int, h: int, cells: int, holed: bool,
     *     present: list<int>, missing: array<int, array{0:int,1:int}>,
     *     coords_ids: list<int>
     * }|null null quand la case ne porte pas de décor à découpe connue
     */
    public function inspect(int $coordsId, string $name): ?array
    {
        [$family, ] = SceneryFootprintDeriver::splitPiece($name);

        $footprint = $this->deriver->catalogue()[$family] ?? null;

        if ($footprint === null) {
            return null;
        }

        $coordsIds = $this->objectCellsAt($coordsId, $name);

        if ($coordsIds === []) {
            return null;
        }

        $in = implode(',', array_map('intval', $coordsIds));

        $present = [];
        $anchorPos = null;

        foreach ($this->conn->fetchAllAssociative(
            "SELECT f.name, c.x, c.y FROM map_foregrounds f
               JOIN coords c ON c.id = f.coords_id
              WHERE f.coords_id IN ({$in})"
        ) as $row) {
            [$rowFamily, $piece] = SceneryFootprintDeriver::splitPiece((string) $row['name']);

            if ($rowFamily !== $family) {
                continue;
            }

            $present[$piece] = true;

            /* La position du premier morceau POSÉ sert de repère pour situer
             * les manquants : la figure est décrite en décalages relatifs. */
            if ($anchorPos === null || $piece < $anchorPos['piece']) {
                $anchorPos = ['piece' => $piece, 'x' => (int) $row['x'], 'y' => (int) $row['y']];
            }
        }

        $missing = [];

        if ($anchorPos !== null && isset($footprint['offsets'][$anchorPos['piece']])) {
            [$ax, $ay] = $footprint['offsets'][$anchorPos['piece']];

            foreach ($footprint['offsets'] as $piece => [$dx, $dy]) {
                if (!isset($present[$piece])) {
                    $missing[$piece] = [
                        $anchorPos['x'] + $dx - $ax,
                        $anchorPos['y'] + $dy - $ay,
                    ];
                }
            }
        }

        ksort($present);
        ksort($missing);

        return [
            'family'     => $family,
            'w'          => $footprint['w'],
            'h'          => $footprint['h'],
            'cells'      => $footprint['cells'],
            'holed'      => $footprint['holed'],
            'present'    => array_keys($present),
            'missing'    => $missing,
            'coords_ids' => $coordsIds,
        ];
    }

    /**
     * Repose les morceaux manquants d'un objet incomplet.
     *
     * C'est le geste qui manque aux animateurs face aux 38 exemplaires
     * tronqués : la figure complète fait foi, autant pouvoir la compléter
     * d'un clic plutôt que de replacer les morceaux un à un.
     *
     * @return int nombre de morceaux reposés
     */
    public function complete(int $coordsId, string $name): int
    {
        $state = $this->inspect($coordsId, $name);

        if ($state === null || $state['missing'] === []) {
            return 0;
        }

        $origin = $this->conn->fetchAssociative(
            'SELECT z, plan FROM coords WHERE id = ?',
            [$coordsId]
        );

        if ($origin === false) {
            return 0;
        }

        $placed = 0;

        foreach ($state['missing'] as $piece => [$x, $y]) {
            $pieceCoordsId = (int) \Classes\View::get_coords_id((object) [
                'x' => $x, 'y' => $y, 'z' => (int) $origin['z'], 'plan' => $origin['plan'],
            ]);

            $this->conn->executeStatement(
                'INSERT INTO map_foregrounds (name, coords_id) VALUES (?, ?)',
                [$this->pieceName($name, $state['family'], $piece), $pieceCoordsId]
            );

            $placed++;
        }

        return $placed;
    }

    /**
     * Le nom d'un morceau, dans la convention de celui que l'animateur a pris.
     *
     * Trois conventions coexistent — `-NN`, `_NN`, et le chiffre collé — et
     * le catalogue ne les uniformise pas : les fichiers d'images, eux, sont
     * nommés comme ils sont. On recopie donc la forme du morceau choisi.
     */
    private function pieceName(string $pickedName, string $family, int $piece): string
    {
        $separator = substr($pickedName, strlen($family), 1);

        if ($separator === '-' || $separator === '_') {
            return $family . $separator . sprintf('%02d', $piece);
        }

        return $family . $piece;
    }
}
