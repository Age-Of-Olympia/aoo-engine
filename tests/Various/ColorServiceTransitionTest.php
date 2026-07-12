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

    public function testTransitionColorIsConstantPerBiomePair(): void
    {
        // Toujours le mélange 50/50, quel que soit le code de coins : la
        // frontière entre deux biomes forme une bande d'une seule couleur
        // sur la carte, pas un dégradé patchwork
        $this->assertSame(
            [100, 50, 20],
            ColorService::colorFor('trans_carreaux_caverne_aabb', self::COLORS)
        );
        $this->assertSame(
            ColorService::colorFor('trans_carreaux_caverne_aabb', self::COLORS),
            ColorService::colorFor('trans_carreaux_caverne_abbb', self::COLORS)
        );
        $this->assertSame(
            ColorService::colorFor('trans_carreaux_caverne_aabb', self::COLORS),
            ColorService::colorFor('trans_carreaux_caverne_baaa', self::COLORS)
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

    public function testThreeBiomeTransitionUsesEqualParts(): void
    {
        // Jonction à 3 biomes : un tiers chacun, quel que soit le code
        $this->assertSame(
            [80, 40, 47],
            ColorService::colorFor('trans_carreaux_caverne_terre_aabc', self::COLORS)
        );
        $this->assertSame(
            ColorService::colorFor('trans_carreaux_caverne_terre_aabc', self::COLORS),
            ColorService::colorFor('trans_carreaux_caverne_terre_abcc', self::COLORS)
        );
    }

    public function testThreeBiomeTransitionResolvesUnderscoredNames(): void
    {
        // desert_de_l_egeon au milieu du nom : le backtracking doit trouver
        // la seule coupure en 3 tuiles connues
        $this->assertSame(
            [160, 113, 107],
            ColorService::colorFor('trans_carreaux_desert_de_l_egeon_terre_abbc', self::COLORS)
        );
    }

    public function testFourBiomeTransitionUsesEqualParts(): void
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
