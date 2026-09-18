<?php

namespace Tests\Various;

use Classes\View;
use PHPUnit\Framework\TestCase;

/** An element fades only toward empty cells: bits 1 north, 2 east, 4 south, 8 west. */
class ElementEdgeBitsTest extends TestCase
{
    public function testOpenSidesAreThoseWithoutAnyElement(): void
    {
        $shore = ['0,0' => 'eau', '0,1' => 'eau', '1,0' => 'eau', '-1,0' => 'cascade'];

        $this->assertSame(4, View::elementEdgeBits($shore, 0, 0), 'south is empty; the waterfall to the west joins the water');
        $this->assertSame(15, View::elementEdgeBits([], 3, 3), 'alone: all four sides');
    }
}
