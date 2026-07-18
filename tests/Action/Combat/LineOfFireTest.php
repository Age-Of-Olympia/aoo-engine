<?php

namespace Tests\Action\Combat;

use App\Action\Combat\LineOfFire;
use PHPUnit\Framework\TestCase;

/**
 * Géométrie pure de la ligne de tir : les cases STRICTEMENT entre le
 * tireur et sa cible (Bresenham, extrémités exclues). Les décisions de
 * blocage (blocks_projectiles) vivent dans BuildingService et ne sont
 * pas testées ici.
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
        // (0,0) → (2,1) : Bresenham traverse (1,0) ou (1,1) selon
        // l'arrondi — notre variante entière passe par (1,1).
        $this->assertSame([[1, 1]], LineOfFire::tilesBetween(0, 0, 2, 1));
    }

    public function testShallowSlope(): void
    {
        $this->assertSame([[1, 0], [2, 1], [3, 1]], LineOfFire::tilesBetween(0, 0, 4, 1));
    }

    public function testEndpointsAreNeverIncluded(): void
    {
        foreach ([[5, 5], [-3, 4], [7, -2]] as [$tx, $ty]) {
            $tiles = LineOfFire::tilesBetween(1, 1, $tx, $ty);
            $this->assertNotContains([1, 1], $tiles);
            $this->assertNotContains([$tx, $ty], $tiles);
        }
    }

    public function testSymmetryInTileCount(): void
    {
        // Aller et retour traversent le même nombre de cases (le tracé
        // exact peut différer d'une case d'arrondi, pas la longueur).
        $this->assertCount(
            count(LineOfFire::tilesBetween(0, 0, 5, 2)),
            LineOfFire::tilesBetween(5, 2, 0, 0)
        );
    }
}
