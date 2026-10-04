<?php

namespace Tests\Player;

use App\View\MainView;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * MainView caches a board only when it looks like one. The check read a
 * markup the grid no longer wrote, so no board was cached and every page
 * load drew the whole board again: the check runs on a real render here.
 */
#[Group('database')]
class BoardCacheTest extends LegacyPlayerFixtureTestCase
{
    public function testARealBoardIsCached(): void
    {
        $player = $this->createRealPlayer('GmCache');

        $svg = (new View($player->getCoords(), 3, playerId: (int) $player->id))->get_view();

        $this->assertTrue(MainView::isRenderable($svg));
    }

    public function testAnEmptyRenderIsNot(): void
    {
        $this->assertFalse(MainView::isRenderable(null));
        $this->assertFalse(MainView::isRenderable('<svg></svg>'));
    }
}
