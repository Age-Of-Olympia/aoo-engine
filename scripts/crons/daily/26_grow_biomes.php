<?php

use App\Service\Map\BiomeGrowthService;
use App\Service\Map\BoardChanges;

// What grows in each biome (admin › Biomes); nothing while the module is off
BoardChanges::cellId(...(new BiomeGrowthService())->growNight());

echo 'done';
