<?php

namespace App\Action\Combat;

/**
 * Géométrie de la ligne de tir — SOURCE UNIQUE, partagée par le calcul
 * (DistanceComputeCondition) et par l'aide à l'écran (observe.php et
 * api/map/line_of_fire.php), toutes deux via
 * BuildingService::lineOfFireReport.
 *
 * # Pourquoi deux traversées
 *
 * Le tracé de Bresenham n'est pas symétrique : quand la ligne idéale passe
 * près d'un coin, l'arrondi dépend du sens de parcours. Mesuré sur un rayon
 * de douze cases, **36 % des trajets** donnent un tracé différent à l'aller
 * et au retour. Un obstacle posé sur une case divergente arrêtait donc le
 * tir dans un sens et le laissait passer dans l'autre : de deux tireurs
 * face à face, l'un touchait et l'autre non.
 *
 * La règle est désormais : **si un tir passe dans un sens, il passe dans
 * l'autre.** Une case n'arrête le tir que si elle est traversée DANS LES
 * DEUX SENS.
 *
 * # Deux jeux de cases, deux usages
 *
 * - `tilesBetween()` rend le CORRIDOR : l'union ordonnée des deux
 *   traversées. C'est ce qu'on dessine, et c'est contigu — l'intersection
 *   seule aurait des trous dans un tiers des cas, et un tracé troué est
 *   illisible.
 * - `paths()` rend les DEUX TRAVERSÉES telles quelles. Un tir passe s'il
 *   existe une traversée LIBRE : c'est le tireur qui se faufile.
 *
 * Ce dernier point s'énonçait autrefois par case — « une case n'arrête que si
 * les deux tracés la traversent » — et cela ne composait pas. Chacune des
 * trois cases d'une base d'enclume est évitable par l'un des tracés, mais
 * aucun tracé ne les évite TOUTES : le tir passait donc à travers un mur de
 * trois cases de large. Pire, sur les trajets de pente exacte 1:2 les deux
 * tracés divergent à chaque pas, l'intersection est vide, et RIEN ne pouvait
 * jamais arrêter un tir.
 *
 * La question se pose donc par TRAJET et non par case : le tir passe si l'un
 * des deux tracés est libre de bout en bout.
 *
 * Tout reste symétrique : échanger les extrémités échange les deux tracés.
 */
final class LineOfFire
{
    /**
     * Le corridor du tir : union ordonnée des deux traversées, extrémités
     * exclues. Contigu. C'est le tracé à afficher, et l'ensemble de cases à
     * scruter pour trouver les obstacles.
     *
     * @return list<array{int, int}> [x, y] des cases traversées
     */
    public static function tilesBetween(int $x0, int $y0, int $x1, int $y1): array
    {
        $forward = self::raster($x0, $y0, $x1, $y1);
        $backward = array_reverse(self::raster($x1, $y1, $x0, $y0));

        $corridor = [];
        $seen = [];

        /* Les deux traversées comptent le MÊME nombre de pas — max(|dx|,|dy|)
         * — donc on les apparie indice par indice : la case divergente
         * s'insère là où elle diverge, et le corridor reste dans l'ordre du
         * trajet. */
        foreach ($forward as $i => $tile) {
            foreach ([$tile, $backward[$i] ?? null] as $candidate) {
                if ($candidate === null) {
                    continue;
                }

                $key = $candidate[0] . ',' . $candidate[1];
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $corridor[] = $candidate;
            }
        }

        return $corridor;
    }

    /**
     * Les deux traversées valides, chacune ordonnée depuis le tireur.
     *
     * Un tir passe s'il en existe une libre de bout en bout ; s'il faut
     * nommer l'obstacle, c'est le premier rencontré depuis le tireur.
     *
     * @return array{0: list<array{int, int}>, 1: list<array{int, int}>}
     */
    public static function paths(int $x0, int $y0, int $x1, int $y1): array
    {
        return [
            self::raster($x0, $y0, $x1, $y1),
            array_reverse(self::raster($x1, $y1, $x0, $y0)),
        ];
    }

    /**
     * Bresenham brut, dans un seul sens, extrémités exclues.
     *
     * @return list<array{int, int}>
     */
    private static function raster(int $x0, int $y0, int $x1, int $y1): array
    {
        // Même case : rien entre les deux (et la boucle ne terminerait pas).
        if ($x0 === $x1 && $y0 === $y1) {
            return [];
        }

        $tiles = [];

        $dx = abs($x1 - $x0);
        $dy = -abs($y1 - $y0);
        $sx = $x0 < $x1 ? 1 : -1;
        $sy = $y0 < $y1 ? 1 : -1;
        $err = $dx + $dy;

        $x = $x0;
        $y = $y0;

        while (true) {
            $e2 = 2 * $err;
            if ($e2 >= $dy) {
                $err += $dy;
                $x += $sx;
            }
            if ($e2 <= $dx) {
                $err += $dx;
                $y += $sy;
            }

            if ($x === $x1 && $y === $y1) {
                break;
            }

            $tiles[] = [$x, $y];
        }

        return $tiles;
    }
}
