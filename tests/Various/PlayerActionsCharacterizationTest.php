<?php

namespace Tests\Various;

use App\Factory\PlayerFactory;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Characterization test pinning Player::have_action / add_action /
 * end_action / get_actions behaviour ahead of the Phase 2 sibling
 * PlayerActionsService extraction.
 *
 * The actions branch has two traits options didn't:
 *
 *   1. `players_actions` has PRIMARY KEY (player_id, name), so a
 *      duplicate add throws a mysqli_sql_exception (strict mode is
 *      globally enabled in Classes\Db::__construct).
 *
 *   2. `Player::add()` does an ActionService lookup for names other
 *      than 'attaquer'. If the action's ormType is 'spell' or
 *      'technique', the row goes in with `type='sort'` instead of the
 *      default empty string. Dropping this in the extraction would
 *      silently break spell availability for every player who relearns
 *      a sort or technique.
 *
 * Requires an initialized aoo4 DB with at least one real player. Skips
 * cleanly otherwise. Every test wraps its mutations in a transaction
 * rolled back in tearDown; setUp wipes the test player's existing
 * players_actions rows inside the transaction so add_action calls
 * don't collide with seeded PK rows.
 */
class PlayerActionsCharacterizationTest extends TestCase
{
    private ?Connection $link = null;
    private int $playerId = 0;
    private string $unknownActionName = '';

