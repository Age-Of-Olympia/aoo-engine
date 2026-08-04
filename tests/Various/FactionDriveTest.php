<?php

namespace Tests\Various;

use App\Service\BuildingService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Taking a building's commands asks the household rule — the owner, a
 * member of its faction — plus the RANK (driveBuilding flag), plus
 * what the thing itself must be: a playable type, standing, finished,
 * not a ruin. Every refusal speaks the words the player reads.
 */
#[Group('action')]
class FactionDriveTest extends LegacyPlayerFixtureTestCase
{
    private const CODE = 'faction_test_d';

    private ?int $factionId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->link->executeStatement(
            "INSERT INTO factions (code, name) VALUES (?, 'Pilotes de test')
             ON DUPLICATE KEY UPDATE name = VALUES(name)",
            [self::CODE]
        );
        $this->factionId = (int) $this->link->fetchOne('SELECT id FROM factions WHERE code = ?', [self::CODE]);

        // Recrue (0) holds nothing; Capitaine (1) drives the buildings.
        $this->link->executeStatement(
            'INSERT INTO faction_roles (faction_id, position, name, defaultRole, driveBuilding)
             VALUES (?, 0, "Recrue", 1, 0), (?, 1, "Capitaine", 0, 1)',
            [$this->factionId, $this->factionId]
        );
        \App\Service\FactionService::clearCache();
    }

    protected function tearDown(): void
    {
        $this->setPlayable(false);
        if ($this->factionId !== null) {
            $this->link->executeStatement("UPDATE players SET faction = '', factionRole = 0 WHERE faction = ?", [self::CODE]);
            $this->link->executeStatement('DELETE FROM faction_roles WHERE faction_id = ?', [$this->factionId]);
            $this->link->executeStatement('DELETE FROM factions WHERE id = ?', [$this->factionId]);
            $this->factionId = null;
        }
        \App\Service\FactionService::clearCache();
        parent::tearDown();
    }

    private function placedAtelier(bool $asSite = false): int
    {
        $this->requireBuildingsOrSkip();

        $id = (new BuildingService())->place(
            'atelier',
            (object) ['x' => 104, 'y' => 104, 'z' => 0, 'plan' => 'gaia'],
            null,
            self::CODE,
            'Forge pilotable',
            asConstructionSite: $asSite
        );
        $this->trackEntityId($id);

        return $id;
    }

    private function setPlayable(bool $playable): void
    {
        $this->link->executeStatement(
            'UPDATE races SET playable = ? WHERE name = "atelier"',
            [$playable ? 1 : 0]
        );
    }

    private function memberOf(string $prefix, int $position): int
    {
        $player = $this->createRealPlayer($prefix);
        $this->link->executeStatement(
            'UPDATE players SET faction = ?, factionRole = ? WHERE id = ?',
            [self::CODE, $position, $player->id]
        );

        return (int) $player->id;
    }

    public function testAFlaggedRankTakesAPlayableFinishedBuilding(): void
    {
        $building = $this->placedAtelier();
        $this->setPlayable(true);
        $captain = $this->memberOf('GmPilote', 1);

        (new BuildingService())->assertDrivable($building, $captain);
        $this->addToAssertionCount(1);
    }

    public function testARankWithoutTheFlagDoesNotDrive(): void
    {
        $building = $this->placedAtelier();
        $this->setPlayable(true);
        $recruit = $this->memberOf('GmRecruePil', 0);

        $this->expectExceptionMessage('Votre rang ne permet pas de piloter les bâtiments.');
        (new BuildingService())->assertDrivable($building, $recruit);
    }

    public function testAnUnplayableTypeRefuses(): void
    {
        $building = $this->placedAtelier();
        $captain = $this->memberOf('GmPilote2', 1);

        $this->expectExceptionMessage('Ce bâtiment ne se pilote pas.');
        (new BuildingService())->assertDrivable($building, $captain);
    }

    public function testAConstructionSiteRefuses(): void
    {
        $building = $this->placedAtelier(asSite: true);
        $this->setPlayable(true);
        $captain = $this->memberOf('GmPilote3', 1);

        $this->expectExceptionMessage('Un chantier ne se pilote pas.');
        (new BuildingService())->assertDrivable($building, $captain);
    }

    public function testAStrangerIsNotOneOfTheirs(): void
    {
        $building = $this->placedAtelier();
        $this->setPlayable(true);
        $stranger = (int) $this->createRealPlayer('GmEtrangerP')->id;
        $this->link->executeStatement(
            "UPDATE players SET faction = 'aucune_maison' WHERE id = ?",
            [$stranger]
        );

        $this->expectExceptionMessage('Vous n\'êtes pas des siens.');
        (new BuildingService())->assertDrivable($building, $stranger);
    }

    public function testACharacterIsNoBuilding(): void
    {
        $captain = $this->memberOf('GmPilote4', 1);

        $this->expectExceptionMessage('Ce n\'est pas un bâtiment.');
        (new BuildingService())->assertDrivable($captain, $captain);
    }
}
