<?php

namespace Tests\Various;

use App\Service\FactionService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The LADDER is the hierarchy: position ascends, and every gesture
 * reaches strictly below — nobody touches a peer, raises anyone to
 * their own rank, or rewrites their own charter. The top rank settles
 * what each lower rank authorizes (initRole).
 */
#[Group('action')]
class FactionHierarchyTest extends LegacyPlayerFixtureTestCase
{
    private const CODE = 'faction_test_h';

    private ?int $factionId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->link->executeStatement(
            "INSERT INTO factions (code, name) VALUES (?, 'Hiérarchie de test')
             ON DUPLICATE KEY UPDATE name = VALUES(name)",
            [self::CODE]
        );
        $this->factionId = (int) $this->link->fetchOne('SELECT id FROM factions WHERE code = ?', [self::CODE]);

        // Recrue (0, default) < Capitaine (1, manages people) < Roi (2, holds the ladder).
        $this->link->executeStatement(
            'INSERT INTO faction_roles (faction_id, position, name, defaultRole, addMember, editRole, kickMember, initRole)
             VALUES (?, 0, "Recrue", 1, 0, 0, 0, 0),
                    (?, 1, "Capitaine", 0, 1, 1, 1, 0),
                    (?, 2, "Roi", 0, 1, 1, 1, 1)',
            [$this->factionId, $this->factionId, $this->factionId]
        );
        FactionService::clearCache();
    }

    protected function tearDown(): void
    {
        if ($this->factionId !== null) {
            $this->link->executeStatement("UPDATE players SET faction = '', factionRole = 0 WHERE faction = ?", [self::CODE]);
            $this->link->executeStatement('DELETE FROM faction_roles WHERE faction_id = ?', [$this->factionId]);
            $this->link->executeStatement('DELETE FROM factions WHERE id = ?', [$this->factionId]);
            $this->factionId = null;
        }
        FactionService::clearCache();
        parent::tearDown();
    }

    private function enrolled(string $prefix, int $position): int
    {
        $player = $this->createRealPlayer($prefix);
        $this->link->executeStatement(
            'UPDATE players SET faction = ?, factionRole = ? WHERE id = ?',
            [self::CODE, $position, $player->id]
        );

        return (int) $player->id;
    }

    public function testAPeerIsOutOfReach(): void
    {
        $captain = $this->enrolled('GmCapitaine', 1);
        $peer = $this->enrolled('GmPair', 1);

        $this->expectExceptionMessage('Cette personne vous dépasse.');
        (new FactionService())->kickMember($captain, $peer);
    }

    public function testNobodyRisesToTheirPromotersRank(): void
    {
        $captain = $this->enrolled('GmCapitaine2', 1);
        $recruit = $this->enrolled('GmRecrue2', 0);

        $this->expectExceptionMessage('On n\'élève personne à son propre rang.');
        (new FactionService())->assignRole($captain, $recruit, 1);
    }

    public function testTheKingSettlesALowerCharter(): void
    {
        $king = $this->enrolled('GmRoi', 2);

        (new FactionService())->updateRoleDefinition($king, 1, 'Général', [
            'addMember' => 1, 'kickMember' => 1, 'editRole' => 0, 'initRole' => 0,
            'showPosition' => 1, 'showForum' => 1,
        ]);

        $row = $this->link->fetchAssociative(
            'SELECT name, addMember, editRole FROM faction_roles WHERE faction_id = ? AND position = 1',
            [$this->factionId]
        );
        $this->assertSame('Général', $row['name']);
        $this->assertSame(1, (int) $row['addMember']);
        $this->assertSame(0, (int) $row['editRole'], 'a granted flag can be withdrawn');
    }

    public function testOwnNamesRenameButOwnFlagsFreeze(): void
    {
        $king = $this->enrolled('GmRoi2', 2);

        (new FactionService())->updateRoleDefinition(
            $king, 2, 'Empereur', ['editRole' => 0, 'initRole' => 0], 'Impératrice'
        );

        $row = $this->link->fetchAssociative(
            'SELECT name, name_alt, editRole, initRole FROM faction_roles WHERE faction_id = ? AND position = 2',
            [$this->factionId]
        );
        $this->assertSame('Empereur', $row['name'], 'names are cosmetic: one\'s own rank renames');
        $this->assertSame('Impératrice', $row['name_alt']);
        $this->assertSame(1, (int) $row['editRole'], 'power does not self-strip…');
        $this->assertSame(1, (int) $row['initRole'], '…nor self-grant: own flags freeze');
    }

    public function testASuperiorsCharterIsOutOfReach(): void
    {
        $this->link->executeStatement(
            'UPDATE faction_roles SET initRole = 1 WHERE faction_id = ? AND position = 1',
            [$this->factionId]
        );
        FactionService::clearCache();
        $captain = $this->enrolled('GmCapitaineInit', 1);

        $this->expectExceptionMessage('Ce rang vous dépasse.');
        (new FactionService())->updateRoleDefinition($captain, 2, 'Usurpateur', []);
    }

    public function testAMemberBearsTheChosenHalf(): void
    {
        $king = $this->enrolled('GmRoi8', 2);
        $member = $this->enrolled('GmMoitie', 0);

        (new FactionService())->updateRoleDefinition($king, 0, 'Roi de camp', [], 'Reine de camp');
        (new FactionService())->assignRole($king, $member, 0, 1);

        $row = $this->link->fetchAssociative(
            'SELECT factionRole, factionRoleVariant FROM players WHERE id = ?',
            [$member]
        );
        $this->assertSame(['factionRole' => 0, 'factionRoleVariant' => 1], array_map('intval', $row));
    }

    public function testTheLadderIsTheInitRoleHoldersAlone(): void
    {
        $captain = $this->enrolled('GmCapitaine3', 1);

        $this->expectExceptionMessage('Votre rang ne permet pas de régler l\'échelle.');
        (new FactionService())->updateRoleDefinition($captain, 0, 'Serf', []);
    }

    public function testARankBearsAName(): void
    {
        $king = $this->enrolled('GmRoi3', 2);

        $this->expectExceptionMessage('Un rang porte un nom.');
        (new FactionService())->updateRoleDefinition($king, 0, '   ', []);
    }

    public function testARankMayBearTwoNames(): void
    {
        $king = $this->enrolled('GmRoi4', 2);

        (new FactionService())->updateRoleDefinition($king, 1, 'Prince', [], 'Princesse');

        $row = $this->link->fetchAssociative(
            'SELECT name, name_alt FROM faction_roles WHERE faction_id = ? AND position = 1',
            [$this->factionId]
        );
        $this->assertSame(['name' => 'Prince', 'name_alt' => 'Princesse'], $row);
    }

    public function testTheSummitAloneRulesTheStructure(): void
    {
        $captain = $this->enrolled('GmCapitaine4', 1);

        $this->expectExceptionMessage('Seul le plus haut rang règle la structure de l\'échelle.');
        (new FactionService())->setLandingRank($captain, 0);
    }

    public function testTheKingMovesTheLandingRank(): void
    {
        $king = $this->enrolled('GmRoi5', 2);

        (new FactionService())->setLandingRank($king, 1);

        $defaults = $this->link->fetchAllKeyValue(
            'SELECT position, defaultRole FROM faction_roles WHERE faction_id = ? ORDER BY position',
            [$this->factionId]
        );
        $this->assertSame([0 => 0, 1 => 1, 2 => 0], array_map('intval', $defaults), 'one landing rung, the chosen one');
    }

    public function testARungEntersBelowTheSummitAndTheArrowsPlaceIt(): void
    {
        $king = $this->enrolled('GmRoi6', 2);

        $service = new FactionService();
        $service->addRank($king, 'Baron');

        $ladder = $this->link->fetchAllKeyValue(
            'SELECT position, name FROM faction_roles WHERE faction_id = ? ORDER BY position',
            [$this->factionId]
        );
        $this->assertSame([0 => 'Recrue', 1 => 'Capitaine', 2 => 'Baron', 3 => 'Roi'], $ladder);
        $this->assertSame(
            3,
            (int) $this->link->fetchOne('SELECT factionRole FROM players WHERE id = ?', [$king]),
            'the summit slid up one, its holder with it'
        );

        $service->moveRank($king, 2, -1);

        $ladder = $this->link->fetchAllKeyValue(
            'SELECT position, name FROM faction_roles WHERE faction_id = ? ORDER BY position',
            [$this->factionId]
        );
        $this->assertSame([0 => 'Recrue', 1 => 'Baron', 2 => 'Capitaine', 3 => 'Roi'], $ladder);
    }

    public function testARungLeavesOnlyEmptyAndNeverTheLanding(): void
    {
        $king = $this->enrolled('GmRoi7', 2);
        $this->enrolled('GmTenant', 1);

        $service = new FactionService();

        try {
            $service->removeRank($king, 1);
            $this->fail('a held rung must refuse');
        } catch (\RuntimeException $e) {
            $this->assertSame('Des membres tiennent ce rang.', $e->getMessage());
        }

        try {
            $service->removeRank($king, 0);
            $this->fail('the landing rung must refuse');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('rang d\'accueil', $e->getMessage());
        }

        $service->addRank($king, 'Éphémère');
        $service->removeRank($king, 2);

        $ladder = $this->link->fetchAllKeyValue(
            'SELECT position, name FROM faction_roles WHERE faction_id = ? ORDER BY position',
            [$this->factionId]
        );
        $this->assertSame([0 => 'Recrue', 1 => 'Capitaine', 2 => 'Roi'], $ladder, 'the gap closes behind it');
    }
}
