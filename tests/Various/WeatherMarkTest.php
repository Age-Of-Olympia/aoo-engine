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
        $this->assertSame(
            ['mask' => 'img/tiles/fog.webp', 'seconds' => 10, 'vertical' => false],
            View::weatherMask(View::WEATHER_MARK_PREFIX . 'fog')
        );
        $this->assertSame(
            ['mask' => 'img/tiles/rain.webp', 'seconds' => 0.2, 'vertical' => true],
            View::weatherMask(View::WEATHER_MARK_PREFIX . 'rain'),
            'rain falls: same speed and axis as the plans that use it'
        );
        $this->assertNull(View::weatherMask(View::WEATHER_MARK_PREFIX . 'aucune_texture_de_ce_nom'));
    }
}
