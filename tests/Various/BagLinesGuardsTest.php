<?php

namespace Tests\Various;

use App\Enum\EquipResult;
use App\Service\ItemInstanceService;
use App\Service\RecipeService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The bag-lines rule reaches the legacy flows: taking from the bank
 * and crafting ask the bag the same question as the chest and the
 * ground. The craft guard also knows a line WHOLLY consumed frees its
 * slot before the outputs land.
 */
#[Group('items-baseline')]
class BagLinesGuardsTest extends LegacyPlayerFixtureTestCase
{
    private ?int $recipeId = null;

    protected function tearDown(): void
    {
        if ($this->recipeId !== null) {
            foreach (['craft_recipes_ingredients', 'craft_recipes_results'] as $table) {
                $this->link->executeStatement("DELETE FROM {$table} WHERE recipe_id = ?", [$this->recipeId]);
            }
            $this->link->executeStatement('DELETE FROM craft_recipes WHERE id = ?', [$this->recipeId]);
            $this->recipeId = null;
        }
        parent::tearDown();
    }

    /** A basic recipe (no workshop): bois in, pierre out. */
    private function boisToPierreRecipe(int $boisId, int $pierreId, int $eats): object
    {
        $this->link->executeStatement("INSERT INTO craft_recipes (name) VALUES ('garde_sac_test')");
        $this->recipeId = (int) $this->link->lastInsertId();
        $this->link->executeStatement(
            'INSERT INTO craft_recipes_ingredients (recipe_id, item_id, count) VALUES (?, ?, ?)',
            [$this->recipeId, $boisId, $eats]
        );
        $this->link->executeStatement(
            'INSERT INTO craft_recipes_results (recipe_id, item_id, count) VALUES (?, ?, 1)',
            [$this->recipeId, $pierreId]
        );

        return (new RecipeService())->getRecipeById($this->recipeId);
    }

    private function withBagCeiling(int $playerId, int $ceiling, callable $body): void
    {
        $race = (string) $this->link->fetchOne('SELECT race FROM players WHERE id = ?', [$playerId]);
        $before = (int) $this->link->fetchOne('SELECT capacity FROM races WHERE name = ?', [$race]);
        $this->link->executeStatement('UPDATE races SET capacity = ? WHERE name = ?', [$ceiling, $race]);
        $this->refreshRaceCatalog();

        try {
            $body();
        } finally {
            $this->link->executeStatement('UPDATE races SET capacity = ? WHERE name = ?', [$before, $race]);
            $this->refreshRaceCatalog();
        }
    }

    public function testCraftRefusesWhenTheOutputWouldNotFit(): void
    {
        $bois = $this->itemOrSkip('bois');
        $pierre = $this->itemOrSkip('pierre');
        $crafter = $this->createRealPlayer('GmSacCraft');
        $bois->add_item($crafter, 5);

        // Eats 2 of 5 bois: the line stays, the pierre needs a new one.
        $recipe = $this->boisToPierreRecipe((int) $bois->id, (int) $pierre->id, 2);

        $this->withBagCeiling((int) $crafter->id, 1, function () use ($recipe, $crafter, $bois, $pierre) {
            $message = '';
            $this->assertFalse((new RecipeService())->TryCraftRecipe($recipe, $crafter, $message));
            $this->assertSame('Votre sac est plein.', $message);
            $this->assertSame(5, $bois->get_n($crafter), 'refused BEFORE eating the ingredients');
            $this->assertSame(0, $pierre->get_n($crafter));
        });
    }

    public function testCraftFitsWhenAWholeLineFrees(): void
    {
        $bois = $this->itemOrSkip('bois');
        $pierre = $this->itemOrSkip('pierre');
        $crafter = $this->createRealPlayer('GmSacLibre');
        $bois->add_item($crafter, 2);

        // Eats the WHOLE bois line: its slot frees for the pierre.
        $recipe = $this->boisToPierreRecipe((int) $bois->id, (int) $pierre->id, 2);

        $this->withBagCeiling((int) $crafter->id, 1, function () use ($recipe, $crafter, $bois, $pierre) {
            $message = '';
            $this->assertTrue((new RecipeService())->TryCraftRecipe($recipe, $crafter, $message), $message);
            $this->assertSame(0, $bois->get_n($crafter));
            $this->assertSame(1, $pierre->get_n($crafter));
        });
    }

    public function testBankExemplarWithdrawAsksTheBag(): void
    {
        $bois = $this->itemOrSkip('bois');
        $gladius = $this->itemOrSkip('gladius');
        $player = $this->createRealPlayer('GmSacBanque');

        $service = new ItemInstanceService();
        $instanceId = $service->create((int) $player->id, (int) $gladius->id, (int) $player->id, '');
        $service->storeInBank($instanceId, (int) $player->id);

        $bois->add_item($player, 3);

        $this->withBagCeiling((int) $player->id, 1, function () use ($service, $instanceId, $player, $gladius) {
            try {
                $service->withdrawFromBank($instanceId, (int) $player->id);
                $this->fail('a full bag must refuse the exemplar');
            } catch (\InvalidArgumentException $e) {
                $this->assertSame('Votre sac est plein.', $e->getMessage());
            }
            $this->assertSame(0, $service->countInstances((int) $player->id, (int) $gladius->id), 'still banked');
        });

        // Ceiling restored: the exemplar comes home.
        $service->withdrawFromBank($instanceId, (int) $player->id);
        $this->assertSame(1, $service->countInstances((int) $player->id, (int) $gladius->id));
    }

