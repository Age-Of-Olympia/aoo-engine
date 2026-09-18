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

        $this->assertSame(0, View::elementEdgeBits(['0,0' => 'eau', '0,1' => 'eau', '1,0' => 'eau', '0,-1' => 'eau', '-1,0' => 'eau'], 0, 0, 'eau'), 'entouré : aucun bord');
        $this->assertSame(4 | 8, View::elementEdgeBits($lake, 0, 0, 'eau'), 'sud vide et ouest occupé par un autre élément');
        $this->assertSame(15, View::elementEdgeBits([], 3, 3, 'eau'), 'isolé : les quatre côtés');
    }
}
