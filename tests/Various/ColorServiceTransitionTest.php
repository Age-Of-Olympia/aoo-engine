<?php

namespace Tests\Various;

use App\Service\ColorService;
use PHPUnit\Framework\TestCase;

/**
 * Couleur carte des tuiles de transition générées
 * (tools/tiled/generate_transitions.php) : ColorService::colorFor doit
 * mélanger les couleurs des 2 à 4 biomes du nom, y compris quand ceux-ci
 * contiennent des underscores.
 */
class ColorServiceTransitionTest extends TestCase
{
    private const COLORS = [
        'carreaux'          => [200, 100, 40],
        'desert_de_l_egeon' => [240, 220, 180],
        'caverne'           => [0, 0, 0],
        'terre'             => [40, 20, 100],
        'default'           => [100, 100, 100],
    ];

    public function testKnownTileReturnsItsColor(): void
    {
        $this->assertSame([200, 100, 40], ColorService::colorFor('carreaux', self::COLORS));
    }

    public function testUnknownTileFallsBackToDefault(): void
    {
        $this->assertSame([100, 100, 100], ColorService::colorFor('tuile_inconnue', self::COLORS));
    }

    public function testTransitionBlendsWeightedByCornerCount(): void
    {
        // 2 coins « b » sur 4 → mélange 50/50
        $this->assertSame(
            [100, 50, 20],
            ColorService::colorFor('trans_carreaux_caverne_aabb', self::COLORS)
        );

        // 3 coins « b » sur 4 → 75 % caverne
        $this->assertSame(
            [50, 25, 10],
            ColorService::colorFor('trans_carreaux_caverne_abbb', self::COLORS)
        );
    }

    public function testTransitionResolvesUnderscoredBiomeNames(): void
    {
        // Les deux noms contiennent des underscores : la coupure doit tomber
        // sur la seule paire connue de la table
        $this->assertSame(
            [220, 160, 110],
            ColorService::colorFor('trans_carreaux_desert_de_l_egeon_aabb', self::COLORS)
        );
    }

    public function testTransitionWithUnknownBiomeFallsBackToDefault(): void
    {
        $this->assertSame(
            [100, 100, 100],
            ColorService::colorFor('trans_carreaux_biome_fantome_aabb', self::COLORS)
        );
    }

    public function testThreeBiomeTransitionBlendsPerCorner(): void
    {
        // Jonction à 3 biomes : 2 coins carreaux, 1 caverne, 1 terre
        $this->assertSame(
            [110, 55, 45],
            ColorService::colorFor('trans_carreaux_caverne_terre_aabc', self::COLORS)
        );
    }

    public function testThreeBiomeTransitionResolvesUnderscoredNames(): void
    {
        // desert_de_l_egeon au milieu du nom : le backtracking doit trouver
        // la seule coupure en 3 tuiles connues
        $this->assertSame(
            [180, 140, 125],
            ColorService::colorFor('trans_carreaux_desert_de_l_egeon_terre_abbc', self::COLORS)
        );
    }

    public function testFourBiomeTransitionBlendsPerCorner(): void
    {
        $this->assertSame(
            [120, 85, 80],
            ColorService::colorFor('trans_carreaux_desert_de_l_egeon_caverne_terre_abcd', self::COLORS)
        );
    }

    public function testCodeSkippingALetterFallsBackToDefault(): void
    {
        // « b » absent alors que « c » est utilisé : jamais produit par le
        // générateur, ne doit pas être interprété
        $this->assertSame(
            [100, 100, 100],
            ColorService::colorFor('trans_carreaux_caverne_aacc', self::COLORS)
        );
    }

    public function testTransitionNameRoundTrip(): void
    {
        $this->assertSame(
            'trans_carreaux_caverne_terre_aabc',
            ColorService::transitionTileName(['carreaux', 'caverne', 'terre'], 'aabc')
        );
    }
}
