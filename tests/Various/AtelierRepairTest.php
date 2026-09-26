<?php

namespace Tests\Various;

use App\Service\ItemInstanceService;
use App\Service\RepairService;
use Classes\Item;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The atelier's counter: a repair costs a share of the object's worth
 * (a quarter at most) that follows the wear, converted into whole
 * resources of the recipe plus one of the object's race — or all in gold
 * with a margin; a broken exemplar is not repaired but recycled for a
 * quarter of its resources.
 */
#[Group('items-baseline')]
class AtelierRepairTest extends LegacyPlayerFixtureTestCase
{
    /** @var int[] */
    private array $recipeIds = [];

    protected function tearDown(): void
    {
        foreach ($this->recipeIds as $recipeId) {
            foreach (['craft_recipes_ingredients', 'craft_recipes_results'] as $table) {
                $this->link->executeStatement("DELETE FROM {$table} WHERE recipe_id = ?", [$recipeId]);
            }
            $this->link->executeStatement('DELETE FROM craft_recipes WHERE id = ?', [$recipeId]);
        }
        /* A recycled wreck keeps its rows (destroyed, shelved): clear them
         * before the catalog row they point at goes. */
        $entities = $this->link->fetchFirstColumn(
            "SELECT i.entity_id FROM item_instances i JOIN items it ON it.id = i.item_id WHERE it.name = 'baton_atelier_test'"
        );
        $this->link->executeStatement("DELETE i FROM item_instances i JOIN items it ON it.id = i.item_id WHERE it.name = 'baton_atelier_test'");
        foreach ($entities as $entityId) {
            $this->link->executeStatement('DELETE FROM players_bonus WHERE player_id = ?', [(int) $entityId]);
            $this->link->executeStatement('DELETE FROM players WHERE id = ?', [(int) $entityId]);
        }
        parent::tearDown();
    }

    public function testTheBillFollowsTheWearAndTheWorth(): void
    {
        [$client, $instanceId, $entityId] = $this->wornExemplar(wear: 200);
        $service = new RepairService();

        /* Worth 235, down to 1 PV of 201: ceil(235 × 0.25 × 200/201) = 59
         * → one rare (50), then one tourbe (5), 4 PO forgiven; plus one
         * cendre, the elven resource; gold = ceil((59 + 15) × 1.5). */
        $quote = $service->listRepairable((int) $client->id)[0]['quote'];
        $rares = array_intersect_key($quote['resources'], array_flip(['salpetre_atelier_test', 'mana_atelier_test', 'cuir_atelier_test']));
        $this->assertSame([1], array_values($rares), 'one rare, drawn among the three');
        $this->assertSame(1, $quote['resources']['tourbe_atelier_test']);
        $this->assertSame(1, $quote['resources']['cendre_atelier_test'], 'the racial resource');
        $this->assertCount(3, $quote['resources']);
        $this->assertSame(111, $quote['gold']);
        $this->assertSame($quote, $service->listRepairable((int) $client->id)[0]['quote'], 'the draw is replayable: the list and the click agree');

        /* Half the wear: ceil(235 × 0.25 × 100/201) = 30 → two cendre
         * (15 each, no rare affordable), plus the racial one. */
        $this->link->executeStatement('UPDATE players_bonus SET n = -100 WHERE player_id = ? AND name = ?', [$entityId, 'pv']);
        $half = $service->listRepairable((int) $client->id)[0]['quote'];
        $this->assertSame(['cendre_atelier_test' => 3], $half['resources']);
        $this->assertSame(68, $half['gold'], 'ceil((30 + 15) × 1.5)');

        /* The knobs are admin settings, read on every quote. */
        (new \App\Service\AdminSettingsService())->set('repair_full_share', '50');
        try {
            $this->assertSame(111, $service->listRepairable((int) $client->id)[0]['quote']['gold'], 'half the wear at 50 % = a quarter');
        } finally {
            $this->link->executeStatement("DELETE FROM admin_settings WHERE name = 'repair_full_share'");
        }
    }

    public function testResourcesPayTheRepairAndRestoreFullLife(): void
    {
        [$client, $instanceId, $entityId] = $this->wornExemplar(wear: 200);
        foreach ((new RepairService())->quote($this->heldRow($client, $instanceId))['resources'] as $name => $count) {
            Item::get_item_by_name($name)->add_item($client, $count);
        }

        (new RepairService())->repairWithResources((int) $client->id, $instanceId);

        $this->assertSame(0, (int) Item::get_item_by_name('cendre_atelier_test')->get_n($client, includeInstances: false), 'the bag paid');
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
        $this->assertCount(1, (new RepairService())->listRepairable((int) $client->id), 'the refused charge leaves the wear');
    }

    public function testATinyBillStillCostsOneResource(): void
    {
        [$client] = $this->wornExemplar(wear: 1);

        /* ceil(235 × 0.25 × 1/201) = 1, under every price: one tourbe, the cheapest. */
        $quote = (new RepairService())->listRepairable((int) $client->id)[0]['quote'];
        $this->assertSame(1, $quote['resources']['tourbe_atelier_test'] ?? 0);
    }

