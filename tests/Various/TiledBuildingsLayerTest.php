<?php

namespace Tests\Various;

use App\Enum\EntityCategory;
use App\Service\BuildingService;
use App\Service\RaceService;
use App\Service\TiledMapService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Couche « buildings » de l'éditeur Tiled : les entités bâtiment d'un
 * niveau s'exportent comme des tuiles (name = type), le push les pose via
 * BuildingService::place et les retire via remove — le décor seulement,
 * les bâtiments possédés/de faction restant protégés comme les lignes
 * player_id des autres couches.
 *
 * DB-backed ; skip propre quand la base est inaccessible — même
 * convention que PlanAdminServiceTest. Plan de test préfixé plan_test_,
 * nettoyé par clé naturelle.
 */
class TiledBuildingsLayerTest extends TestCase
{
    private const PLAN = 'plan_test_tiled_bld';

    private string $type;

    protected function setUp(): void
    {
        $this->bootstrapOrSkip();
        $this->cleanupFixtures();

        $structures = (new RaceService())->getRacesByKind(EntityCategory::Structure->value);
        if ($structures === []) {
            $this->markTestSkipped('Aucune race structure en base.');
        }
        $this->type = $structures[0]->getName();

        // Coord d'amorce : le plan doit exister pour être exportable
        \Classes\View::get_coords_id((object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => self::PLAN]);
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures();
    }

    public function testPushPlacesKeepsAndRemovesDecorBuildings(): void
    {
        $this->expectErrorLog();
        $service = new TiledMapService();

        $export = $service->exportPlan(self::PLAN, 0);
        $this->assertSame([], $export['layers']['buildings'], 'plan neuf : aucune entité');
        $this->assertContains($this->type, $export['catalog']['buildings'], 'palette = catalogue des types');

        // Pose : une tuile buildings devient une entité
        $result = $service->importPlan(self::PLAN, 0, [
            'buildings' => [['x' => 2, 'y' => 3, 'name' => $this->type]],
        ], $export['version']);
        $this->assertSame(1, $result['layers']['buildings']['inserted']);
        $this->assertSame([], $result['layers']['buildings']['skipped']);

        $entity = $this->link()->fetchAssociative(
            "SELECT p.id, p.race, b.build_state FROM buildings b
             JOIN players p ON p.id = b.player_id
             JOIN coords c ON c.id = p.coords_id
             WHERE c.plan = ? AND c.x = 2 AND c.y = 3",
            [self::PLAN]
        );
        $this->assertNotFalse($entity, 'l\'entité existe sur la case');
        $this->assertSame($this->type, $entity['race']);
        $this->assertSame('built', $entity['build_state']);

        // Re-push identique : conservée (l'entité, ses PV, son id survivent)
        $export = $service->exportPlan(self::PLAN, 0);
        $this->assertCount(1, $export['layers']['buildings']);
        $this->assertSame(0, $export['layers']['buildings'][0]['player_id'], 'décor : diffable');

        $result = $service->importPlan(self::PLAN, 0, [
            'buildings' => [['x' => 2, 'y' => 3, 'name' => $this->type]],
        ], $export['version']);
        $this->assertSame(1, $result['layers']['buildings']['kept']);
        $this->assertSame(
            (int) $entity['id'],
            (int) $this->link()->fetchOne(
                'SELECT p.id FROM players p JOIN coords c ON c.id = p.coords_id WHERE c.plan = ? AND c.x = 2 AND c.y = 3',
                [self::PLAN]
            ),
            'même entité, pas re-créée'
        );

        // Gomme : couche vide → l'entité est démontée par le service
        $export = $service->exportPlan(self::PLAN, 0);
        $result = $service->importPlan(self::PLAN, 0, ['buildings' => []], $export['version']);
        $this->assertSame(1, $result['layers']['buildings']['deleted']);
        $this->assertFalse(
            $this->link()->fetchOne('SELECT b.player_id FROM buildings b WHERE b.player_id = ?', [(int) $entity['id']]),
            'satellite supprimé'
        );
    }

