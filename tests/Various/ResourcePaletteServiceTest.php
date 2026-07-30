<?php

namespace Tests\Various;

use App\Service\ResourcePaletteService;
use PHPUnit\Framework\TestCase;

/**
 * Règle unique des murs encore authorables dans map_resources (passe Tiled de
 * la conversion des obstacles en entités) : partagée entre le catalogue de
 * l'extension Tiled, la garde d'import et la palette de l'éditeur web.
 */
class ResourcePaletteServiceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Miroir minimal du catalogue des types (couture de test du gateway,
        // pas de base ici) : la NATURE dit ce qu'un type est, plus le signe
        // d'un nombre.
        \App\Service\Map\StructureTypeService::setCatalogForTests([
            'arbre1'     => ['nature' => 'ressource', 'pv' => 100],
            'pierre1'    => ['nature' => 'ressource', 'pv' => 100],
            'mur_pierre' => ['nature' => 'obstacle',  'pv' => 150],
            'pilier'     => ['nature' => 'obstacle',  'pv' => 10],
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        \App\Service\Map\StructureTypeService::setCatalogForTests(null);
    }

    public function testResourcesAreAuthorableEverywhere(): void
    {
        $this->assertTrue(ResourcePaletteService::isResourceName('arbre1'));
        $this->assertTrue(ResourcePaletteService::isAuthorable('arbre1', 'gaia'));
        $this->assertTrue(ResourcePaletteService::isAuthorable('pierre1', 'arcadia'));
    }

    public function testObstaclesAreNotAuthorableOutsideTutorial(): void
    {
        $this->assertFalse(ResourcePaletteService::isResourceName('mur_pierre'));
        $this->assertFalse(ResourcePaletteService::isAuthorable('mur_pierre', 'gaia'));
        $this->assertFalse(ResourcePaletteService::isAuthorable('pilier', 'arcadia'));
        // Nom inconnu du catalogue : obstacle par défaut
        $this->assertFalse(ResourcePaletteService::isAuthorable('statue_exotique', 'gaia'));
    }

    public function testSurvivorsStayAuthorable(): void
    {
        $this->assertTrue(ResourcePaletteService::isAuthorable('autel', 'gaia'));
        $this->assertTrue(ResourcePaletteService::isAuthorable('autel_olympien', 'gaia'));
        $this->assertTrue(ResourcePaletteService::isAuthorable('altar', 'gaia'));
        $this->assertTrue(ResourcePaletteService::isAuthorable('unique_pierre_sacree', 'gaia'));
    }

    public function testTutorialPlansKeepEverything(): void
    {
        $this->assertTrue(ResourcePaletteService::isTutorialPlan('tutorial'));
        $this->assertTrue(ResourcePaletteService::isTutorialPlan('tut_abc123'));
        $this->assertFalse(ResourcePaletteService::isTutorialPlan('gaia'));

        // Les murs d'enceinte clonés par session restent des map_resources
        $this->assertTrue(ResourcePaletteService::isAuthorable('mur_pierre', 'tutorial'));
        $this->assertTrue(ResourcePaletteService::isAuthorable('mur_pierre', 'tut_abc123'));
    }

    public function testLegacyWallsLayerKeyIsNormalized(): void
    {
        // Cartes pullées et bundles d'avant le renommage map_walls →
        // map_resources : la clé « walls » est acceptée et repliée
        $normalized = \App\Service\TiledMapService::normalizeLegacyLayerKeys([
            'tiles' => [['x' => 0, 'y' => 0, 'name' => 'herbe']],
            'walls' => [['x' => 1, 'y' => 1, 'name' => 'arbre1']],
        ]);

        $this->assertArrayNotHasKey('walls', $normalized);
        $this->assertSame('arbre1', $normalized['resources'][0]['name']);
        $this->assertArrayHasKey('tiles', $normalized);
    }

    public function testFilterNamesKeepsOnlyAuthorable(): void
    {
        $names = ['arbre1', 'mur_pierre', 'autel', 'pilier', 'unique_x'];

        $this->assertSame(
            ['arbre1', 'autel', 'unique_x'],
            ResourcePaletteService::filterNames($names, 'gaia')
        );
        $this->assertSame($names, ResourcePaletteService::filterNames($names, 'tutorial'));
    }
}
