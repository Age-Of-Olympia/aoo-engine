<?php

namespace Tests\Various;

use App\Factory\PlayerFactory;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Characterization test pinning Player::have_option / add_option / end_option
 * / get_options behaviour ahead of the Phase 2 PlayerOptionsService
 * extraction.
 *
 * Locks the observable behaviour of the generic
 * Player::have/add/end/get($table, $name) god-method
 * (Classes/Player.php:467-568) so that when the extraction lands, the
 * same assertions pass against the new PlayerOptionsService unchanged.
 *
 * Covers the four branches the roadmap calls out (option missing,
 * option exists, duplicate add, end on absent) plus get_options sort
 * order. The historic isMerchant → marchand follower side effect is
 * GONE with the counter roles (the building's dialogue carries them,
 * Version20260806150000) — pinned by its inverse below: options are a
 * plain SQL wrapper now, no hidden hooks.
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
    public function testOptionsCarryNoFollowerSideEffectAnymore(): void
    {
        // L'inverse de l'ancien piège : le crochet isMerchant → suiveur
        // « marchand » est parti avec les rôles de comptoir (le dialogue
        // du bâtiment fait foi). Poser/retirer une option ne touche plus
        // aucune autre table.
        $this->link->executeStatement(
            "DELETE FROM players_followers WHERE player_id = ? AND name = 'marchand'",
            [$this->playerId]
        );

        $player = PlayerFactory::legacy($this->playerId);
        $player->get_data();

        $player->add_option('isMerchant');
        $this->assertSame(
            0,
            $this->marchandFollowerCount(),
            'add_option ne crée plus de suiveur : les options sont un pur wrapper SQL'
        );

        $player->end_option('isMerchant');
        $this->assertSame(0, $this->marchandFollowerCount());
    }

    /**
     * Le suivant porte son nom LUI-MÊME.
     *
     * Il pointait auparavant vers une ligne de `map_foregrounds`, ce qui le
     * rendait indiscernable d'un décor posé par un animateur — et le rendre
     * en effaçait parfois un. Le compte se lit donc sans jointure.
     */
    private function marchandFollowerCount(): int
    {
        return (int) $this->link->fetchOne(
            "SELECT COUNT(*) FROM players_followers WHERE player_id = ? AND name = 'marchand'",
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
