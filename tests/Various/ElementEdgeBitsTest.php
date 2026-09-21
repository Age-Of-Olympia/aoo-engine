<?php

namespace Tests\Various;

use Classes\View;
use PHPUnit\Framework\TestCase;

/**
 * Un élément s'estompe sur les côtés où la case voisine ne porte pas le
 * même élément : bits 1 nord, 2 est, 4 sud, 8 ouest.
 */
class ElementEdgeBitsTest extends TestCase
{
    public function testOpenSidesAreThoseWithoutTheSameElement(): void
    {
        $lake = ['0,0' => 'eau', '0,1' => 'eau', '1,0' => 'eau', '-1,0' => 'boue'];

        $this->assertSame(0, View::elementEdgeBits(array_fill_keys(['0,0', '0,1', '1,0', '0,-1', '-1,0', '1,1', '1,-1', '-1,-1', '-1,1'], 'eau'), 0, 0, 'eau'), 'surrounded on all eight cells: no fade');
        $this->assertSame(16 | 32 | 64 | 128, View::elementEdgeBits(['0,0' => 'eau', '0,1' => 'eau', '1,0' => 'eau', '0,-1' => 'eau', '-1,0' => 'eau'], 0, 0, 'eau'), 'centre of a cross: four inside corners');
        $this->assertSame(4 | 8 | 16, View::elementEdgeBits($lake, 0, 0, 'eau'), 'south empty, west another element, and the NE diagonal is the inside of a bend');
        $this->assertSame(15, View::elementEdgeBits([], 3, 3, 'eau'), 'isolé : les quatre côtés');
    }

    public function testElementsOfOneFamilyJoinEdgeToEdge(): void
    {
        $fall = ['0,1' => 'eau_cascade', '0,0' => 'eau_ecume', '0,-1' => 'eau', '1,0' => 'sang'];

        $this->assertSame(2 | 8, View::elementEdgeBits($fall, 0, 0, 'eau_ecume'), 'foam joins the fall above and the water below, fades east (blood) and west (empty)');
        $this->assertSame(1 | 2 | 8, View::elementEdgeBits($fall, 0, 1, 'eau_cascade'), 'the fall only joins downward');
        $this->assertSame('eau', View::elementFamily('eau_cascade'));
        $this->assertSame('sang', View::elementFamily('sang'));
    }

    public function testAnElbowEntersAndLeavesLikeTheStraightPartsAroundIt(): void
    {
        /* Down a run painted 180, elbow, elbow (a staircase step), then a horizontal run painted 270:
           the halves that touch a run take that run's rotation; the step between the elbows takes the path's. */
        $at = ['0,5' => 'eau_cascade', '0,4' => 'eau_cascade', '0,3' => 'eau_cascade', '1,3' => 'eau_cascade', '1,2' => 'eau_cascade', '2,2' => 'eau_cascade', '3,2' => 'eau_cascade'];
        $rot = ['0,5' => 180, '0,4' => 180, '2,2' => 270, '3,2' => 270];
        $axes = View::elementAxes($at, $rot, 'eau');

        $this->assertSame(['v' => 180, 'h' => 270], $axes['1,3'], 'the path reads both axes from its runs');
        $this->assertSame(['N' => 180, 'E' => 270], array_column(View::elementElbow($at, $rot, $axes, 0, 3, 'eau_cascade'), 'rotation', 'side'), 'first elbow: enters like the vertical run, leaves like the path horizontal');
        $this->assertSame(['S' => 180, 'W' => 270], array_column(View::elementElbow($at, $rot, $axes, 1, 3, 'eau_cascade'), 'rotation', 'side'), 'second elbow: joins the step and the horizontal run as painted');
        $this->assertNull(View::elementElbow(['0,0' => 'eau', '0,1' => 'eau', '0,-1' => 'eau'], [], [], 0, 0, 'eau'), 'a straight run is not an elbow');
        $this->assertNull(View::elementElbow(['0,0' => 'eau', '1,0' => 'eau', '0,-1' => 'eau', '1,-1' => 'eau'], [], [], 0, 0, 'eau'), 'the corner of a two-wide block is not an elbow');
        $this->assertNull(View::elementElbow(['0,0' => 'eau', '0,1' => 'eau', '-1,0' => 'sang'], [], [], 0, 0, 'eau'), 'another family does not count');
        $this->assertSame(['v' => 0, 'h' => 90], View::elementAxes(['0,0' => 'eau'], [], 'eau')['0,0'], 'no straight run: defaults');
        // West half of a bend joined west and north: fades from its own corner (SW) toward NE
        $this->assertStringContainsString('<linearGradient id="elem-half-WSE-g" gradientUnits="userSpaceOnUse" x1="0" y1="50" x2="50" y2="0">', View::elementHalfDefs(['elem-half-WSE']));
    }