    public function testARecipeMakingTwoCountsHalfPerObject(): void
    {
        [$client] = $this->wornExemplar(wear: 201, staffYield: 2);

        /* Half the recipe per staff: 2 tourbe and 25 or, a quarter of which
         * rounds down to 0 tourbe and 6 or. */
        $this->assertSame(['or' => 6], (new RepairService())->listBroken((int) $client->id)[0]['refund']);
    }

    public function testRecyclingNeverGivesBackMoreThanTheRecipe(): void
    {
        [$client] = $this->wornExemplar(wear: 201);

        (new \App\Service\AdminSettingsService())->set('recycle_share', '200');
        try {
            $this->assertSame(
                ['tourbe_atelier_test' => 4, 'or' => 50],
                array_intersect_key((new RepairService())->listBroken((int) $client->id)[0]['refund'], ['tourbe_atelier_test' => 0, 'or' => 0])
            );
        } finally {
            $this->link->executeStatement("DELETE FROM admin_settings WHERE name = 'recycle_share'");
        }
    }

    public function testABrokenExemplarIsRecycledNotRepaired(): void
    {
        [$client, $instanceId] = $this->wornExemplar(wear: 201);
        $service = new RepairService();

        /* A quarter of the flattened recipe, rounded down: 4 tourbe → 1,
         * 50 or → 12, single units → nothing. */
        $this->assertSame([], $service->listRepairable((int) $client->id), 'broken: past repair');
        $this->assertSame(['tourbe_atelier_test' => 1, 'or' => 12], $service->listBroken((int) $client->id)[0]['refund']);

        $service->recycle((int) $client->id, $instanceId);

        $this->assertSame(1, (int) Item::get_item_by_name('tourbe_atelier_test')->get_n($client, includeInstances: false));
        $this->assertSame(12, (int) $client->get_gold());
        $this->assertSame(1, (int) $this->link->fetchOne('SELECT destroyed FROM item_instances WHERE id = ?', [$instanceId]));
        $this->assertSame([], $service->listBroken((int) $client->id), 'the wreck is gone from the bag');
    }

    /**
     * A bag holding one exemplar of an elven 201 PV staff, worn down by
     * $wear. Recipe: 1 sceptre + 1 salpêtre + 1 mana + 1 cuir + 2 tourbe;
     * the sceptre is 1 cendre + 2 tourbe + 50 or. Flattened: three rares
     * at 50, one cendre (elven, 15), four tourbe (common, 5), 50 or —
     * worth 235. $staffYield staffs per craft divide all of it.
     *
     * @return array{0: \Classes\Player, 1: int, 2: int} client, instance id, entity id
     */
    private function wornExemplar(int $wear, int $staffYield = 1): array
    {
        $this->itemOrSkip('or');
        $prices = ['salpetre' => 50, 'mana' => 50, 'cuir' => 50, 'cendre' => 15, 'tourbe' => 5];
        $ids = [];
        foreach ($prices as $name => $price) {
            $ids[$name] = (int) $this->sowCatalogItem("{$name}_atelier_test", ['type' => 'matiere', 'price' => $price, 'race' => $name === 'cendre' ? 'elfe' : 'common'])->id;
        }
        $ids['sceptre'] = (int) $this->sowCatalogItem('sceptre_atelier_test', ['type' => 'equipement', 'price' => 1])->id;
        $staffId = (int) $this->sowCatalogItem('baton_atelier_test', ['type' => 'equipement', 'race' => 'elfe', 'durability_max' => 201, 'price' => 1])->id;
        $orId = (int) Item::get_item_by_name('or')->id;

        $this->sowRecipe($ids['sceptre'], [$ids['cendre'] => 1, $ids['tourbe'] => 2, $orId => 50]);
        $this->sowRecipe($staffId, [$ids['sceptre'] => 1, $ids['salpetre'] => 1, $ids['mana'] => 1, $ids['cuir'] => 1, $ids['tourbe'] => 2], $staffYield);

        $client = $this->createRealPlayer('ForgeronAtelier');
        $instanceId = (new ItemInstanceService())->create((int) $client->id, $staffId, (int) $client->id, '');
        $entityId = (int) $this->link->fetchOne('SELECT entity_id FROM item_instances WHERE id = ?', [$instanceId]);
        $this->link->insert('players_bonus', ['player_id' => $entityId, 'name' => 'pv', 'n' => -$wear]);

        return [$client, $instanceId, $entityId];
    }

    /** @param array<int, int> $ingredients item id => count */
    private function sowRecipe(int $resultId, array $ingredients, int $yield = 1): void
    {
        $this->link->insert('craft_recipes', ['name' => (string) $this->link->fetchOne('SELECT name FROM items WHERE id = ?', [$resultId])]);
        $recipeId = (int) $this->link->lastInsertId();
        $this->recipeIds[] = $recipeId;
        foreach ($ingredients as $itemId => $count) {
            $this->link->insert('craft_recipes_ingredients', ['recipe_id' => $recipeId, 'item_id' => $itemId, 'count' => $count]);
        }
        $this->link->insert('craft_recipes_results', ['recipe_id' => $recipeId, 'item_id' => $resultId, 'count' => $yield]);
    }

    /** @return array<string, mixed> */
    private function heldRow(\Classes\Player $client, int $instanceId): array
    {
        foreach ((new RepairService())->listRepairable((int) $client->id) as $row) {
            if ((int) $row['instance_id'] === $instanceId) {
                return $row;
            }
        }
        $this->fail('exemplar not in the bag');
    }
}
