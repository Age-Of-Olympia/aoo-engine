<?php

namespace Tests\Various;

use App\Service\ItemInstanceService;
use App\Service\RepairService;
use Classes\Item;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The atelier's counter: a repair costs a share of the recipe that follows
 * the wear (a quarter at most), paid in resources plus labour or all in
 * gold; a broken exemplar is not repaired but recycled for a quarter of
 * its ingredients.
 */
#[Group('items-baseline')]
class AtelierRepairTest extends LegacyPlayerFixtureTestCase
{
    private ?int $recipeId = null;

    protected function tearDown(): void
    {
        if ($this->recipeId !== null) {
            foreach (['craft_recipes_ingredients', 'craft_recipes_results'] as $table) {
                $this->link->executeStatement("DELETE FROM {$table} WHERE recipe_id = ?", [$this->recipeId]);
            }
            $this->link->executeStatement('DELETE FROM craft_recipes WHERE id = ?', [$this->recipeId]);
        }
        /* A recycled wreck keeps its rows (destroyed, shelved): clear them
         * before the catalog row they point at goes. */
        $entities = $this->link->fetchFirstColumn(
            "SELECT i.entity_id FROM item_instances i JOIN items it ON it.id = i.item_id WHERE it.name = 'marteau_atelier_test'"
        );
        $this->link->executeStatement("DELETE i FROM item_instances i JOIN items it ON it.id = i.item_id WHERE it.name = 'marteau_atelier_test'");
        foreach ($entities as $entityId) {
            $this->link->executeStatement('DELETE FROM players_bonus WHERE player_id = ?', [(int) $entityId]);
            $this->link->executeStatement('DELETE FROM players WHERE id = ?', [(int) $entityId]);
        }
        parent::tearDown();
    }

    public function testTheBillFollowsTheWearAndTheRecipe(): void
    {
        [$client, $instanceId, $entityId] = $this->wornExemplar(wear: 200);

        $service = new RepairService();
        $rows = $service->listRepairable((int) $client->id);
        $this->assertCount(1, $rows, 'one worn exemplar in the bag');
        $quote = $rows[0]['quote'];

        /* Down to 1 PV: a quarter of 8 bois (10 PO) + 4 pierre (10 PO). */
        $this->assertSame(['bois' => 2, 'pierre' => 1], $quote['resources']);
        $this->assertSame(3, $quote['labour'], '10% of 30 PO of resources, rounded up');
        $this->assertSame(45 + 3, $quote['gold'], 'resources at 1.5 × plus the labour');

        $this->link->executeStatement('UPDATE players_bonus SET n = -100 WHERE player_id = ? AND name = ?', [$entityId, 'pv']);
        $half = $service->listRepairable((int) $client->id)[0]['quote'];
        $this->assertSame(['bois' => 1, 'pierre' => 1], $half['resources'], 'half the wear, half the bill, whole units');
        $this->assertSame(23 + 2, $half['gold']);
    }

    public function testResourcesPayTheRepairAndRestoreFullLife(): void
    {
        [$client, $instanceId, $entityId] = $this->wornExemplar(wear: 200);
        (new Item((int) $this->itemOrSkip('bois')->id))->add_item($client, 2);
        (new Item((int) $this->itemOrSkip('pierre')->id))->add_item($client, 1);
        $this->itemOrSkip('or')->add_item($client, 3);

        (new RepairService())->repairWithResources((int) $client->id, $instanceId);

        $this->assertSame(0, (int) $client->get_gold());
        $this->assertSame(0, (int) $this->link->fetchOne('SELECT COUNT(*) FROM players_bonus WHERE player_id = ? AND name = ?', [$entityId, 'pv']), 'no wear left');
        $this->assertSame([], (new RepairService())->listRepairable((int) $client->id));
    }

    public function testAnEmptyPurseRepairsNothing(): void
    {
        [$client, $instanceId] = $this->wornExemplar(wear: 200);

        try {
            (new RepairService())->repairWithGold((int) $client->id, $instanceId);
            $this->fail('no gold, no repair');
        } catch (\RuntimeException $e) {
            $this->assertSame('Pas assez d\'or.', $e->getMessage());
        }
    }

    public function testABrokenExemplarIsRecycledNotRepaired(): void
    {
        [$client, $instanceId] = $this->wornExemplar(wear: 201);
        $service = new RepairService();

        $this->assertSame([], $service->listRepairable((int) $client->id), 'broken: past repair');
        $this->assertSame(['bois' => 2, 'pierre' => 1], $service->listBroken((int) $client->id)[0]['refund']);

        $service->recycle((int) $client->id, $instanceId);

        $this->assertSame(2, (int) (new Item((int) $this->itemOrSkip('bois')->id))->get_n($client, includeInstances: false));
        $this->assertSame(1, (int) $this->link->fetchOne('SELECT destroyed FROM item_instances WHERE id = ?', [$instanceId]));
        $this->assertSame([], $service->listBroken((int) $client->id), 'the wreck is gone from the bag');
    }

    /**
     * A bag holding one exemplar of a 201 PV item whose recipe is
     * 8 bois + 4 pierre, worn down by $wear.
     *
     * @return array{0: \Classes\Player, 1: int, 2: int} client, instance id, entity id
     */
    private function wornExemplar(int $wear): array
    {
        $bois = $this->itemOrSkip('bois');
        $pierre = $this->itemOrSkip('pierre');
        $item = $this->sowCatalogItem('marteau_atelier_test', ['type' => 'equipement', 'durability_max' => 201, 'price' => 100]);

        $this->link->insert('craft_recipes', ['name' => 'marteau_atelier_test']);
        $this->recipeId = (int) $this->link->lastInsertId();
        $this->link->insert('craft_recipes_ingredients', ['recipe_id' => $this->recipeId, 'item_id' => (int) $bois->id, 'count' => 8]);
        $this->link->insert('craft_recipes_ingredients', ['recipe_id' => $this->recipeId, 'item_id' => (int) $pierre->id, 'count' => 4]);
        $this->link->insert('craft_recipes_results', ['recipe_id' => $this->recipeId, 'item_id' => (int) $item->id, 'count' => 1]);

        $client = $this->createRealPlayer('ForgeronAtelier');
        $instanceId = (new ItemInstanceService())->create((int) $client->id, (int) $item->id, (int) $client->id, '');
        $entityId = (int) $this->link->fetchOne('SELECT entity_id FROM item_instances WHERE id = ?', [$instanceId]);
        $this->link->insert('players_bonus', ['player_id' => $entityId, 'name' => 'pv', 'n' => -$wear]);

        return [$client, $instanceId, $entityId];
    }
}