    public function testATwoWideFlowBendsOnItsTwoCornerCells(): void
    {
        /* Vertical band x=0,1 for y>=1 turning east into a horizontal band y=0,1 for x>=0. */
        $at = [];
        foreach ([0, 1] as $x) { foreach ([1, 2, 3] as $y) { $at["$x,$y"] = 'eau'; } }
        foreach ([0, 1, 2, 3] as $x) { foreach ([0, 1] as $y) { $at["$x,$y"] = 'eau'; } }
        $rot = ['2,0' => 90, '2,1' => 90, '3,0' => 90, '3,1' => 90, '1,0' => 90];
        $axes = View::elementAxes($at, $rot, 'eau');

        $this->assertSame(['N' => 0, 'E' => 90], array_column(View::elementElbow($at, $rot, $axes, 0, 0, 'eau'), 'rotation', 'side'), 'outer corner: its corner is filled but both runs continue');
        $this->assertSame(['', 'elem-half-ESW'], array_column(View::elementElbow($at, $rot, $axes, 0, 0, 'eau'), 'clip'), 'outer corner: vertical half whole, horizontal half through the SW-NE ramp');
        $this->assertSame(['N' => 0, 'E' => 90], array_column(View::elementElbow($at, $rot, $axes, 1, 1, 'eau'), 'rotation', 'side'), 'inner corner: four neighbours, one empty diagonal');
        $this->assertSame(['', 'elem-half-ESW'], array_column(View::elementElbow($at, $rot, $axes, 1, 1, 'eau'), 'clip'), 'same diagonal as the outer corner');
        $this->assertNull(View::elementElbow($at, $rot, $axes, 0, 1, 'eau'), 'the other two cells of the block stay straight');
        $this->assertNull(View::elementElbow($at, $rot, $axes, 1, 0, 'eau'));

        $fallTop = ['0,0' => 'eau', '1,0' => 'eau', '0,-1' => 'eau', '1,-1' => 'eau', '0,-2' => 'eau', '1,-2' => 'eau'];
        $this->assertNull(View::elementElbow($fallTop, [], [], 0, 0, 'eau'), 'the top of a wide fall: one run stops, no bend');
    }

    public function testCellsAlongAFlowShareTheirPhase(): void
    {
        // Vertical flow: the choice hangs on the column, so a column is uniform and columns differ somewhere
        $this->assertCount(1, array_unique(array_map(fn(int $y) => View::cellPhase('y', 0, 4, $y), range(0, 5))), 'one column, one phase');
        $this->assertSame([0.0, 0.5], array_values(array_unique(array_map(fn(int $x) => View::cellPhase('y', 0, $x, 0), range(0, 8)))), 'columns use both phases');
        // Turned a quarter, the same texture flows horizontally: the choice hangs on the row
        $this->assertCount(1, array_unique(array_map(fn(int $x) => View::cellPhase('y', 90, $x, 3), range(0, 5))));
        $this->assertSame(0.0, View::cellPhase(null, 0, 1, 1), 'a diagonal drift is never shifted');
    }

    public function testALayerTileMayBeAnSvg(): void
    {
        $this->assertSame('img/tiles/carreaux.png', View::layerImage('tiles', 'carreaux'), 'a png tile keeps its path');
        $this->assertSame('img/tiles/zz_no_such_tile.png', View::layerImage('tiles', 'zz_no_such_tile'), 'a missing tile keeps the png path, so it shows as broken');

        file_put_contents('img/tiles/zz_svg_tile_test.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50"/>');
        try {
            $this->assertSame('img/tiles/zz_svg_tile_test.svg', View::layerImage('tiles', 'zz_svg_tile_test'));
        } finally {
            unlink('img/tiles/zz_svg_tile_test.svg');
        }
    }

    public function testTheInsideOfABendFadesAtItsCorner(): void
    {
        // A stream coming from the west turning south: the SW diagonal is the inside of the bend
        $bend = ['0,0' => 'cascade', '-1,0' => 'cascade', '0,-1' => 'cascade'];

        $this->assertSame(1 | 2 | 64, View::elementEdgeBits($bend, 0, 0, 'cascade'));
        $this->assertSame(1 | 4, View::elementEdgeBits(['0,0' => 'cascade', '-1,0' => 'cascade', '1,0' => 'cascade'], 0, 0, 'cascade'), 'a straight run has no corner fade');
        $this->assertStringContainsString('<mask id="elem-edge-67"', View::elementEdgeDefs([67]));
        $this->assertStringContainsString('url(#elem-fade-64)', View::elementEdgeDefs([67]));
    }
}
