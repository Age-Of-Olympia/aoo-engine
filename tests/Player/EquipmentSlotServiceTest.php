<?php

namespace Tests\Player;

use App\Service\EquipmentSlotService;
use PHPUnit\Framework\TestCase;

class EquipmentSlotServiceTest extends TestCase
{
    public function testATwoHandedItemIsWornOnce(): void
    {
        $lame = (object) ['name' => 'lame'];
        $casque = (object) ['name' => 'casque'];
        $emplacements = (object) ['main2' => $lame, 'tete' => $casque, 'main1' => $lame];

        $this->assertSame(['main1' => $lame, 'tete' => $casque], EquipmentSlotService::wornItems($emplacements));
        $this->assertSame(['main2' => $lame], EquipmentSlotService::wornItems($emplacements, ['main2', 'main1']));
    }
}
