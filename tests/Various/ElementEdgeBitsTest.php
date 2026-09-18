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
        $this->assertStringContainsString('<linearGradient id="elem-half-WSE-g" x1="0" y1="1" x2="1" y2="0">', View::elementHalfDefs(['elem-half-WSE']));
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
