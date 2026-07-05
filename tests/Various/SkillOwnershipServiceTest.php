<?php

namespace Tests\Various;

use App\Service\SkillOwnershipService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Functional test for SkillOwnershipService — the "Qui a ça ?" reverse lookup.
 * Pins that counts and rosters include real players and exclude non-real ones
 * (PNJs / tutorial characters), which is the rule that keeps the figures honest.
 *
 * Mutations run inside a transaction rolled back in tearDown; the service reads
 * through the same global Doctrine connection. Skips when no aoo4 DB is reachable.
 */
class SkillOwnershipServiceTest extends TestCase
{
    private ?Connection $link = null;
    private int $realPlayerId = 0;
    private int $nonRealPlayerId = 0;
    private string $actionName = '';

    protected function setUp(): void
    {
        $this->bootstrapOrSkip();
        $this->link->beginTransaction();
        $this->actionName = 'ownTest_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if ($this->link !== null && $this->link->isTransactionActive()) {
            $this->link->rollBack();
        }
        $this->link = null;
    }

    #[Group('skill-ownership')]
    public function testCountAndRosterIncludeRealOwner(): void
    {
        $this->grant($this->realPlayerId);

        $counts = (new SkillOwnershipService())->actionOwnerCounts();
        $this->assertSame(1, $counts[$this->actionName] ?? 0);

        $ownerIds = array_column((new SkillOwnershipService())->actionOwners($this->actionName), 'id');
        $this->assertContains($this->realPlayerId, $ownerIds);
    }

    #[Group('skill-ownership')]
    public function testNonRealOwnerIsExcluded(): void
    {
        if ($this->nonRealPlayerId === 0) {
            $this->markTestSkipped('No non-real player available to assert exclusion.');
        }

        $this->grant($this->realPlayerId);
        $this->grant($this->nonRealPlayerId);

        $counts = (new SkillOwnershipService())->actionOwnerCounts();
        $this->assertSame(1, $counts[$this->actionName] ?? 0, 'Non-real owner must not be counted.');

        $ownerIds = array_column((new SkillOwnershipService())->actionOwners($this->actionName), 'id');
        $this->assertNotContains($this->nonRealPlayerId, $ownerIds);
    }

    private function grant(int $playerId): void
    {
        $this->link->executeStatement(
            "INSERT INTO players_actions (player_id, name, type) VALUES (?, ?, '')",
            [$playerId, $this->actionName]
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
            $real = $link->fetchOne("SELECT id FROM players WHERE player_type = 'real' ORDER BY id ASC LIMIT 1");
            $nonReal = $link->fetchOne("SELECT id FROM players WHERE player_type <> 'real' ORDER BY id ASC LIMIT 1");
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy DB unreachable: ' . $e->getMessage());
        }

        if (empty($real)) {
            $this->markTestSkipped('No real player available — reseed the DB.');
        }

        $this->link = $link;
        $this->realPlayerId = (int) $real;
        $this->nonRealPlayerId = $nonReal !== false ? (int) $nonReal : 0;
    }
}
