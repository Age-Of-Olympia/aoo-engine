<?php

namespace Tests\Various;

use App\Service\BuildingService;
use App\Service\FactionService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The faction panel lists the faction's BUILDINGS — its walls. Standing
 * ones only: what vanished stands nowhere and is no asset. Carrying the
 * faction is enough; a building is never a member (the counts say so).
 */
#[Group('action')]
class FactionPanelBuildingsTest extends LegacyPlayerFixtureTestCase
{
    private function factionOrSkip(): string
    {
        $code = (string) ($this->link->fetchOne('SELECT code FROM factions ORDER BY code LIMIT 1') ?: '');
        if ($code === '') {
            $this->markTestSkipped('factions catalog not seeded (run migrations).');
        }

        return $code;
    }

    public function testAWallIsNoBuildingOfThePanel(): void
    {
        $this->requireBuildingsOrSkip();
        $code = $this->factionOrSkip();

        $id = (new BuildingService())->place('palissade', $this->tile(110, 102), null, $code);
        $this->trackEntityId($id);

        $this->assertNotContains(
            $id,
            array_column((new FactionService())->buildingsOf($code), 'id'),
            'a palissade is an obstacle, not a building'
        );
    }

    public function testADoorHasItsOwnList(): void
    {
        $this->requireBuildingsOrSkip();
        $code = $this->factionOrSkip();
        if ((new \App\Service\RaceService())->getRaceByName('porte_bois')?->isDoor() !== true) {
            $this->markTestSkipped("'porte_bois' not seeded as a door (run migrations).");
        }

        $id = (new BuildingService())->place('porte_bois', $this->tile(118, 102), null, $code);
        $this->trackEntityId($id);

        $this->assertContains($id, array_column((new FactionService())->doorsOf($code), 'id'));
        $this->assertNotContains($id, array_column((new FactionService())->buildingsOf($code), 'id'));
    }

    public function testAPlayerFindsTheBuildingsAndDoorsTheyOwn(): void
    {
        $this->requireBuildingsOrSkip();
        if ((new \App\Service\RaceService())->getRaceByName('porte_bois')?->isDoor() !== true) {
            $this->markTestSkipped("'porte_bois' not seeded as a door (run migrations).");
        }
        $owner = (int) $this->createRealPlayer('GmBatisseur')->id;
        [$x, $y] = $this->farTile();

        $workshop = (new BuildingService())->place('atelier', $this->tile($x, $y), $owner, '');
        $door = (new BuildingService())->place('porte_bois', $this->tile($x + 3, $y), $owner, '');
        $wall = (new BuildingService())->place('palissade', $this->tile($x + 5, $y), $owner, '');
        array_map($this->trackEntityId(...), [$workshop, $door, $wall]);

        $service = new FactionService();
        $this->assertSame([$workshop], array_column($service->buildingsOwnedBy($owner), 'id'));
        $this->assertSame([$door], array_column($service->doorsOwnedBy($owner), 'id'), 'the wall is neither');
    }

    public function testAStandingBuildingIsListedWithItsState(): void
    {
        $this->requireBuildingsOrSkip();
        $code = $this->factionOrSkip();

        $id = (new BuildingService())->place(
            'atelier',
            $this->tile(102, 102),
            null,
            $code,
            'Atelier du bastion',
            asConstructionSite: true
        );
        $this->trackEntityId($id);

        $rows = array_values(array_filter(
            (new FactionService())->buildingsOf($code),
            static fn (array $b): bool => $b['id'] === $id
        ));

        $this->assertCount(1, $rows, "the faction's building is among its assets");
        $this->assertSame('Atelier du bastion', $rows[0]['name']);
        $this->assertSame('construction', $rows[0]['build_state']);
        $this->assertSame(40, $rows[0]['site_total'], 'the panel knows the site progress');
        $this->assertFalse($rows[0]['playable'], 'the atelier type is not playable');

        (new BuildingService())->vanish($id);

        $this->assertSame(
            [],
            array_values(array_filter(
                (new FactionService())->buildingsOf($code),
                static fn (array $b): bool => $b['id'] === $id
            )),
            'vanished, it stands nowhere and is no asset'
        );
    }

    public function testAStandingChestIsAmongTheFactionsAssets(): void
    {
        $code = $this->factionOrSkip();

        $chestId = $this->installExemplar('coffre_bois', 108, 108);
        $this->link->executeStatement(
            'UPDATE players SET faction = ?, owner_id = NULL WHERE id = ?',
            [$code, $chestId]
        );

        $rows = array_values(array_filter(
            (new FactionService())->containersOf($code),
            static fn (array $c): bool => $c['id'] === $chestId
        ));

        $this->assertCount(1, $rows, "the faction's chest is among its assets");
        $this->assertTrue($rows[0]['isOpen']);
        $this->assertSame('gaia', $rows[0]['plan']);

        (new \App\Service\Map\EntityLocationService($this->link))->shelve($chestId);

        $this->assertSame(
            [],
            array_values(array_filter(
                (new FactionService())->containersOf($code),
                static fn (array $c): bool => $c['id'] === $chestId
            )),
            'shelved, it stands nowhere and is not listed'
        );
    }
}
