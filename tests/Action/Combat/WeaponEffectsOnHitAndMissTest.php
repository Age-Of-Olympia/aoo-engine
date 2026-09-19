<?php

namespace Tests\Action\Combat;

use App\Factory\ActionFactory;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use App\Service\EffectService;
use App\Service\ItemEffectService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * A weapon's effects land by outcome and receiver: on a hit the 'hit' rows
 * reach the target, on a miss the 'miss' rows reach whoever they name —
 * here the bearer. Whatever the dice say, exactly one of the two lands.
 */
#[Group('action-combat')]
class WeaponEffectsOnHitAndMissTest extends LegacyPlayerFixtureTestCase
{
    private int $weaponId = 0;

    protected function tearDown(): void
    {
        if ($this->weaponId > 0) {
            $this->link->executeStatement('DELETE FROM players_items WHERE item_id = ?', [$this->weaponId]);
            $this->link->executeStatement('DELETE FROM items WHERE id = ?', [$this->weaponId]);
        }
        $this->link->executeStatement("DELETE FROM effects WHERE name IN ('brulure_test', 'honte_test')");
        EffectService::clearCache();
        parent::tearDown();
    }

    public function testHitRowsReachTheTargetAndMissRowsTheBearer(): void
    {
        $this->link->executeStatement(
            "INSERT INTO effects (name, label) VALUES ('brulure_test', 'Brûlure'), ('honte_test', 'Honte')"
        );
        EffectService::clearCache();

        $this->link->executeStatement(
            "INSERT INTO items (name, price, stats_in_db, type, subtype, emplacement) VALUES ('lame_test', 1, 1, 'equipement', 'melee', 'main1')"
        );
        $this->weaponId = (int) $this->link->fetchOne("SELECT id FROM items WHERE name = 'lame_test'");
        (new ItemEffectService())->replaceForItem($this->weaponId, [
            ['name' => 'brulure_test', 'duration' => 2, 'outcome' => 'hit', 'target' => 'target'],
            ['name' => 'honte_test', 'duration' => 1, 'outcome' => 'miss', 'target' => 'self'],
        ]);

        $actor = $this->createRealPlayer('GmLame');
        $target = $this->createRealPlayer('GmVictime');
        $this->movePlayerTo($target->id, 0, 1);
        $this->link->executeStatement(
            "INSERT INTO players_items (player_id, item_id, n, equiped, slot) VALUES (?, ?, 1, 'main1', '')",
            [$actor->id, $this->weaponId]
        );
        $actor = PlayerFactory::legacy($actor->id);
        $target = PlayerFactory::legacy($target->id);
        $actor->getCoords();
        $target->getCoords();
        $actor->get_caracs();
        $target->get_caracs();

        $this->assertCount(2, $actor->getEquipedItemsEffects(), 'both rows travel with the equipped weapon');

        $action = ActionFactory::getAction('melee');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no 'melee' row).");
        }
        $results = (new ActionExecutorService($action, $actor, $target))->executeAction();
        $this->assertFalse($results->isBlocked());

        $targetBurns = $this->link->fetchOne(
            "SELECT COUNT(*) FROM players_effects WHERE player_id = ? AND name = 'brulure_test'", [$target->id]
        );
        $actorAshamed = $this->link->fetchOne(
            "SELECT COUNT(*) FROM players_effects WHERE player_id = ? AND name = 'honte_test'", [$actor->id]
        );
        $this->assertSame(
            1,
            (int) $targetBurns + (int) $actorAshamed,
            'a hit burns the target, a miss shames the bearer — never both, never neither'
        );
        $this->assertSame(0, (int) $this->link->fetchOne(
            "SELECT COUNT(*) FROM players_effects WHERE player_id = ? AND name = 'brulure_test'", [$actor->id]
        ), 'the hit row never lands on the bearer');
    }
}
