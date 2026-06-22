<?php

namespace Tests\Action\Schema;

use App\Service\Action\SimulationWeaponCatalog;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class SimulationWeaponCatalogTest extends TestCase
{
    private function items(): array
    {
        return [
            'gladius' => (object) ['type' => 'equipement', 'emplacement' => 'main1', 'subtype' => 'melee', 'name' => 'Gladius', 'spellMalus' => 1],
            'arc' => (object) ['type' => 'equipement', 'emplacement' => 'main1', 'subtype' => 'tir', 'name' => 'Arc'],
            'casque' => (object) ['type' => 'equipement', 'emplacement' => 'tete', 'subtype' => 'casque', 'name' => 'Casque'],
            'bois' => (object) ['type' => 'matiere', 'name' => 'Bois'],
        ];
    }

    public function testKeepsOnlyMainHandEquipmentGroupedBySubtype(): void
    {
        $catalog = new SimulationWeaponCatalog($this->items());

        $this->assertSame(
            ['melee' => ['gladius' => 'Gladius'], 'tir' => ['arc' => 'Arc']],
            $catalog->groupedBySubtype()
        );
    }

    public function testListsEveryNonMainHandSlotPopulatedOrEmpty(): void
    {
        $slots = (new SimulationWeaponCatalog($this->items()))->equipmentSlots();

        // Every slot of the real model is present, in order, except main1.
        $expected = array_values(array_filter(ITEM_EMPLACEMENT_FORMAT, static fn (string $s): bool => $s !== 'main1'));
        $this->assertSame($expected, array_keys($slots));
        // Slots with items are populated; the rest are empty but still testable.
        $this->assertSame(['casque' => 'Casque'], $slots['tete']);
        $this->assertSame([], $slots['cape']);
    }

    public function testExposesAnItemsRealData(): void
    {
        $catalog = new SimulationWeaponCatalog($this->items());

        $this->assertTrue($catalog->has('gladius'));
        $this->assertTrue($catalog->has('casque'));    // equipment (other slot)
        $this->assertFalse($catalog->has('bois'));     // not equipement
        $this->assertSame(1, $catalog->dataFor('gladius')->spellMalus);
        $this->assertNull($catalog->dataFor('unknown'));
    }
}
