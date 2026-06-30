<?php

namespace Tests\Various;

use App\Service\PlayerLoadoutService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Functional test for PlayerLoadoutService::applyLoadout — the admin loadout
 * editor's write path. Pins the two safety-critical guarantees:
 *
 *   1. Orphan-safety: an owned action with no catalog row (e.g. the base attack
 *      'attaquer') is never removed, even when the desired set is empty.
 *   2. Catalog whitelist: a desired name that is not in the catalog is never
 *      inserted into players_actions (no arbitrary-row injection from POST).
 *
 * Plus the ordinary add/remove of catalogued actions.
 *
 * Mutations run inside a transaction rolled back in tearDown. The service reads
 * and writes through Classes\Db, which shares the global Doctrine connection the
 * transaction holds, so seeded rows are visible to the service and vice versa.
 * Skips cleanly when no initialized aoo4 DB is reachable.
 */
class PlayerLoadoutServiceTest extends TestCase
{
    private ?Connection $link = null;
    private int $playerId = 0;
    private string $catalogAction = '';
    private string $orphanName = '';
    private string $bogusName = '';

    protected function setUp(): void
    {
        $this->bootstrapOrSkip();
        $this->link->beginTransaction();

        $this->link->executeStatement(
            'DELETE FROM players_actions WHERE player_id = ?',
            [$this->playerId]
        );

        $this->orphanName = 'loadoutTest_orphan_' . bin2hex(random_bytes(4));
        $this->bogusName = 'loadoutTest_bogus_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if ($this->link !== null && $this->link->isTransactionActive()) {
            $this->link->rollBack();
        }
        $this->link = null;
    }

    #[Group('player-loadout')]
    public function testApplyLoadoutAddsDesiredCatalogAction(): void
    {
        (new PlayerLoadoutService())->applyLoadout($this->playerId, [$this->catalogAction], []);

        $this->assertContains($this->catalogAction, $this->ownedActionNames());
    }

    #[Group('player-loadout')]
    public function testApplyLoadoutRemovesCatalogActionWhenOmitted(): void
    {
        $this->seedOwned($this->catalogAction);
        $this->assertContains($this->catalogAction, $this->ownedActionNames());

        (new PlayerLoadoutService())->applyLoadout($this->playerId, [], []);

        $this->assertNotContains($this->catalogAction, $this->ownedActionNames());
    }

    #[Group('player-loadout')]
    public function testApplyLoadoutPreservesOwnedOrphanOnEmptySave(): void
    {
        // An owned action with no catalog row — the base-attack case.
        $this->seedOwned($this->orphanName);

        (new PlayerLoadoutService())->applyLoadout($this->playerId, [], []);

        $this->assertContains(
            $this->orphanName,
            $this->ownedActionNames(),
            'Owned uncatalogued actions must survive a save that omits them.'
        );
    }

    #[Group('player-loadout')]
    public function testApplyLoadoutIgnoresNonCatalogDesiredName(): void
    {
        (new PlayerLoadoutService())->applyLoadout($this->playerId, [$this->bogusName], []);

        $this->assertNotContains(
            $this->bogusName,
            $this->ownedActionNames(),
            'A desired name absent from the catalog must never be inserted.'
        );
    }

    /**
     * @return array<int, string>
     */
    private function ownedActionNames(): array
    {
        return $this->link->fetchFirstColumn(
            'SELECT name FROM players_actions WHERE player_id = ?',
            [$this->playerId]
        );
    }

    private function seedOwned(string $name): void
    {
        $this->link->executeStatement(
            "INSERT INTO players_actions (player_id, name, type) VALUES (?, ?, '')",
            [$this->playerId, $name]
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
            $catalog = $link->fetchOne('SELECT name FROM actions ORDER BY name ASC LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('players/actions table unreadable: ' . $e->getMessage());
        }

        if (empty($row['id'])) {
            $this->markTestSkipped(
                'No real player row available — run scripts/testing/reset_test_database.sh.'
            );
        }
        if (empty($catalog)) {
            $this->markTestSkipped('No catalog action rows — reseed the DB.');
        }

        $this->link = $link;
        $this->playerId = (int) $row['id'];
        $this->catalogAction = (string) $catalog;
    }
}
