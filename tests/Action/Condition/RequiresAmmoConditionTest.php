<?php

namespace Tests\Action\Condition;

use App\Action\Condition\ConditionObject;
use App\Action\Condition\RequiresAmmoCondition;
use App\Entity\ActionCondition;
use App\Factory\PlayerFactory;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

#[Group('action-condition')]
class RequiresAmmoConditionTest extends LegacyPlayerFixtureTestCase
{
    /** @var int[] */
    private array $itemIds = [];

    protected function tearDown(): void
    {
        foreach ($this->itemIds as $id) {
            $this->link->executeStatement('DELETE FROM players_items WHERE item_id = ?', [$id]);
            $this->link->executeStatement('DELETE FROM items WHERE id = ?', [$id]);
        }
        parent::tearDown();
    }

    private function item(string $name, string $emplacement): int
    {
        $this->link->executeStatement(
            "INSERT INTO items (name, price, stats_in_db, type, subtype, emplacement) VALUES (?, 1, 1, 'equipement', 'melee', ?)",
            [$name, $emplacement]
        );
        $id = (int) $this->link->lastInsertId();
        $this->itemIds[] = $id;

        return $id;
    }

    private function passes(int $actorId, int $ammoId): bool
    {
        $condition = new ActionCondition();
        $condition->setParameters(['itemId' => $ammoId]);
        $actor = PlayerFactory::legacy($actorId);

        return (new RequiresAmmoCondition())->check($actor, null, $condition, new ConditionObject())->isSuccess();
    }

    public function testOnlyTheRequestedItemCountsAsAmmo(): void
    {
        $ammo = $this->item('fleche_test', 'munition');
        $other = $this->item('lame_test', 'main1');
        $actor = $this->createRealPlayer('GmArcher');

        $this->link->executeStatement(
            "INSERT INTO players_items (player_id, item_id, n, equiped, slot) VALUES (?, ?, 1, 'main1', '')",
            [$actor->id, $other]
        );
        $this->assertFalse($this->passes($actor->id, $ammo), 'another equipped item is not the ammo');

        $this->link->executeStatement(
            "INSERT INTO players_items (player_id, item_id, n, equiped, slot) VALUES (?, ?, 5, 'munition', '')",
            [$actor->id, $ammo]
        );
        $this->assertTrue($this->passes($actor->id, $ammo), 'the equipped ammo passes');
    }
}
