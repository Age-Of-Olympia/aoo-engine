<?php

namespace App\Action\Combat;

/**
 * Géométrie de la ligne de tir : les cases STRICTEMENT entre le tireur
 * et sa cible (Bresenham, extrémités exclues). Pur — la décision de
 * blocage (quelles entités arrêtent une flèche) vit dans
 * BuildingService::lineOfFireReport.
 *
 * Bresenham simple : un tir en diagonale se faufile entre deux coins
 * qui se touchent — le classique des jeux à cases.
 */
final class LineOfFire
{
    /**
     * @return list<array{int, int}> [x, y] des cases traversées
     */
    public static function tilesBetween(int $x0, int $y0, int $x1, int $y1): array
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
