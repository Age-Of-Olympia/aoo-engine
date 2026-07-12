<?php

namespace Tests\Various;

use App\Service\TileAssetService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Inventaire et gestion des images de tuiles (panneau « Tuiles & images ») :
 * diagnostics (PNG à palette, formats multiples, image posée en base mais
 * absente), ajout normalisé en vraies couleurs, garde-fous de suppression et
 * de renommage. Arborescence img/ temporaire ; les comptes d'usage viennent
 * de la vraie base (aoo4) — skip propre quand elle est inaccessible.
 */
class TileAssetServiceTest extends TestCase
{
    private TileAssetService $service;
    private string $root;

    protected function setUp(): void
    {
        $this->bootstrapOrSkip();
        $this->root = sys_get_temp_dir() . '/tile_assets_' . uniqid();
        mkdir($this->root . '/img/tiles', 0777, true);
        $this->service = new TileAssetService(null, $this->root);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/img/*/*') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->root . '/img/*', GLOB_ONLYDIR) ?: [] as $dir) {
            rmdir($dir);
        }
        rmdir($this->root . '/img');
        if (is_dir($this->root . '/tools')) {
            @unlink($this->root . '/tools/tiled/aoo/terrains.json');
            rmdir($this->root . '/tools/tiled/aoo');
            rmdir($this->root . '/tools/tiled');
            rmdir($this->root . '/tools');
        }
        rmdir($this->root);
    }

    private function writePalettePng(string $name): void
    {
        $image = imagecreate(50, 50); // imagecreate = image à palette
        imagecolorallocate($image, 174, 174, 174);
        imagepng($image, $this->root . '/img/tiles/' . $name . '.png');
        imagedestroy($image);
    }

    private function writeTruecolorPng(string $name): void
    {
        $image = imagecreatetruecolor(50, 50);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
        imagepng($image, $this->root . '/img/tiles/' . $name . '.png');
        imagedestroy($image);
    }

    public function testInventoryFlagsPaletteImagesAndMissingOnes(): void
    {
        $this->writePalettePng('tuile_test_palette');
        $this->writeTruecolorPng('tuile_test_ok');

        ['entries' => $entries] = $this->service->inventory('tiles');
        $byName = array_column($entries, null, 'name');

        $this->assertNotEmpty($byName['tuile_test_palette']['problems']);
        $this->assertStringContainsString('palette', $byName['tuile_test_palette']['problems'][0]);

        $paletteProblems = array_filter(
            $byName['tuile_test_ok']['problems'],
            fn(string $p) => str_contains($p, 'palette')
        );
        $this->assertSame([], $paletteProblems, 'une image vraies couleurs n\'est pas signalée palette');

        // Les tuiles réellement posées en base (caverne…) n'existent pas dans
        // l'img temporaire : signalées « image absente », le vrai danger
        $missing = array_filter($entries, fn(array $entry) => $entry['missing']);
        $this->assertNotEmpty($missing, 'les noms posés en base sans image sont remontés');
    }

    public function testAddNormalizesToTruecolorPng(): void
    {
        // Une source à palette doit ressortir en PNG vraies couleurs
        $tmp = $this->root . '/upload_source.png';
        $palette = imagecreate(50, 50);
        imagecolorallocate($palette, 10, 10, 10);
        $grey = imagecolorallocate($palette, 174, 174, 174);
        imagefilledrectangle($palette, 0, 0, 49, 49, $grey);
        imagepng($palette, $tmp);

        $this->service->add('tiles', 'tuile_test_ajout', $tmp);
        unlink($tmp);

        $written = imagecreatefrompng($this->root . '/img/tiles/tuile_test_ajout.png');
        $this->assertTrue(imageistruecolor($written), 'normalisée en vraies couleurs');
        $pixel = imagecolorsforindex($written, imagecolorat($written, 25, 25));
        $this->assertEqualsWithDelta(174, $pixel['red'], 2);
    }

    public function testDeleteRefusesTilesStillPlacedOnMaps(): void
    {
        // caverne est posée sur les cartes de la base de test : même sans
        // image dans l'img temporaire, on la recrée pour tester le garde-fou
        $this->writeTruecolorPng('caverne');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/encore posée/');
        $this->service->delete('tiles', 'caverne');
    }

    public function testMoveChangesLayerAndRefusesWhenStillPlaced(): void
    {
        mkdir($this->root . '/img/foregrounds', 0777, true);
        $this->writeTruecolorPng('tuile_test_move');

        $this->service->move('tiles', 'tuile_test_move', 'foregrounds');

        $this->assertFileDoesNotExist($this->root . '/img/tiles/tuile_test_move.png');
        $this->assertFileExists($this->root . '/img/foregrounds/tuile_test_move.png');

        // caverne est posée sur les cartes : le changement de type est refusé
        $this->writeTruecolorPng('caverne');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/encore posée/');
        $this->service->move('tiles', 'caverne', 'foregrounds');
    }

    public function testRenameMovesFileAndRefusesWhenTransitionsEmbedTheName(): void
    {
        $this->writeTruecolorPng('tuile_test_avant');

        $result = $this->service->rename('tiles', 'tuile_test_avant', 'tuile_test_apres');

        $this->assertSame(0, $result['rowsUpdated'], 'aucune case en base pour cette tuile de test');
        $this->assertFileDoesNotExist($this->root . '/img/tiles/tuile_test_avant.png');
        $this->assertFileExists($this->root . '/img/tiles/tuile_test_apres.png');

        // Avec un fondu généré qui embarque le nom : refus explicite
        mkdir($this->root . '/tools/tiled/aoo', 0777, true);
        file_put_contents($this->root . '/tools/tiled/aoo/terrains.json', json_encode([
            'tiles' => [
                'name' => 'Terrains', 'type' => 'corner',
                'colors' => ['tuile_test_apres', 'autre'],
                'tiles' => [
                    'tuile_test_apres' => 'tuile_test_apres',
                    'trans_tuile_test_apres_autre_abba' => [0, 1, 0, 2, 0, 2, 0, 1],
                ],
            ],
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/fondu/');
        $this->service->rename('tiles', 'tuile_test_apres', 'tuile_test_final');
    }

    private function bootstrapOrSkip(): void
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/functions.php';
            require_once __DIR__ . '/../../config/constants.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy bootstrap failed: ' . $e->getMessage());
        }

        global $link;
        if (!isset($link) || !$link instanceof Connection) {
            $this->markTestSkipped('Global $link not populated by bootstrap.');
        }

        try {
            $link->executeQuery('SELECT 1 FROM map_tiles LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('map_tiles table unreachable: ' . $e->getMessage());
        }
    }
}
