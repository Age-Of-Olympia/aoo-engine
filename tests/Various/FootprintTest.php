<?php

namespace Tests\Various;

use App\Service\Map\Footprint;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The scenery cut-out value object: the invariants it now carries alone.
 *
 * No database — a figure is data, not a record.
 */
class FootprintTest extends TestCase
{
    /** Callers relied on the convention; construction now enforces it. */
    public function testOffsetsAreBroughtBackToTheFirstPiece(): void
    {
        $shifted = Footprint::fromOffsets([0 => [5, 5], 1 => [6, 5], 2 => [5, 4]]);

        $this->assertSame(
            [0 => [0, 0], 1 => [1, 0], 2 => [0, -1]],
            $shifted->offsets()
        );
    }

    /** Pieces are ordered by index, so the first one really is the first. */
    public function testPiecesAreOrderedWhateverTheirArrivalOrder(): void
    {
        $jumbled = Footprint::fromOffsets([2 => [2, 0], 0 => [0, 0], 1 => [1, 0]]);

        $this->assertSame([0, 1, 2], array_keys($jumbled->offsets()));
        $this->assertSame([0, 0], $jumbled->offsets()[0]);
    }

    /** What is derived is not stored: the box follows the pieces. */
    public function testTheBoxFollowsThePiecesWhenItIsNotGiven(): void
    {
        $figure = Footprint::fromOffsets([0 => [0, 0], 1 => [1, 0], 2 => [0, -1], 3 => [1, -1]]);

        $this->assertSame(2, $figure->width());
        $this->assertSame(2, $figure->height());
        $this->assertSame(4, $figure->cells());
        $this->assertFalse($figure->isHoled(), 'quatre cases dans une boîte de quatre');
    }

    public function testAGivenBoxMayExceedTheFigureAndRevealsTheHoles(): void
    {
        $giant = Footprint::boxed(3, 3, [0 => [0, 0], 1 => [0, -1], 2 => [-1, -2], 3 => [-2, -2]]);

        $this->assertSame(4, $giant->cells());
        $this->assertTrue($giant->isHoled(), 'quatre cases dans une boîte de neuf');
    }

    /** The one calculation the figure does, and the reason it lives here. */
    public function testTheFigureIsSeenFromAnyOfItsPieces(): void
    {
        $tower = Footprint::fromOffsets([0 => [0, 0], 1 => [0, -1]]);

        $this->assertSame(
            [0 => [10, 10], 1 => [10, 9]],
            $tower->cellsAround(0, 10, 10),
            'depuis le premier morceau'
        );

        $this->assertSame(
            [0 => [10, 11], 1 => [10, 10]],
            $tower->cellsAround(1, 10, 10),
            'depuis le second : c\'est LUI qui tombe sur la case visée'
        );
    }

    /** An unknown piece shifts nothing rather than breaking placement. */
    public function testAnUnknownPieceLeavesTheFigureWhereItIs(): void
    {
        $tower = Footprint::fromOffsets([0 => [0, 0], 1 => [0, -1]]);

        $this->assertSame([0 => [3, 3], 1 => [3, 2]], $tower->cellsAround(99, 3, 3));
    }

    /** A role exists only where someone decided one. */
    public function testOnlyDecidedRolesAreKept(): void
    {
        $arch = Footprint::fromOffsets(
            [0 => [0, 0], 1 => [0, -1]],
            [0 => 'block', 1 => '']
        );

        $this->assertSame([0 => 'block'], $arch->roles(), 'le rôle vide n\'est pas un rôle');
        $this->assertSame('block', $arch->roleOf(0, 'part'));
        $this->assertSame('part', $arch->roleOf(1, 'part'), 'sans avis, c\'est le défaut proposé');
    }

    /** A single-cell figure has nothing to spread. */
    public function testASingleCellFigureSaysSo(): void
    {
        $this->assertTrue(Footprint::fromOffsets([0 => [0, 0]])->isSingleCell());
        $this->assertFalse(Footprint::fromOffsets([0 => [0, 0], 1 => [1, 0]])->isSingleCell());
    }

    public function testAFigureWithoutAnyPieceIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Footprint::fromOffsets([]);
    }

    /** A box too small for its pieces describes something else. */
    public function testABoxTooSmallForItsPiecesIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Footprint::boxed(1, 1, [0 => [0, 0], 1 => [1, 0]]);
    }

    /** The JavaScript boundary stays an array, and stays complete. */
    public function testTheArrayFormCarriesEverythingTheEditorNeeds(): void
    {
        $figure = Footprint::boxed(2, 2, [0 => [0, 0], 1 => [1, 0]], [1 => 'block']);

        $this->assertSame(
            [
                'w'       => 2,
                'h'       => 2,
                'cells'   => 2,
                'holed'   => true,
                'offsets' => [0 => [0, 0], 1 => [1, 0]],
                'roles'   => [1 => 'block'],
            ],
            $figure->toArray()
        );
    }
}
