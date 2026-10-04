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

    /** The type sets how long and how hard its effect lands, as a skill does. */
    public function testTheTypeSetsDurationAndIntensity(): void
    {
        $this->link->executeStatement("INSERT INTO effects (name, label) VALUES ('glu_test', 'Glu')");
        EffectService::clearCache();
        $elements = new MapElementService();
        $elements->setEffectStrength('glu_test', 3, 2);

        $player = $this->createRealPlayer('GmGluForte');
        $cell = $this->tile(2, 0);
        Element::put('glu_test', (int) \Classes\View::get_coords_id($cell), 4);
        $player->go($cell);

        $row = $this->link->fetchAssociative(
            "SELECT endTime, value FROM players_effects WHERE player_id = ? AND name = 'glu_test'",
            [(int) $player->id]
        );
        $this->assertSame(3, (int) $row['endTime']);
        $this->assertSame(2, (int) $row['value']);
        $this->assertSame('glu_test', $elements->effectOf('glu_test'), 'the new row keeps the effect of its own name');
    }

    /** The step tells what it took, at the type's intensity; the card keeps only what lasts. */
    public function testTheStepTellsWhatItTook(): void
    {
        $this->link->executeStatement(
            "INSERT INTO effects (name, label, loss_mods) VALUES ('glu_test', 'Glu', '{\"pv\":-15,\"mvt\":-1}')"
        );
        EffectService::clearCache();
        $elements = new MapElementService();
        $elements->setEffectStrength('glu_test', 3, 2);
        $elements->setLabel('glu_test', 'Lave');

        $player = $this->createRealPlayer('GmGluBrule');
        $cell = $this->tile(3, 0);
        Element::put('glu_test', (int) \Classes\View::get_coords_id($cell), 4);

        $this->assertSame(['Lave : vous avez perdu 30 PV et 2 Mvt.'], $player->go($cell));
        $this->assertSame('', (new EffectService())->describe('glu_test', 2, false), 'nothing lasting to show');
    }

    /** The step tells which carried effect the element's effect put out. */
    public function testTheStepTellsWhatItCancelled(): void
    {
        $this->link->executeStatement("INSERT INTO effects (name, label) VALUES ('glu_test', 'Glu'), ('colle_test', 'Colle')");
        EffectService::clearCache();
        $effects = new EffectService();
        $effects->replaceControls($effects->getEffectByName('glu_test'), ['colle_test']);
        (new MapElementService())->setLabel('glu_test', 'Eau');

        $player = $this->createRealPlayer('GmGluEteint');
        $player->add_effect('colle_test', 5);
        $cell = $this->tile(4, 0);
        Element::put('glu_test', (int) \Classes\View::get_coords_id($cell), 4);

        $this->assertSame(['Eau : l\'effet Colle prend fin.'], $player->go($cell));
        $this->assertEmpty($player->have_effect('colle_test'));
    }

    public function testFluidIsSetWithoutTouchingTheEffect(): void
    {
        $this->link->executeStatement("INSERT INTO effects (name, label) VALUES ('glu_test', 'Glu')");
        EffectService::clearCache();
        $elements = new MapElementService();
        $this->assertTrue($elements->isFluid('glu_test'), 'no row: fluid');

        $elements->setFluid('glu_test', false);
        $this->assertFalse($elements->isFluid('glu_test'));
        $this->assertSame('glu_test', $elements->effectOf('glu_test'), 'the new row keeps the effect of its own name');
    }

    public function testTheLabelFallsBackOnTheCode(): void
    {
        $elements = new MapElementService();
        $this->assertSame('glu_test', $elements->labelOf('glu_test'), 'no row: the code');

        $elements->setLabel('glu_test', 'Glu collante');
        $this->assertSame('Glu collante', $elements->labelOf('glu_test'));

        $elements->setLabel('glu_test', '');
        $this->assertSame('glu_test', $elements->labelOf('glu_test'), 'emptied: the code again');
    }
}
