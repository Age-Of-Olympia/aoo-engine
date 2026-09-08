<?php

namespace Tests\Various;

use App\Service\PlayerSkillsService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyBootstrapTrait;

/**
 * Functional test for PlayerSkillsService::applySkills — the admin skills
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
#[Group('player-skills')]
class PlayerSkillsServiceTest extends TestCase
{
    use LegacyBootstrapTrait;

    private int $playerId = 0;
    private string $catalogAction = '';
    private string $orphanName = '';
    private string $bogusName = '';

    protected function setUp(): void
    {
        $this->bootstrapLegacyOrSkip();
        $this->playerId = $this->firstRealPlayerIdOrSkip();

        $catalog = $this->link->fetchOne('SELECT name FROM actions ORDER BY name ASC LIMIT 1');
        if (empty($catalog)) {
            $this->markTestSkipped('No catalog action rows — reseed the DB.');
        }
        $this->catalogAction = (string) $catalog;

        $this->link->beginTransaction();

        $this->link->executeStatement(
            'DELETE FROM players_actions WHERE player_id = ?',
            [$this->playerId]
        );

        $this->orphanName = 'skillsTest_orphan_' . bin2hex(random_bytes(4));
        $this->bogusName = 'skillsTest_bogus_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if ($this->link !== null && $this->link->isTransactionActive()) {
            $this->link->rollBack();
        }
        $this->link = null;
    }

    public function testApplySkillsAddsDesiredCatalogAction(): void
    {
        (new PlayerSkillsService())->applySkills($this->playerId, [$this->catalogAction], []);

        $this->assertContains($this->catalogAction, $this->ownedActionNames());
    }

    public function testApplySkillsRemovesCatalogActionWhenOmitted(): void
    {
        $this->seedOwned($this->catalogAction);
        $this->assertContains($this->catalogAction, $this->ownedActionNames());

        (new PlayerSkillsService())->applySkills($this->playerId, [], []);

        $this->assertNotContains($this->catalogAction, $this->ownedActionNames());
    }

    public function testApplySkillsPreservesOwnedOrphanOnEmptySave(): void
    {
        // An owned action with no catalog row — the base-attack case.
        $this->seedOwned($this->orphanName);

        (new PlayerSkillsService())->applySkills($this->playerId, [], []);

        $this->assertContains(
            $this->orphanName,
            $this->ownedActionNames(),
            'Owned uncatalogued actions must survive a save that omits them.'
        );
    }

    public function testApplySkillsIgnoresNonCatalogDesiredName(): void
    {
        (new PlayerSkillsService())->applySkills($this->playerId, [$this->bogusName], []);

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
}
