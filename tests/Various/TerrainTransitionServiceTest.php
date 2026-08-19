<?php

namespace Tests\Various;

use App\Service\TerrainTransitionService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Moteur des transitions de terrain (autotiling Tiled) : énumération des
 * affectations de coins, analyse des points de coin d'une grille, et
 * génération des fondus PNG + wangId dans une arborescence temporaire.
 */
class TerrainTransitionServiceTest extends TestCase
{
    private TerrainTransitionService $service;
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/terrain_transitions_' . uniqid();
        mkdir($this->root . '/img/tiles', 0777, true);
        $this->service = new TerrainTransitionService(null, $this->root);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/img/tiles/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->root . '/img/tiles');
        rmdir($this->root . '/img');
        if (is_dir($this->root . '/tools')) {
            @unlink($this->root . '/tools/tiled/terrains.json');
            rmdir($this->root . '/tools/tiled');
            rmdir($this->root . '/tools');
        }
        rmdir($this->root);
    }

    /** Écrit une tuile 50x50 de couleur unie dans l'img temporaire. */
    private function writeSolidTile(string $name, int $red, int $green, int $blue): void
    {
        $image = imagecreatetruecolor(50, 50);
        imagefill($image, 0, 0, imagecolorallocate($image, $red, $green, $blue));
        imagepng($image, $this->root . '/img/tiles/' . $name . '.png');
    }

    /** @return array{name: string, type: string, colors: list<string>, tiles: array<string, mixed>} */
    private function config(array $fullTiles): array
    {
        return [
            'name'   => 'Terrains',
            'type'   => 'corner',
            'colors' => array_values($fullTiles),
            'tiles'  => $fullTiles,
        ];
    }

    public function testSurjectiveTuplesMatchWangCombinationCounts(): void
    {
        // 2^4-2 paires, 3^4-3-3*14 trios, 4! quatuors
        $this->assertCount(14, $this->service->surjectiveTuples(2));
        $this->assertCount(36, $this->service->surjectiveTuples(3));
        $this->assertCount(24, $this->service->surjectiveTuples(4));

        foreach ($this->service->surjectiveTuples(3) as $tuple) {
            $this->assertCount(3, array_unique($tuple), 'chaque biome doit apparaître');
        }
    }

    public function testCornerSetsFindsPairsTriosAndIgnoresNonTerrains(): void
    {
        $cfg = $this->config(['a' => 'a', 'b' => 'b', 'c' => 'c']);

        // 2x2 : trois biomes se rencontrent au point central, plus une tuile
        // hors terrain (non déclarée) qui ne doit produire aucun ensemble
        $grid = [
            '0,0' => 'a', '1,0' => 'b',
            '0,1' => 'c', '1,1' => 'rune',
        ];

        ['sets' => $sets, 'ignored' => $ignored] = $this->service->cornerSets($grid, $cfg);

        $this->assertSame(['rune'], $ignored);
        $this->assertArrayHasKey('a|b|c', $sets, 'le point central mélange les trois biomes');
        $this->assertArrayHasKey('a|b', $sets);
        $this->assertArrayHasKey('a|c', $sets);
        $this->assertArrayNotHasKey('b|c', $sets, 'b et c ne se touchent qu\'en diagonale du point central');
    }

    public function testGenerateSetProducesBlendedTilesAndWangIds(): void
    {
        $this->writeSolidTile('rouge', 255, 0, 0);
        $this->writeSolidTile('bleu', 0, 0, 255);
        $cfg = $this->config(['rouge' => 'rouge', 'bleu' => 'bleu']);

        $names = $this->service->generateSet($cfg, 'tiles', ['rouge', 'bleu']);

        $this->assertCount(14, $names);
        $this->assertContains('trans_rouge_bleu_baaa', $names);

        // baaa : coin TL = « b » (bleu), les trois autres rouges
        $this->assertSame([0, 1, 0, 1, 0, 1, 0, 2], $cfg['tiles']['trans_rouge_bleu_baaa']);

        $png = imagecreatefrompng($this->root . '/img/tiles/trans_rouge_bleu_baaa.png');
        $topLeft = imagecolorsforindex($png, imagecolorat($png, 0, 0));
        $bottomRight = imagecolorsforindex($png, imagecolorat($png, 49, 49));
        $this->assertGreaterThan(250, $topLeft['blue'], 'le coin TL doit être bleu');
        $this->assertGreaterThan(250, $bottomRight['red'], 'le coin BR doit être rouge');
    }

    public function testGenerateSetSkipsAssignmentsAlreadyCoveredByWangId(): void
    {
        $this->writeSolidTile('rouge', 255, 0, 0);
        $this->writeSolidTile('bleu', 0, 0, 255);
        $cfg = $this->config(['rouge' => 'rouge', 'bleu' => 'bleu']);

        $this->service->generateSet($cfg, 'tiles', ['rouge', 'bleu']);
        $again = $this->service->generateSet($cfg, 'tiles', ['bleu', 'rouge'],
            $this->service->existingWangKeys($cfg));

        $this->assertSame([], $again, 'les 14 affectations existent déjà, même déclarées dans l\'autre ordre');
    }

    public function testClassifyTilesKeepsColorIndicesStableAndProtectsTransitions(): void
    {
        // Pas de mkdir : sur un serveur déployé tools/ n'existe pas,
        // saveTerrains doit créer l'arborescence à la première écriture
        $this->writeSolidTile('rouge', 255, 0, 0);
        $this->writeSolidTile('bleu', 0, 0, 255);

        // Un set existant avec des fondus dont les wangId pointent les
        // couleurs par index : la classification ne doit jamais les décaler
        $terrains = $this->service->loadTerrains();
        $cfg = &$this->service->layerConfig($terrains, 'tiles');
        $cfg['colors'] = ['rouge', 'bleu'];
        $cfg['tiles'] = ['rouge' => 'rouge', 'bleu' => 'bleu'];
        $this->service->generateSet($cfg, 'tiles', ['rouge', 'bleu']);
        $this->service->saveTerrains($terrains);

        // Déclasser rouge, déclarer vert ; les fondus sont intouchables
        $this->writeSolidTile('vert', 0, 255, 0);
        $result = $this->service->classifyTiles('tiles', ['vert', 'trans_rouge_bleu_baaa'], ['rouge']);

        $this->assertSame(['vert'], $result['declared'], 'un fondu ne se déclare pas comme terrain');
        $this->assertSame(['rouge'], $result['undeclared']);

        $saved = $this->service->loadTerrains()['tiles'];
        $this->assertSame(['rouge', 'bleu', 'vert'], $saved['colors'],
            'couleur orpheline conservée, nouvelle couleur en fin : indices stables');
        $this->assertArrayNotHasKey('rouge', array_filter($saved['tiles'], 'is_string'),
            'rouge n\'est plus une tuile pleine');
        $this->assertSame([0, 1, 0, 1, 0, 1, 0, 2], $saved['tiles']['trans_rouge_bleu_baaa'],
            'les wangId existants sont intacts');
        $this->assertSame('vert', $saved['tiles']['vert']);
    }

    public function testPaletteSourceBlendsLikeTruecolor(): void
    {
        // PNG à palette : imagecolorat y renvoie l'index, pas la couleur —
        // sans conversion truecolor le fondu tirait vers le noir (bug vécu
        // sur le serveur de test, dont le GD ne convertit pas au resize)
        $palette = imagecreate(50, 50);
        imagecolorallocate($palette, 10, 10, 10);   // index 0 : leurre sombre
        $grey = imagecolorallocate($palette, 174, 174, 174);
        imagefilledrectangle($palette, 0, 0, 49, 49, $grey);
        imagepng($palette, $this->root . '/img/tiles/gris_palette.png');
        $this->writeSolidTile('rouge', 255, 0, 0);

        $cfg = $this->config(['rouge' => 'rouge', 'gris_palette' => 'gris_palette']);
        $this->service->generateSet($cfg, 'tiles', ['rouge', 'gris_palette']);

        // bbbb n'existe pas (14 combos) : prendre abbb, coin BR ≈ pur gris
        $png = imagecreatefrompng($this->root . '/img/tiles/trans_rouge_gris_palette_abbb.png');
        $corner = imagecolorsforindex($png, imagecolorat($png, 49, 49));
        $this->assertEqualsWithDelta(174, $corner['red'], 2, 'le gris de la palette, pas son index');
        $this->assertEqualsWithDelta(174, $corner['green'], 2);
    }

    public function testRegenerateTransitionImagesRepairsCorruptedPngs(): void
    {
        mkdir($this->root . '/tools/tiled', 0777, true);
        $this->writeSolidTile('rouge', 255, 0, 0);
        $this->writeSolidTile('bleu', 0, 0, 255);

        $terrains = $this->service->loadTerrains();
        $cfg = &$this->service->layerConfig($terrains, 'tiles');
        $cfg['colors'] = ['rouge', 'bleu'];
        $cfg['tiles'] = ['rouge' => 'rouge', 'bleu' => 'bleu'];
        $this->service->generateSet($cfg, 'tiles', ['rouge', 'bleu']);
        $this->service->saveTerrains($terrains);

        // Corrompre un fondu (tout noir), comme les blends palette du serveur
        $this->writeSolidTile('trans_rouge_bleu_baaa', 0, 0, 0);

        $result = $this->service->regenerateTransitionImages('tiles');

        $this->assertSame(14, $result['regenerated']);
        $this->assertSame([], $result['unparsed']);
        $png = imagecreatefrompng($this->root . '/img/tiles/trans_rouge_bleu_baaa.png');
        $corner = imagecolorsforindex($png, imagecolorat($png, 49, 49));
        $this->assertGreaterThan(250, $corner['red'], 'le coin BR (rouge) est restauré');
    }

    public function testGenerateSetRefusesBiomesSharingAColor(): void
    {
        $this->writeSolidTile('lac', 0, 0, 255);
        $this->writeSolidTile('lac_gele', 200, 200, 255);
        // les deux tuiles pointent la même couleur de terrain
        $cfg = $this->config(['lac' => 'eau', 'lac_gele' => 'eau']);
        $cfg['colors'] = ['eau'];

        $this->expectException(RuntimeException::class);
        $this->service->generateSet($cfg, 'tiles', ['lac', 'lac_gele']);
    }
}