    public function testUnequipAsksTheBag(): void
    {
        $spec = ['type' => 'equipement', 'stats_in_db' => 1, 'durability_max' => 100, 'emplacement' => 'main1'];
        $sword = $this->sowCatalogItem('zz_epee_sac_' . bin2hex(random_bytes(3)), $spec);
        $bois = $this->itemOrSkip('bois');
        $player = $this->createRealPlayer('GmSacDeseq');
        $sword->add_item($player, 1);
        $bois->add_item($player, 3);
        $player->get_caracs();
        $this->assertSame(EquipResult::Equip, $player->equip($sword));

        $this->withBagCeiling((int) $player->id, 1, function () use ($player, $sword) {
            $this->assertSame(EquipResult::BagFull, $player->equip($sword, bagGuard: true));
            $this->assertSame(EquipResult::Unequip, $player->equip($sword), 'a system unequip always passes');
        });
    }

    public function testUnequipMeltingIntoItsStackNeedsNoRoom(): void
    {
        $spec = ['type' => 'equipement', 'stats_in_db' => 1, 'durability_max' => 100, 'emplacement' => 'main1'];
        $sword = $this->sowCatalogItem('zz_epee_pile_' . bin2hex(random_bytes(3)), $spec);
        $player = $this->createRealPlayer('GmSacPile');
        $sword->add_item($player, 2);
        $player->get_caracs();
        $this->assertSame(EquipResult::Equip, $player->equip($sword));

        // The stack of the other sword is the only line: the intact one goes back into it.
        $this->withBagCeiling((int) $player->id, 1, function () use ($player, $sword) {
            $this->assertSame(EquipResult::Unequip, $player->equip($sword, bagGuard: true));
        });
    }

    /**
     * A worn sword, another sword type in the bag, the bag filled to its ceiling.
     *
     * @return array{\Classes\Player, \Classes\Item, \Classes\Item, int}
     */
    private function swapSetup(string $name, int $spares): array
    {
        $spec = ['type' => 'equipement', 'stats_in_db' => 1, 'durability_max' => 100, 'emplacement' => 'main1'];
        $worn = $this->sowCatalogItem('zz_porte_' . bin2hex(random_bytes(3)), $spec);
        $spare = $this->sowCatalogItem('zz_rechange_' . bin2hex(random_bytes(3)), $spec);
        $player = $this->createRealPlayer($name);
        $worn->add_item($player, 1);
        $spare->add_item($player, $spares);
        $player->get_caracs();
        $this->assertSame(EquipResult::Equip, $player->equip($worn));
        $lines = (new \App\Service\ContainerService())->lineCountOf((int) $player->id);

        return [$player, $worn, $spare, $lines];
    }

    public function testSwapRefusedWhenTheNewItemComesFromAPileThatStays(): void
    {
        [$player, $worn, $spare, $lines] = $this->swapSetup('GmSacEchangeRefus', 2);

        $this->withBagCeiling((int) $player->id, $lines, function () use ($player, $spare) {
            $this->assertSame(EquipResult::BagFull, $player->equip($spare, bagGuard: true));
        });
        $this->assertSame('main1', $this->slotOfInstance($this->instanceHeldBy((int) $player->id, (int) $worn->id)), 'the worn sword stays worn');
    }

    public function testSwapAllowedWhenTheNewItemFreesItsLine(): void
    {
        [$player, $worn, $spare, $lines] = $this->swapSetup('GmSacEchangeOk', 1);

        $this->withBagCeiling((int) $player->id, $lines, function () use ($player, $spare) {
            $this->assertSame(EquipResult::Equip, $player->equip($spare, bagGuard: true));
        });
    }

    public function testWhatDoesNotFitFallsAtThePlayersFeet(): void
    {
        $bois = $this->itemOrSkip('bois');
        $pierre = $this->itemOrSkip('pierre');
        $player = $this->createRealPlayer('GmSacAuSol');
        $player->get_data();
        $bois->add_item($player, 3);
        $cell = (int) $player->data->coords_id;
        $onGround = fn (int $itemId): int => (int) $this->link->fetchOne(
            'SELECT COALESCE(SUM(n), 0) FROM map_items WHERE coords_id = ? AND item_id = ?',
            [$cell, $itemId]
        );
        $groundBefore = $onGround((int) $pierre->id);

        $this->withBagCeiling((int) $player->id, 1, function () use ($player, $bois, $pierre, $onGround, $groundBefore) {
            $this->assertTrue(\App\Service\GroundLootService::giveOrDrop($player, $pierre, 2), 'a new line does not fit');
            $this->assertSame(0, $pierre->get_n($player));
            $this->assertSame($groundBefore + 2, $onGround((int) $pierre->id));

            $this->assertFalse(\App\Service\GroundLootService::giveOrDrop($player, $bois, 2), 'joining its pile needs no line');
            $this->assertSame(5, $bois->get_n($player));
        });

        $this->link->executeStatement('DELETE FROM map_items WHERE coords_id = ? AND item_id = ?', [$cell, $pierre->id]);
    }
}
