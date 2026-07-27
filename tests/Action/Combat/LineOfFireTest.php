<?php

namespace Tests\Action\Combat;

use App\Action\Combat\LineOfFire;
use PHPUnit\Framework\TestCase;

/**
 * Géométrie pure de la ligne de tir : les cases STRICTEMENT entre le
 * tireur et sa cible (extrémités exclues). Les décisions de blocage
 * (blocks_projectiles) vivent dans BuildingService et ne sont pas testées
 * ici.
 *
 * Ce que ces tests garantissent avant tout : **si un tir passe dans un
 * sens, il passe dans l'autre**. Le tracé de Bresenham n'étant pas
 * symétrique, on calcule les deux sens — le corridor est leur union
 * (contiguë, c'est ce qu'on dessine), le noyau leur intersection (les
 * seules cases qui arrêtent le tir).
 */
class LineOfFireTest extends TestCase
{
    public function testSameTileHasNoTilesBetween(): void
    {
        $this->assertSame([], LineOfFire::tilesBetween(3, -2, 3, -2));
    }

    public function testAdjacentTilesHaveNoTilesBetween(): void
    {
        $this->assertSame([], LineOfFire::tilesBetween(0, 0, 1, 0));
        $this->assertSame([], LineOfFire::tilesBetween(0, 0, 1, 1));
        $this->assertSame([], LineOfFire::tilesBetween(0, 0, 0, -1));
    }

    public function testHorizontalLine(): void
    {
        $this->assertSame([[1, 0], [2, 0]], LineOfFire::tilesBetween(0, 0, 3, 0));
    }

    public function testVerticalLineNegativeDirection(): void
    {
        $this->assertSame([[0, -1], [0, -2]], LineOfFire::tilesBetween(0, 0, 0, -3));
    }

    public function testPerfectDiagonal(): void
    {
        $this->assertSame([[1, 1], [2, 2]], LineOfFire::tilesBetween(0, 0, 3, 3));
    }

    public function testKnightShapedShot(): void
    {
        /* (0,0) → (2,1) : l'arrondi hésite entre (1,0) et (1,1) selon le
         * sens de parcours. Le corridor contient les deux ; aucune des deux
         * n'arrête le tir, puisque chacune est évitable par l'un des tracés
         * — le noyau est donc vide. */
        $this->assertSame([[1, 1], [1, 0]], LineOfFire::tilesBetween(0, 0, 2, 1));
        $this->assertSame([], LineOfFire::blockingTilesBetween(0, 0, 2, 1));
    }

    public function testShallowSlope(): void
    {
        $this->assertSame(
            [[1, 0], [2, 1], [2, 0], [3, 1]],
            LineOfFire::tilesBetween(0, 0, 4, 1),
            'le corridor contient les deux arrondis de la case du milieu'
        );
        $this->assertSame(
            [[1, 0], [3, 1]],
            LineOfFire::blockingTilesBetween(0, 0, 4, 1),
            'seules les cases traversées dans les deux sens arrêtent le tir'
        );
    }

    public function testEndpointsAreNeverIncluded(): void
    {
        foreach ([[5, 5], [-3, 4], [7, -2]] as [$tx, $ty]) {
            $tiles = LineOfFire::tilesBetween(1, 1, $tx, $ty);
            $this->assertNotContains([1, 1], $tiles);
            $this->assertNotContains([$tx, $ty], $tiles);
        }
    }

    /**
     * LA règle : si un tir passe dans un sens, il passe dans l'autre.
     *
     * Ce test remplace un ancien qui ne comparait que la LONGUEUR des deux
     * tracés et admettait explicitement qu'ils diffèrent « d'une case
     * d'arrondi ». C'était précisément le défaut : sur un rayon de douze
     * cases, 36 % des trajets divergeaient, et un obstacle sur une case
     * divergente arrêtait le tir dans un sens seulement.
     */
    public function testEveryShotIsSymmetric(): void
    {
        $radius = 12;
        $sortedKeys = static fn (array $tiles): array => self::sortedKeys($tiles);

        for ($dx = -$radius; $dx <= $radius; $dx++) {
            for ($dy = -$radius; $dy <= $radius; $dy++) {
                if ($dx === 0 && $dy === 0) {
                    continue;
                }

                $this->assertSame(
                    $sortedKeys(LineOfFire::tilesBetween(0, 0, $dx, $dy)),
                    $sortedKeys(LineOfFire::tilesBetween($dx, $dy, 0, 0)),
                    "corridor asymétrique vers ({$dx}, {$dy})"
                );

                $this->assertSame(
                    $sortedKeys(LineOfFire::blockingTilesBetween(0, 0, $dx, $dy)),
                    $sortedKeys(LineOfFire::blockingTilesBetween($dx, $dy, 0, 0)),
                    "noyau asymétrique vers ({$dx}, {$dy})"
                );
            }
        }
    }

    /**
     * Le corridor est contigu : c'est lui qu'on dessine, et un tracé à trous
     * serait illisible. L'intersection seule en aurait dans un tiers des cas.
     */
    public function testCorridorIsContiguous(): void
    {
        $radius = 12;

        for ($dx = -$radius; $dx <= $radius; $dx++) {
            for ($dy = -$radius; $dy <= $radius; $dy++) {
                if ($dx === 0 && $dy === 0) {
                    continue;
                }

                $tiles = LineOfFire::tilesBetween(0, 0, $dx, $dy);
                for ($i = 1, $n = count($tiles); $i < $n; $i++) {
                    $step = max(
                        abs($tiles[$i][0] - $tiles[$i - 1][0]),
                        abs($tiles[$i][1] - $tiles[$i - 1][1])
                    );
                    $this->assertSame(1, $step, "trou dans le corridor vers ({$dx}, {$dy})");
                }
            }
        }
    }

    /** Le noyau est toujours inclus dans le corridor. */
    public function testBlockingTilesAreASubsetOfTheCorridor(): void
    {
        foreach ([[5, 2], [4, 1], [-7, 3], [2, -9]] as [$dx, $dy]) {
            $corridor = self::sortedKeys(LineOfFire::tilesBetween(0, 0, $dx, $dy));
            $blocking = self::sortedKeys(LineOfFire::blockingTilesBetween(0, 0, $dx, $dy));

            $this->assertSame([], array_values(array_diff($blocking, $corridor)));
        }
    }

    /** @param list<array{int, int}> $tiles @return list<string> */
    private static function sortedKeys(array $tiles): array
    {
        $keys = array_map(static fn (array $t): string => $t[0] . ',' . $t[1], $tiles);
        sort($keys);

        return $keys;
    }
}
