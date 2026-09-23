<?php

namespace Tests\Player;

use App\Service\EffectService;
use App\Service\MapElementService;
use Classes\Element;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/** Stepping on an element applies the effect its type names, not the one of its name. */
#[Group('database')]
class ElementTypeEffectTest extends LegacyPlayerFixtureTestCase
{
    protected function tearDown(): void
    {
        $this->link->executeStatement("DELETE FROM map_elements WHERE name = 'glu_test'");
        $this->link->executeStatement("DELETE FROM element_types WHERE name = 'glu_test'");
        $this->link->executeStatement("DELETE FROM effects WHERE name IN ('glu_test', 'colle_test')");
        EffectService::clearCache();
        MapElementService::clearCache();
        parent::tearDown();
    }

    public function testTheTypeChoosesTheEffect(): void
    {
        $this->link->executeStatement("INSERT INTO effects (name, label) VALUES ('glu_test', 'Glu'), ('colle_test', 'Colle')");
        EffectService::clearCache();
        $elements = new MapElementService();
        $this->assertSame('glu_test', $elements->effectOf('glu_test'), 'no row: the effect of its own name');

        $elements->setEffect('glu_test', 'colle_test');
        $player = $this->createRealPlayer('GmGlu');
        $cell = $this->tile(1, 0);
        Element::put('glu_test', (int) \Classes\View::get_coords_id($cell), 4);
        $player->go($cell);

        $this->assertNotEmpty($player->have_effect('colle_test'));
        $this->assertEmpty($player->have_effect('glu_test'));

        $elements->setEffect('glu_test', null);
        $this->assertNull($elements->effectOf('glu_test'), 'decor, though an effect bears its name');
        $this->assertFalse($elements->isBuildableOver('glu_test'), 'decor blocks construction');
    }
}
