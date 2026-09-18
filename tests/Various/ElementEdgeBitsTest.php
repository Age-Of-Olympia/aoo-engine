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
