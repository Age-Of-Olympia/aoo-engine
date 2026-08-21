<?php

namespace Tests\Action;

use App\Factory\ActionFactory;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use Classes\Player;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Chests and the bank (chest & bank design):
 *
 * - a lockable container can only be built on a plan where a
 *   FINISHED bank stands (items.requires_building → RequiresPlanBuilding);
 * - the builder chooses the owner (POST buildFor) — personal keeps
 *   today's owner, faction hands the chest to their faction, and
 *   demands one (ChestSite);
 * - a floor may forbid chests (plan_z_levels.chests_allowed);
 * - an édifice is a faction's work (RequiresFaction {scope: edifice})
 *   while palissades and walls stay free for the lone builder.
 *
 * Every scene stands on its own plan: the bank rule reads the WHOLE
 * plan, a leftover bank on gaia would fake the pass.
 */
#[Group('items-baseline')]
class ChestUnderTheBankTest extends LegacyPlayerFixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBuildingsOrSkip();
    }

    /** @var list<string> */
    private array $sownFactionCodes = [];

    protected function tearDown(): void
    {
        unset($_POST['itemId'], $_POST['buildFor']);
        foreach ($this->sownFactionCodes as $code) {
            $this->link->executeStatement('DELETE FROM factions WHERE code = ?', [$code]);
        }
        $this->sownFactionCodes = [];
        parent::tearDown();
    }

    /** A faction catalogue row for the scene — place() validates the code. */
    private function sowFaction(string $code): void
    {
        if ((bool) $this->link->fetchOne('SELECT 1 FROM factions WHERE code = ?', [$code])) {
            return;
        }
        $this->link->insert('factions', ['code' => $code, 'name' => ucfirst($code), 'raFont' => '']);
        $this->sownFactionCodes[] = $code;
    }

    private function actionOrSkip(): \App\Interface\ActionInterface
    {
        $action = ActionFactory::getAction('construire');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no generic 'construire' row — run migrations).");
        }

        $attached = $this->link->fetchOne(
            "SELECT ac.id FROM action_conditions ac
               JOIN actions a ON a.id = ac.action_id
              WHERE a.name = 'construire' AND ac.conditionType = 'ChestSite'"
        );
        if ($attached === false) {
            $this->markTestSkipped('chest conditions not attached to construire (run migrations).');
        }

        return $action;
    }

    /** A plan of its own for the scene — the bank rule reads the whole plan. */
    private function scenePlan(): string
    {
        return 'p_coffres_' . bin2hex(random_bytes(3));
    }

    /** A fresh builder teleported alone onto the scene's plan. */
    private function builderOn(string $plan): Player
    {
        $builder = $this->createRealPlayer('GmCoffreur');
        [$x, $y] = $this->farTile();
        $coordsId = (int) View::get_coords_id((object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => $plan]);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$coordsId, $builder->id]);
        (new \App\Service\Map\EntityCellService($this->link))->syncCells($builder->id);

        return $this->reload($builder->id);
    }

    /**
     * Change a fixture player's faction — raw UPDATE plus cache purge:
     * get_data() serves the per-entity .json cache before the base.
     */
    private function setFaction(int $playerId, string $faction): void
    {
        $this->link->executeStatement('UPDATE players SET faction = ? WHERE id = ?', [$faction, $playerId]);
        self::purgeEntityCache($playerId);
    }

    /** Fresh legacy object, data/coords/caracs loaded — after any raw SQL. */
    private function reload(int $playerId): Player
    {
        $player = PlayerFactory::legacy($playerId);
        $player->get_data();
        $player->getCoords();
        $player->get_caracs();

        return $player;
    }

    private function build(Player $builder, \Classes\Item $item, string $buildFor = ''): \App\Action\ActionResults
    {
        $_POST['itemId'] = (string) $item->id;
        if ($buildFor === '') {
            unset($_POST['buildFor']);
        } else {
            $_POST['buildFor'] = $buildFor;
        }

        return (new ActionExecutorService($this->actionOrSkip(), $builder, $builder))->executeAction();
    }

    /** The chest standing on the plan for this builder's scene, or null. */
    private function placedChestOn(string $plan): ?array
    {
        $row = $this->link->fetchAssociative(
            "SELECT p.id, p.owner_id, p.faction
               FROM players p
               JOIN coords c ON c.id = p.coords_id
              WHERE p.player_type = 'item' AND p.race = 'coffre_bois' AND c.plan = ?",
            [$plan]
        );
        if ($row === false) {
            return null;
        }
        $this->trackEntityId((int) $row['id']);

        return $row;
    }

    public function testAChestOnlyRisesUnderABank(): void
    {
        $plan = $this->scenePlan();
        $builder = $this->builderOn($plan);
        $chestItem = $this->itemOrSkip('coffre_bois');
        $chestItem->add_item($builder, 1);

        $results = $this->build($builder, $chestItem);

        $this->assertTrue($results->isBlocked(), 'no bank on the plan: the chest must not rise');
        $this->assertNull($this->placedChestOn($plan));
        $this->assertSame(1, $chestItem->get_n($this->reload($builder->id)), 'a blocked gesture consumes nothing');

        // A bank is placed far away on the same plan; the same action passes.
        [$bx, $by] = $this->farTile();
        $this->placeStructure('banque', $bx, $by, $plan);

        $results = $this->build($this->reload($builder->id), $chestItem);

        $this->assertFalse($results->isBlocked(), 'with a bank on the plan the chest can be built');
        $this->assertTrue($results->isSuccess());
        $placed = $this->placedChestOn($plan);
        $this->assertNotNull($placed);
        $this->assertSame($builder->id, (int) $placed['owner_id'], 'default owner: the builder owns their chest');
        $this->assertSame('', (string) $placed['faction'], 'a personal chest carries no faction');
    }

    public function testAFactionChestBelongsToTheHouseholdNotTheBuilder(): void
    {
        $plan = $this->scenePlan();
        $builder = $this->builderOn($plan);
        [$bx, $by] = $this->farTile();
        $this->placeStructure('banque', $bx, $by, $plan);

        $this->sowFaction('coffres_scene');
        $this->setFaction($builder->id, 'coffres_scene');
        $chestItem = $this->itemOrSkip('coffre_bois');
        $chestItem->add_item($builder, 1);

        $results = $this->build($this->reload($builder->id), $chestItem, 'faction');

        $this->assertFalse($results->isBlocked());
        $this->assertTrue($results->isSuccess());
        $placed = $this->placedChestOn($plan);
        $this->assertNotNull($placed);
        $this->assertNull($placed['owner_id'], 'a faction chest has no personal owner');
        $this->assertSame('coffres_scene', (string) $placed['faction'], 'the owner is the faction');
    }

    public function testAFactionlessBuilderGetsNoFactionChest(): void
    {
        $plan = $this->scenePlan();
        $builder = $this->builderOn($plan);
        [$bx, $by] = $this->farTile();
        $this->placeStructure('banque', $bx, $by, $plan);

        $this->setFaction($builder->id, '');
        $chestItem = $this->itemOrSkip('coffre_bois');
        $chestItem->add_item($builder, 1);

        $results = $this->build($this->reload($builder->id), $chestItem, 'faction');

        $this->assertTrue($results->isBlocked(), 'no faction, no faction chest');
        $this->assertNull($this->placedChestOn($plan));
    }

    public function testAForbiddenFloorRefusesTheChest(): void
    {
        $plan = $this->scenePlan();

        // The plan's ground floor forbids chests — through the same config
        // shape the bundles carry, so the round-trip is exercised too.
        (new \App\Service\PlanConfigService())->replace($plan, [
            'name' => $plan,
            'z_levels' => [['z' => 0, 'z-name' => 'Rez', 'chestsAllowed' => false]],
        ]);
        plans()->forget($plan);

        try {
            $builder = $this->builderOn($plan);
            [$bx, $by] = $this->farTile();
            $this->placeStructure('banque', $bx, $by, $plan);

            $chestItem = $this->itemOrSkip('coffre_bois');
            $chestItem->add_item($builder, 1);

            $results = $this->build($builder, $chestItem);

            $this->assertTrue($results->isBlocked(), 'the floor forbids chests, bank or not');
            $this->assertNull($this->placedChestOn($plan));
        } finally {
            $this->link->executeStatement('DELETE FROM plans WHERE slug = ?', [$plan]);
            plans()->forget($plan);
        }
    }

    public function testAnEdificeIsAFactionsWorkAPalissadeIsNot(): void
    {
        $this->sowStructureType('edifice_scene', ['structure_nature' => 'edifice']);
        $edificeItem = $this->sowCatalogItem('edifice_scene', ['type' => 'constructible']);

        $builder = $this->createRealPlayer('GmSolitaire');
        [$x, $y] = $this->farTile();
        $this->movePlayerTo($builder->id, $x, $y);
        $this->setFaction($builder->id, '');
        $builder = $this->reload($builder->id);

        $edificeItem->add_item($builder, 1);
        $results = $this->build($builder, $edificeItem);
        $this->assertTrue($results->isBlocked(), 'an édifice is a faction\'s work');

        // The lone builder still raises a palissade (obstacle, not édifice).
        $palissadeItem = $this->itemOrSkip('palissade');
        $palissadeItem->add_item($builder, 1);
        $results = $this->build($this->reload($builder->id), $palissadeItem);
        $this->assertFalse($results->isBlocked(), 'palissades and walls stay free for the factionless');
        $this->assertTrue($results->isSuccess());

        $built = (int) $this->link->fetchOne(
            'SELECT b.player_id FROM buildings b JOIN players p ON p.id = b.player_id WHERE p.owner_id = ?',
            [$builder->id]
        );
        $this->assertGreaterThan(0, $built);
        $this->trackEntityId($built);

        // With a faction, the same édifice gesture passes.
        $this->sowFaction('coffres_scene');
        $this->setFaction($builder->id, 'coffres_scene');
        $results = $this->build($this->reload($builder->id), $edificeItem);
        $this->assertFalse($results->isBlocked(), 'joined a faction: the édifice can be built');
        $this->assertTrue($results->isSuccess());

        $edifice = (int) $this->link->fetchOne(
            "SELECT b.player_id FROM buildings b JOIN players p ON p.id = b.player_id
              WHERE p.owner_id = ? AND p.race = 'edifice_scene'",
            [$builder->id]
        );
        $this->assertGreaterThan(0, $edifice);
        $this->trackEntityId($edifice);
    }
}
