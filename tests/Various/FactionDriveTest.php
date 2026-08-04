<?php

namespace Tests\Various;

use App\Service\BuildingService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Taking a building's commands asks the household rule — the owner, a
 * member of its faction — plus what the thing itself must be: a
 * playable type, standing, finished, not a ruin. Every refusal speaks
 * the words the player reads.
 */
#[Group('action')]
class FactionDriveTest extends LegacyPlayerFixtureTestCase
{
    private function factionOrSkip(): string
    {
        $code = (string) ($this->link->fetchOne('SELECT code FROM factions ORDER BY code LIMIT 1') ?: '');
        if ($code === '') {
            $this->markTestSkipped('factions catalog not seeded (run migrations).');
        }

        return $code;
    }

    private function placedAtelier(string $faction, bool $asSite = false): int
    {
        $this->requireBuildingsOrSkip();

        $id = (new BuildingService())->place(
            'atelier',
            (object) ['x' => 104, 'y' => 104, 'z' => 0, 'plan' => 'gaia'],
            null,
            $faction,
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

    protected function tearDown(): void
    {
        $this->setPlayable(false);
        parent::tearDown();
    }

    private function memberOf(string $faction): int
    {
        $player = $this->createRealPlayer('GmPilote');
        $this->link->executeStatement(
            'UPDATE players SET faction = ? WHERE id = ?',
            [$faction, $player->id]
        );

        return (int) $player->id;
    }

    public function testAMemberTakesAPlayableFinishedBuilding(): void
    {
        $code = $this->factionOrSkip();
        $building = $this->placedAtelier($code);
        $this->setPlayable(true);
        $member = $this->memberOf($code);

        (new BuildingService())->assertDrivable($building, $member);
        $this->addToAssertionCount(1);
    }

    public function testAnUnplayableTypeRefuses(): void
    {
        $code = $this->factionOrSkip();
        $building = $this->placedAtelier($code);
        $member = $this->memberOf($code);

        $this->expectExceptionMessage('Ce bâtiment ne se pilote pas.');
        (new BuildingService())->assertDrivable($building, $member);
    }

    public function testAConstructionSiteRefuses(): void
    {
        $code = $this->factionOrSkip();
        $building = $this->placedAtelier($code, asSite: true);
        $this->setPlayable(true);
        $member = $this->memberOf($code);

        $this->expectExceptionMessage('Un chantier ne se pilote pas.');
        (new BuildingService())->assertDrivable($building, $member);
    }

    public function testAStrangerIsNotOneOfTheirs(): void
    {
        $code = $this->factionOrSkip();
        $building = $this->placedAtelier($code);
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
        $code = $this->factionOrSkip();
        $member = $this->memberOf($code);

        $this->expectExceptionMessage('Ce n\'est pas un bâtiment.');
        (new BuildingService())->assertDrivable($member, $member);
    }
}