    protected function setUp(): void
    {
        $this->bootstrapOrSkip();
        $this->link->beginTransaction();

        // Clear the test player's existing actions inside the transaction
        // so tests have predictable starting state without fighting
        // seeded PRIMARY KEY rows.
        $this->link->executeStatement(
            'DELETE FROM players_actions WHERE player_id = ?',
            [$this->playerId]
        );

        $this->unknownActionName = 'phase2bChar_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if ($this->link !== null && $this->link->isTransactionActive()) {
            $this->link->rollBack();
        }
        $this->link = null;
    }

    #[Group('player-actions-characterization')]
    #[Group('dismantling-phase-2b')]
    public function testHaveActionReturnsZeroWhenActionAbsent(): void
    {
        $player = PlayerFactory::legacy($this->playerId);

        $this->assertSame(0, $player->have_action($this->unknownActionName));
    }

    #[Group('player-actions-characterization')]
    #[Group('dismantling-phase-2b')]
    public function testAddActionMakesHaveActionReturnOne(): void
    {
        $player = PlayerFactory::legacy($this->playerId);

        $player->add_action($this->unknownActionName);

        $this->assertSame(1, $player->have_action($this->unknownActionName));
    }

    #[Group('player-actions-characterization')]
    #[Group('dismantling-phase-2b')]
    public function testDuplicateAddActionThrowsOnPrimaryKeyConflict(): void
    {
        // players_actions has PRIMARY KEY (player_id, name). A second
        // INSERT of the same row would silently succeed without strict
        // mode — but Classes\Db sets mysqli_report(MYSQLI_REPORT_ERROR |
        // MYSQLI_REPORT_STRICT) in its constructor, so duplicates
        // throw. Any future service that catches/masks this changes
        // observable behaviour.
        $player = PlayerFactory::legacy($this->playerId);
        $player->add_action($this->unknownActionName);

        $this->expectException(\mysqli_sql_exception::class);
        $player->add_action($this->unknownActionName);
    }

    #[Group('player-actions-characterization')]
    #[Group('dismantling-phase-2b')]
    public function testEndActionOnAbsentRowIsNoOp(): void
    {
        $player = PlayerFactory::legacy($this->playerId);

        $player->end_action($this->unknownActionName);

        $this->assertSame(0, $player->have_action($this->unknownActionName));
    }

    #[Group('player-actions-characterization')]
    #[Group('dismantling-phase-2b')]
    public function testEndActionRemovesExistingRow(): void
    {
        $player = PlayerFactory::legacy($this->playerId);

        $player->add_action($this->unknownActionName);
        $player->end_action($this->unknownActionName);

        $this->assertSame(0, $player->have_action($this->unknownActionName));
    }

    #[Group('player-actions-characterization')]
    #[Group('dismantling-phase-2b')]
    public function testGetActionsReflectsAdditionAndReturnsSortedList(): void
    {
        $player = PlayerFactory::legacy($this->playerId);

        $before = $player->get_actions();
        $this->assertNotContains($this->unknownActionName, $before);

        $player->add_action($this->unknownActionName);

        $after = $player->get_actions();
        $this->assertContains($this->unknownActionName, $after);

        $sorted = $after;
        sort($sorted);
        $this->assertSame($sorted, $after, 'get_actions must return an ascending sort');
    }

    #[Group('player-actions-characterization')]
    #[Group('dismantling-phase-2b')]
    public function testAddActionWithSpellNameSetsTypeSort(): void
    {
        // 'dmg1/pic_de_pierre' is seeded in `actions` with ormType='spell'.
        // Player::add()'s ActionService lookup must persist type='sort'.
        $player = PlayerFactory::legacy($this->playerId);

        $player->add_action('dmg1/pic_de_pierre');

        $this->assertSame('sort', $this->fetchActionType('dmg1/pic_de_pierre'));
    }

    #[Group('player-actions-characterization')]
    #[Group('dismantling-phase-2b')]
    public function testAddActionWithTechniqueNameSetsTypeSort(): void
    {
        // 'epuisement' is seeded in `actions` with ormType='technique'.
        // Same 'sort' storage type — the branch covers both in one arm.
        $player = PlayerFactory::legacy($this->playerId);

        $player->add_action('epuisement');

        $this->assertSame('sort', $this->fetchActionType('epuisement'));
    }

    #[Group('player-actions-characterization')]
    #[Group('dismantling-phase-2b')]
    public function testAddActionWithUnknownNameDefaultsTypeToEmptyString(): void
    {
        // getActionByName returns null for a name absent from `actions`,
        // so `$values['type']` is never set and the column default
        // kicks in. Callers rely on this: add_action creates a "plain"
        // row that the spell/technique-specific UI branches ignore.
        $player = PlayerFactory::legacy($this->playerId);

        $player->add_action($this->unknownActionName);

        $this->assertSame('', $this->fetchActionType($this->unknownActionName));
    }

    private function fetchActionType(string $name): string
    {
        $row = $this->link->fetchAssociative(
            'SELECT type FROM players_actions WHERE player_id = ? AND name = ?',
            [$this->playerId, $name]
        );

        $this->assertNotFalse($row, "players_actions row for {$name} not found");

        return (string) $row['type'];
    }

    private function bootstrapOrSkip(): void
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/functions.php';
            require_once __DIR__ . '/../../config/constants.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy bootstrap failed: ' . $e->getMessage());
        }

        global $link;
        if (!isset($link) || !$link instanceof Connection) {
            $this->markTestSkipped('Global $link not populated by bootstrap.');
        }

        try {
            $link->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy DB unreachable: ' . $e->getMessage());
        }

        try {
            $row = $link->fetchAssociative(
                "SELECT id FROM players WHERE id > 0 AND (player_type IS NULL OR player_type = 'real') ORDER BY id ASC LIMIT 1"
            );
        } catch (\Throwable $e) {
            $this->markTestSkipped('players table unreadable: ' . $e->getMessage());
        }

        if (empty($row['id'])) {
            $this->markTestSkipped(
                'No real player row available — run scripts/testing/reset_test_database.sh.'
            );
        }

        // Sanity-check the spell/technique fixture rows are seeded. If
        // someone's reset_test_database.sh didn't run the actions seed,
        // skip loudly rather than fail mysteriously.
        try {
            $actions = $link->fetchFirstColumn(
                "SELECT name FROM actions WHERE name IN ('dmg1/pic_de_pierre', 'epuisement')"
            );
        } catch (\Throwable $e) {
            $this->markTestSkipped('actions table unreadable: ' . $e->getMessage());
        }

        if (count($actions) < 2) {
            $this->markTestSkipped(
                'Spell/technique fixture rows missing — reseed the DB.'
            );
        }

        $this->link = $link;
        $this->playerId = (int) $row['id'];
    }
}
