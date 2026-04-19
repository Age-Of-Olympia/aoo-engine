<?php

namespace Tests\Various;

use App\Factory\PlayerFactory;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Characterization test pinning Player::have_option / add_option / end_option
 * / get_options behaviour ahead of the Phase 2 PlayerOptionsService
 * extraction (docs/player-dismantling-roadmap.md §Phase 2).
 *
 * Locks the observable behaviour of the generic
 * Player::have/add/end/get($table, $name) god-method
 * (Classes/Player.php:467-568) so that when the extraction lands, the
 * same assertions pass against the new PlayerOptionsService unchanged.
 *
 * Covers the four branches the roadmap calls out (option missing,
 * option exists, duplicate add, end on absent) plus get_options sort
 * order and the hidden-trap isMerchant → marchand follower side effect
 * (Player.php:519-527, :545-548). Any extraction that treats options
 * as a "thin SQL wrapper" and drops the follower hook silently breaks
 * merchant state in prod.
 *
 * Requires an initialized aoo4 DB with at least one real player. Skips
 * cleanly otherwise. Every test wraps its mutations in a transaction
 * rolled back in tearDown — Classes\Db uses the same underlying
 * mysqli connection Doctrine manages, so the rollback undoes both
 * Doctrine and legacy writes in one go.
 */
class PlayerOptionsCharacterizationTest extends TestCase
{
    private ?Connection $link = null;
    private int $playerId = 0;
    private string $optionName = '';

    protected function setUp(): void
    {
        $this->bootstrapOrSkip();
        $this->link->beginTransaction();

        // Per-test random name so that even if the rollback misbehaves on
        // a weird engine configuration, the next run sees a clean row space.
        $this->optionName = 'phase2Char_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if ($this->link !== null && $this->link->isTransactionActive()) {
            $this->link->rollBack();
        }
        $this->link = null;
    }

    #[Group('player-options-characterization')]
    #[Group('dismantling-phase-2')]
    public function testHaveOptionReturnsZeroWhenOptionAbsent(): void
    {
        $player = PlayerFactory::legacy($this->playerId);

        $this->assertSame(0, $player->have_option($this->optionName));
    }

    #[Group('player-options-characterization')]
    #[Group('dismantling-phase-2')]
    public function testAddOptionMakesHaveOptionReturnPositive(): void
    {
        $player = PlayerFactory::legacy($this->playerId);

        $player->add_option($this->optionName);

        $this->assertGreaterThanOrEqual(1, $player->have_option($this->optionName));
    }

    #[Group('player-options-characterization')]
    #[Group('dismantling-phase-2')]
    public function testDuplicateAddYieldsCountOfTwo(): void
    {
        // The schema has no UNIQUE(player_id, name) on players_options, so
        // add_option is insert-on-duplicate. Callers today rely on
        // have_option returning the count (int), not a bool — any service
        // that changes that contract breaks them.
        $player = PlayerFactory::legacy($this->playerId);

        $player->add_option($this->optionName);
        $player->add_option($this->optionName);

        $this->assertSame(2, $player->have_option($this->optionName));
    }

    #[Group('player-options-characterization')]
    #[Group('dismantling-phase-2')]
    public function testEndOptionOnAbsentRowIsNoOp(): void
    {
        $player = PlayerFactory::legacy($this->playerId);

        $player->end_option($this->optionName);

        $this->assertSame(0, $player->have_option($this->optionName));
    }

    #[Group('player-options-characterization')]
    #[Group('dismantling-phase-2')]
    public function testEndOptionRemovesExistingRow(): void
    {
        $player = PlayerFactory::legacy($this->playerId);

        $player->add_option($this->optionName);
        $player->end_option($this->optionName);

        $this->assertSame(0, $player->have_option($this->optionName));
    }

    #[Group('player-options-characterization')]
    #[Group('dismantling-phase-2')]
    public function testGetOptionsReflectsAdditionAndReturnsSortedList(): void
    {
        $player = PlayerFactory::legacy($this->playerId);

        $before = $player->get_options();
        $this->assertNotContains($this->optionName, $before);

        $player->add_option($this->optionName);

        $after = $player->get_options();
        $this->assertContains($this->optionName, $after);

        $sorted = $after;
        sort($sorted);
        $this->assertSame($sorted, $after, 'get_options must return an ascending sort');
    }

    #[Group('player-options-characterization')]
    #[Group('dismantling-phase-2')]
    public function testIsMerchantOptionManagesMarchandFollowerSideEffect(): void
    {
        // Clean pre-existing isMerchant state inside the transaction so
        // the add/end deltas are unambiguous. Rollback restores the
        // original rows after the test.
        $this->link->executeStatement(
            "DELETE FROM players_followers WHERE player_id = ? AND foreground_id IN (SELECT id FROM map_foregrounds WHERE name = 'marchand')",
            [$this->playerId]
        );
        $this->link->executeStatement(
            "DELETE FROM players_options WHERE player_id = ? AND name = 'isMerchant'",
            [$this->playerId]
        );

        $player = PlayerFactory::legacy($this->playerId);
        $player->get_data();

        $this->assertSame(0, $this->marchandFollowerCount());

        $player->add_option('isMerchant');
        $this->assertSame(
            1,
            $this->marchandFollowerCount(),
            'add_option(isMerchant) must create a marchand follower row'
        );

        $player->end_option('isMerchant');
        $this->assertSame(
            0,
            $this->marchandFollowerCount(),
            'end_option(isMerchant) must delete the marchand follower row'
        );
    }

    private function marchandFollowerCount(): int
    {
        return (int) $this->link->fetchOne(
            "SELECT COUNT(*) FROM players_followers AS f
             INNER JOIN map_foregrounds AS m ON f.foreground_id = m.id
             WHERE f.player_id = ? AND m.name = 'marchand'",
            [$this->playerId]
        );
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

        $this->link = $link;
        $this->playerId = (int) $row['id'];
    }
}