    public function testOwnedBuildingsAreProtectedFromTheDiff(): void
    {
        $this->expectErrorLog();
        $buildings = new BuildingService();
        $ownerId = (int) $this->link()->fetchOne('SELECT id FROM players WHERE id > 0 ORDER BY id LIMIT 1');
        if ($ownerId === 0) {
            $this->markTestSkipped('Aucun joueur en base pour porter le bâtiment.');
        }

        $buildings->place($this->type, (object) ['x' => 5, 'y' => 5, 'z' => 0, 'plan' => self::PLAN], $ownerId);

        $service = new TiledMapService();
        $export = $service->exportPlan(self::PLAN, 0);
        $this->assertNotSame(0, $export['layers']['buildings'][0]['player_id'], 'possédé : marqué protégé');

        // Un push qui vide la couche ne touche pas le bâtiment possédé
        $result = $service->importPlan(self::PLAN, 0, ['buildings' => []], $export['version']);
        $this->assertSame(1, $result['layers']['buildings']['protected']);
        $this->assertSame(0, $result['layers']['buildings']['deleted']);
        $this->assertNotFalse($this->link()->fetchOne(
            'SELECT p.id FROM players p JOIN coords c ON c.id = p.coords_id WHERE c.plan = ? AND c.x = 5 AND c.y = 5',
            [self::PLAN]
        ));
    }

    public function testOccupiedTileIsSkippedWithoutFailingThePush(): void
    {
        $this->expectErrorLog();
        $service = new TiledMapService();

        // Un mur ressource occupe la case visée
        $coordsId = \Classes\View::get_coords_id((object) ['x' => 7, 'y' => 7, 'z' => 0, 'plan' => self::PLAN]);
        $this->link()->executeStatement(
            'INSERT INTO map_resources (name, coords_id, damages) VALUES (?, ?, -1)',
            ['arbre1', $coordsId]
        );

        $export = $service->exportPlan(self::PLAN, 0);
        $result = $service->importPlan(self::PLAN, 0, [
            'buildings' => [
                ['x' => 7, 'y' => 7, 'name' => $this->type],
                ['x' => 8, 'y' => 8, 'name' => $this->type],
            ],
        ], $export['version']);

        $this->assertSame(1, $result['layers']['buildings']['inserted'], 'la case libre a pris');
        $this->assertCount(1, $result['layers']['buildings']['skipped'], 'la case occupée est signalée');
        $this->assertStringContainsString('7,7', $result['layers']['buildings']['skipped'][0]);
    }

    private function cleanupFixtures(): void
    {
        $link = $this->link();

        $ids = $link->fetchFirstColumn(
            'SELECT p.id FROM players p JOIN coords c ON c.id = p.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );
        foreach ($ids as $id) {
            // Même ordre que BuildingService::remove() : satellite d'abord
            $link->executeStatement('DELETE FROM buildings WHERE player_id = ?', [(int) $id]);
            BuildingService::deleteEntityRows($link, (int) $id);
            BuildingService::purgeEntityCaches((int) $id);
        }

        foreach (['map_resources'] as $table) {
            $link->executeStatement(
                "DELETE m FROM {$table} m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?",
                [self::PLAN]
            );
        }
        $link->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);

        @unlink($_SERVER['DOCUMENT_ROOT'] . '/datas/private/plans/' . self::PLAN . '.json');
        json()->forget('plans', self::PLAN);
    }

    private function link(): Connection
    {
        global $link;

        return $link;
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

        if (empty($_SERVER['DOCUMENT_ROOT'])) {
            $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
        }

        global $link;
        if (!isset($link) || !$link instanceof Connection) {
            $this->markTestSkipped('Global $link not populated by bootstrap.');
        }

        try {
            $link->executeQuery('SELECT 1 FROM coords LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('coords table unreachable: ' . $e->getMessage());
        }
    }
}
