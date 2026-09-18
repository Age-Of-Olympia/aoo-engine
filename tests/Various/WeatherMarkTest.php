<?php

namespace Tests\Various;

use Classes\View;
use PHPUnit\Framework\TestCase;

/**
 * Une marque meteo_<x> désigne la texture img/tiles/<x> posée sur tout le
 * damier quand le joueur est dessus. Tourne depuis la racine du dépôt,
 * où vit img/tiles (comme le rendu, qui lit des chemins relatifs).
 */
class WeatherMarkTest extends TestCase
{
    public function testAWeatherMarkNamesItsTexture(): void
    {
        $this->assertFileExists('img/tiles/fog.webp', 'le stock de masques est en place');
        $this->assertSame('img/tiles/fog.webp', View::weatherMask(View::WEATHER_MARK_PREFIX . 'fog'));
        $this->assertNull(View::weatherMask(View::WEATHER_MARK_PREFIX . 'aucune_texture_de_ce_nom'));
    }
}
