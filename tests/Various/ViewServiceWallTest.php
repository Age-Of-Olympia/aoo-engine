<?php

namespace Tests\Various;

use App\Service\ViewService;
use PHPUnit\Framework\TestCase;

class ViewServiceWallTest extends TestCase
{
    public function testWallsShareOneMapColour(): void
    {
        foreach (['mur_pierre', 'mur_crepusculaire', 'mur_bois', 'muret', 'barricade'] as $type) {
            $this->assertTrue(ViewService::isWall($type), $type);
        }
    }

    public function testCliffsIceSpikesAndBuildingsKeepTheirOwnColour(): void
    {
        foreach (['falaise_o', 'pic_glace1', 'dent_de_pierres_1', 'banque', 'statue_ailee'] as $type) {
            $this->assertFalse(ViewService::isWall($type), $type);
        }
    }
}
