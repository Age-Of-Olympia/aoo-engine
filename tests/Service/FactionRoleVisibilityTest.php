<?php

namespace Tests\Service;

use App\Service\FactionService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * showPosition / showForum are read on the rank held in the faction —
 * public or secret. A rank without rights sees nothing.
 */
#[Group('action')]
class FactionRoleVisibilityTest extends LegacyPlayerFixtureTestCase
{
    private const CODE = 'faction_test_vis';

    private ?int $factionId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->link->executeStatement(
            "INSERT INTO factions (code, name) VALUES (?, 'Visibilité de test')
             ON DUPLICATE KEY UPDATE name = VALUES(name)",
            [self::CODE]
        );
        $this->factionId = (int) $this->link->fetchOne('SELECT id FROM factions WHERE code = ?', [self::CODE]);
        $this->link->executeStatement(
            'INSERT INTO faction_roles (faction_id, position, name, defaultRole, showPosition, showForum)
             VALUES (?, 0, "Rétif", 0, 0, 0), (?, 1, "Membre", 1, 1, 1)',
            [$this->factionId, $this->factionId]
        );
        FactionService::clearCache();
    }

    protected function tearDown(): void
    {
        if ($this->factionId !== null) {
            $this->link->executeStatement(
                "UPDATE players SET faction = '', factionRole = 0 WHERE faction = ?",
                [self::CODE]
            );
            $this->link->executeStatement(
                "UPDATE players SET secretFaction = '', secretFactionRole = 0 WHERE secretFaction = ?",
                [self::CODE]
            );
            $this->link->executeStatement('DELETE FROM faction_roles WHERE faction_id = ?', [$this->factionId]);
            $this->link->executeStatement('DELETE FROM factions WHERE id = ?', [$this->factionId]);
            $this->factionId = null;
        }
        FactionService::clearCache();
        parent::tearDown();
    }

    public function testRankWithoutRightsSeesNothing(): void
    {
        $id = $this->memberAt('faction', 'factionRole', 0, 'VisRetif');
        $service = new FactionService();

        $this->assertFalse($service->mayInFaction($id, self::CODE, 'showPosition'));
        $this->assertFalse($service->mayInFaction($id, self::CODE, 'showForum'));
    }

    public function testRankWithRightsSees(): void
    {
        $id = $this->memberAt('faction', 'factionRole', 1, 'VisMembre');
        $service = new FactionService();

        $this->assertTrue($service->mayInFaction($id, self::CODE, 'showPosition'));
        $this->assertTrue($service->mayInFaction($id, self::CODE, 'showForum'));
    }

    public function testSecretFactionReadsTheSecretRank(): void
    {
        $retif = $this->memberAt('secretFaction', 'secretFactionRole', 0, 'VisSecretR');
        $member = $this->memberAt('secretFaction', 'secretFactionRole', 1, 'VisSecretM');
        $service = new FactionService();

        $this->assertFalse($service->mayInFaction($retif, self::CODE, 'showForum'));
        $this->assertTrue($service->mayInFaction($member, self::CODE, 'showForum'));
    }

    public function testOutsiderSeesNothing(): void
    {
        $player = $this->createRealPlayer('VisOutsider');

        $this->assertFalse((new FactionService())->mayInFaction((int) $player->id, self::CODE, 'showForum'));
    }

    private function memberAt(string $factionColumn, string $roleColumn, int $position, string $name): int
    {
        $player = $this->createRealPlayer($name);
        $this->link->executeStatement(
            "UPDATE players SET $factionColumn = ?, $roleColumn = ? WHERE id = ?",
            [self::CODE, $position, $player->id]
        );
        FactionService::clearCache();

        return (int) $player->id;
    }
}
