<?php

namespace Tests\Action;

use App\Factory\ActionFactory;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * L'action GÉNÉRIQUE 'equiper' de bout en bout
 * (docs/design-generic-item-actions.md, volet 2) : bascule
 * équiper/déséquiper via ItemPick — équiper coûte 1 Ae, déséquiper est
 * gratuit, et le type 'equip' (sans ligne action_type_xp) ne rapporte
 * AUCUNE XP : pas de fermier de garde-robe.
 */
#[Group('items-baseline')]
class EquiperBaselineTest extends LegacyPlayerFixtureTestCase
{
    private function actionOrSkip(): \App\Interface\ActionInterface
    {
        $action = ActionFactory::getAction('equiper');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no generic 'equiper' row — run migrations).");
        }

        return $action;
    }

    protected function tearDown(): void
    {
        unset($_POST['itemId'], $_POST['instanceId']);
        parent::tearDown();
    }

    public function testEquipCostsOneAeUnequipIsFreeAndNoXpEitherWay(): void
    {
        $bearer = $this->createRealPlayer('GmBearer');
        $bearer->getCoords();
        $bearer->get_caracs();
        $maxAe = (int) $bearer->caracs->ae;
        $xpBefore = (int) $this->link->fetchOne('SELECT xp FROM players WHERE id = ?', [$bearer->id]);

        $gladius = $this->itemOrSkip('gladius');
        $gladius->add_item($bearer, 1);
        $_POST['itemId'] = (string) $gladius->id;

        // Équiper : 1 Ae
        $results = (new ActionExecutorService($this->actionOrSkip(), $bearer, $bearer))->executeAction();
        $this->assertTrue($results->isSuccess(), 'owning the gladius, equipping must succeed');

        $fresh = PlayerFactory::legacy($bearer->id);
        // Un gladius à durabilité s'équipe comme INSTANCE (promotion
        // paresseuse) : l'état porté vit alors dans players_items_instances.
        $worn = fn (): bool => (bool) ($this->link->fetchOne(
            "SELECT 1 FROM players_items WHERE player_id = ? AND item_id = ? AND equiped != ''",
            [$bearer->id, $gladius->id]
        ) !== false || $this->link->fetchOne(
            "SELECT 1 FROM players e JOIN item_instances i ON i.entity_id = e.id
             WHERE e.holder_id = ? AND i.item_id = ? AND e.slot != ''",
            [$bearer->id, $gladius->id]
        ) !== false);
        $this->assertTrue($worn(), 'the gladius must be worn');
        $this->assertSame($maxAe - 1, $fresh->getRemaining('ae'), 'equipping costs exactly 1 Ae');

        // Déséquiper : gratuit
        $bearer2 = PlayerFactory::legacy($bearer->id);
        $bearer2->get_data();
        $bearer2->getCoords();
        $bearer2->get_caracs();
        $results = (new ActionExecutorService($this->actionOrSkip(), $bearer2, $bearer2))->executeAction();
        $this->assertTrue($results->isSuccess(), 'unequipping must succeed');

        $fresh = PlayerFactory::legacy($bearer->id);
        $this->assertFalse($worn(), 'the gladius must be back in the bag');
        $this->assertSame($maxAe - 1, $fresh->getRemaining('ae'), 'unequipping is free');

        $this->assertSame(
            $xpBefore,
            (int) $this->link->fetchOne('SELECT xp FROM players WHERE id = ?', [$bearer->id]),
            "type 'equip' has no XP rule: the wardrobe grants no XP"
        );
    }

    public function testWithoutTheItemTheActionBlocks(): void
    {
        $bearer = $this->createRealPlayer('GmNaked');
        $bearer->getCoords();
        $bearer->get_caracs();

        $gladius = $this->itemOrSkip('gladius');
        $_POST['itemId'] = (string) $gladius->id;

        $results = (new ActionExecutorService($this->actionOrSkip(), $bearer, $bearer))->executeAction();

        $this->assertTrue($results->isBlocked(), 'no gladius owned: ItemPick must block');
    }
}
