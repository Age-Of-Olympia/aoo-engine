<?php

namespace Tests\Various;

use App\Service\TerrainTransitionService;
use Classes\Db;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyBootstrapTrait;
use Tests\Support\PlanFixtureTrait;

/**
 * Deleting every transition of a plan, laid ones included: files and wangIds
 * go, the cells carrying them fall back to their top-left biome.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class TerrainTransitionPurgeTest extends TestCase
{
    use LegacyBootstrapTrait;
    use PlanFixtureTrait;

    private const PLAN = 'plan_test_transition_purge';

    private ?Connection $conn = null;
    private string $root;

    protected function setUp(): void
    {
        $this->bootstrapLegacyOrSkip();

        try {
            $this->conn = \App\Factory\EntityManagerFactory::getEntityManager()->getConnection();
            $this->conn->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unreachable: ' . $e->getMessage());
        }

        $this->purgePlan($this->conn, self::PLAN);
        $this->root = sys_get_temp_dir() . '/transition_purge_' . uniqid();
        mkdir($this->root . '/img/tiles', 0777, true);
        mkdir($this->root . '/tools/tiled', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->purgePlan($this->conn, self::PLAN);
        if (isset($this->root)) {
            exec('rm -rf ' . escapeshellarg($this->root));
        }
    }

    public function testDeletesFilesDeclarationsAndFlattensLaidCells(): void
    {
        foreach (['gm_purge_rouge' => [255, 0, 0], 'gm_purge_bleu' => [0, 0, 255]] as $name => [$r, $g, $b]) {
            $image = imagecreatetruecolor(50, 50);
            imagefill($image, 0, 0, imagecolorallocate($image, $r, $g, $b));
            imagepng($image, $this->root . '/img/tiles/' . $name . '.png');
        }

        $service = new TerrainTransitionService(new Db(), $this->root);
        $terrains = $service->loadTerrains();
        $cfg = &$service->layerConfig($terrains, 'tiles');
        $cfg['colors'] = ['gm_purge_rouge', 'gm_purge_bleu'];
        $cfg['tiles'] = ['gm_purge_rouge' => 'gm_purge_rouge', 'gm_purge_bleu' => 'gm_purge_bleu'];
        $service->generateSet($cfg, 'tiles', ['gm_purge_rouge', 'gm_purge_bleu']);
        $service->saveTerrains($terrains);

        $lay = [
            [0, 'gm_purge_rouge'],
            [3, 'gm_purge_bleu'],
            [1, 'trans_gm_purge_rouge_gm_purge_bleu_baaa'], // TL = b = bleu
            [2, 'trans_undeclared_abab'],                   // no wangId: emptied
        ];
        foreach ($lay as [$x, $name]) {
            $this->conn->executeStatement('INSERT INTO map_tiles (name, coords_id) VALUES (?, ?)',
                [$name, $this->coordsIdOn(self::PLAN, $x, 0)]);
        }

        $result = $service->deletePlanTransitions(self::PLAN);

        $this->assertSame(15, $result['deleted'], '14 declared + 1 stray');
        $this->assertSame(1, $result['cellsReplaced']);
        $this->assertSame(1, $result['cellsEmptied']);
        $this->assertSame(['gm_purge_bleu.png', 'gm_purge_rouge.png'],
            array_map('basename', glob($this->root . '/img/tiles/*') ?: []));
        $this->assertSame([], array_filter($service->loadTerrains()['tiles']['tiles'], 'is_array'));
        $this->assertSame(['gm_purge_bleu', 'gm_purge_bleu', 'gm_purge_rouge'], $this->conn->fetchFirstColumn(
            'SELECT m.name FROM map_tiles m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ? ORDER BY m.name',
            [self::PLAN]
        ));
    }
}
