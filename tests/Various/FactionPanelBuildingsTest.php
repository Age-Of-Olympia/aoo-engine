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

    public function testAStandingBuildingIsListedWithItsState(): void
    {
        $this->requireBuildingsOrSkip();
        $code = $this->factionOrSkip();

        $id = (new BuildingService())->place(
            'atelier',
            (object) ['x' => 102, 'y' => 102, 'z' => 0, 'plan' => 'gaia'],
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
}
