<?php

namespace Tests\Action;

use App\Factory\ActionFactory;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * L'action GÉNÉRIQUE 'consommer' de bout en bout
 * (docs/design-generic-item-actions.md) : l'objet arrive à l'exécution
 * (POST itemId → ItemPick), le coût 1 A et le retrait de l'exemplaire
 * sont des conditions, la charge (bonus, effets) une instruction —
 * même résultat observable que le geste d'inventaire historique.
 */
#[Group('items-baseline')]
class ConsommerBaselineTest extends LegacyPlayerFixtureTestCase
{
    private function actionOrSkip(): \App\Interface\ActionInterface
    {
        $action = ActionFactory::getAction('consommer');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no generic 'consommer' row — run migrations).");
        }

        return $action;
    }

    protected function tearDown(): void
    {
        unset($_POST['itemId']);
        parent::tearDown();
    }

    public function testDrinkingAPotionHealsConsumesAndCostsOneA(): void
    {
        $drinker = $this->createRealPlayer('GmDrinker');
        $drinker->getCoords();
        $drinker->get_caracs();
        $maxA = (int) $drinker->caracs->a;
        $this->snapshotBloodAt((int) $drinker->data->coords_id);

        $potion = $this->sowCatalogItem('potion_soin', ['type' => 'consommable', 'pv' => 10]);
        $potion->add_item($drinker, 1);
        $drinker->putBonus(['pv' => -20]);

        $_POST['itemId'] = (string) $potion->id;
        $results = (new ActionExecutorService($this->actionOrSkip(), $drinker, $drinker))->executeAction();

        $this->assertFalse($results->isBlocked(), 'owning the potion, the action must pass');
        $this->assertTrue($results->isSuccess(), 'consommer has no dice: passing conditions means success');

        $fresh = PlayerFactory::legacy($drinker->id);
        $this->assertSame(
            min((int) $drinker->caracs->pv, (int) $drinker->caracs->pv - 20 + 10),
            $fresh->getRemaining('pv'),
            'the potion payload (+10 PV) is applied through the shared applyConsumablePayload'
        );
        $this->assertSame(0, $potion->get_n($fresh), 'the potion is consumed (RequiresItem via ItemPick)');
        $this->assertSame($maxA - 1, $fresh->getRemaining('a'), 'the action costs exactly 1 A');
    }

    public function testWithoutThePotionTheActionBlocksAndCostsNothing(): void
    {
        $drinker = $this->createRealPlayer('GmThirsty');
        $drinker->getCoords();
        $drinker->get_caracs();
        $maxA = (int) $drinker->caracs->a;

        $potion = $this->sowCatalogItem('potion_soin', ['type' => 'consommable', 'pv' => 10]);
        $_POST['itemId'] = (string) $potion->id;

        $results = (new ActionExecutorService($this->actionOrSkip(), $drinker, $drinker))->executeAction();

        $this->assertTrue($results->isBlocked(), 'no potion owned: ItemPick must block');
        $this->assertSame($maxA, PlayerFactory::legacy($drinker->id)->getRemaining('a'), 'a blocked action must not cost the A');
    }

    public function testAimingAtSomeoneElseIsRefusedBySelfTargeting(): void
    {
        // Visée 'self' : consommer ne s'applique qu'à son lanceur — la
        // potion d'un autre ne se boit pas par procuration.
        $drinker = $this->createRealPlayer('GmSelfish');
        $other = $this->createRealPlayer('GmVictim');
        $drinker->getCoords();
        $drinker->get_caracs();
        $other->get_caracs();

        $potion = $this->sowCatalogItem('potion_soin', ['type' => 'consommable', 'pv' => 10]);
        $potion->add_item($drinker, 1);
        $_POST['itemId'] = (string) $potion->id;

        $results = (new ActionExecutorService($this->actionOrSkip(), $drinker, $other))->executeAction();

        $this->assertTrue($results->isBlocked(), "visée 'self' : une cible tierce doit bloquer");
        $this->assertSame(1, $potion->get_n(PlayerFactory::legacy($drinker->id)), 'nothing may be consumed');
    }

    public function testANonConsumableItemIsRefusedByAdmissibility(): void
    {
        // Un itemId forgé ne doit pas permettre de « consommer » l'or :
        // possession OK, admissibilité (kind consommable) refusée.
        $drinker = $this->createRealPlayer('GmCheater');
        $drinker->getCoords();
        $drinker->get_caracs();

        $or = $this->itemOrSkip('or');
        $or->add_item($drinker, 5);
        $_POST['itemId'] = (string) $or->id;

        $results = (new ActionExecutorService($this->actionOrSkip(), $drinker, $drinker))->executeAction();

        $this->assertTrue($results->isBlocked(), 'gold is not a consumable: ItemPick admissibility must block');
        $this->assertSame(5, $or->get_n(PlayerFactory::legacy($drinker->id)), 'nothing may be spent');
    }
}
