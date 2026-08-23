<?php

namespace Tests\Various;

use App\Service\BuildingService;
use App\Service\Map\ResourceStateService;
use App\Service\Map\StructureTypeService;
use App\Service\TiledMapService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\PlantsResourcesTrait;

/**
 * Couche « resources » de l'éditeur Tiled : elle parle aux ENTITÉS.
 *
 * La table map_resources est vide et sans lecteur depuis la conversion, mais
 * l'éditeur y lisait et y écrivait encore : le pull montrait un plan sans
 * arbres ni pierres là où le jeu en tenait, et le push répondait « +3 posées »
 * en déposant trois lignes dans une table que personne ne regarde.
 *
 * Ce que ces cas tiennent : le pull voit ce que le jeu voit, le push pose de
 * vraies entités, ce qui ne bouge pas garde son identité ET son état — une
 * ressource épuisée ne repousse pas parce qu'un animateur a poussé sa carte —
 * et un niveau poussé ne touche pas aux autres.
 *
 * DB-backed ; skip propre quand la base est inaccessible, plan préfixé
 * plan_test_ nettoyé par clé naturelle — mêmes conventions que
 * TiledBuildingsLayerTest.
 */
class TiledResourcesLayerTest extends TestCase
{
    use PlantsResourcesTrait;

    private const PLAN = 'plan_test_tiled_res';

    /** Un type dont le catalogue dit « ressource » : c'est ce qui se pose ici. */
    private string $type;

    protected function setUp(): void
    {
        $this->bootstrapOrSkip();
        $this->cleanupFixtures();

        $harvestable = array_keys(array_filter(
            StructureTypeService::all(),
            static fn(array $spec): bool => $spec['nature'] === StructureTypeService::NATURE_RESOURCE
        ));

        if ($harvestable === []) {
            $this->markTestSkipped('Aucun type de nature « ressource » en base.');
        }

        sort($harvestable);
        $this->type = $harvestable[0];

        // Coord d'amorce : le plan doit exister pour être exportable
        \Classes\View::get_coords_id((object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => self::PLAN]);
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures();
    }

    /** Ce que le jeu tient, l'éditeur le voit. */
    public function testPullShowsTheResourcesStandingOnTheLevel(): void
    {
        $coordsId = (int) \Classes\View::get_coords_id(
            (object) ['x' => 4, 'y' => 4, 'z' => 0, 'plan' => self::PLAN]
        );
        $this->plantResource($this->link(), $this->type, $coordsId, self::PLAN, 4, 4);

        $export = (new TiledMapService())->exportPlan(self::PLAN, 0);

        $this->assertCount(1, $export['layers']['resources'], 'la ressource posée est pullée');
        $this->assertSame($this->type, $export['layers']['resources'][0]['name']);
        $this->assertSame(4, (int) $export['layers']['resources'][0]['x']);
    }

    /** Poser, conserver, retirer — et rien qui atterrisse dans la table retirée. */
    public function testPushPlacesKeepsAndRemovesEntities(): void
    {
        $service = new TiledMapService();
        $link = $this->link();

        $export = $service->exportPlan(self::PLAN, 0);
        $this->assertSame([], $export['layers']['resources'], 'plan neuf : aucune ressource');

        $result = $service->importPlan(self::PLAN, 0, [
            'resources' => [['x' => 2, 'y' => 3, 'name' => $this->type]],
        ], $export['version']);
        $this->assertSame(1, $result['layers']['resources']['inserted']);
        $this->assertSame([], $result['layers']['resources']['skipped']);

        $entityId = (int) $link->fetchOne(
            "SELECT p.id FROM players p JOIN coords c ON c.id = p.coords_id
              WHERE p.player_type = 'resource' AND c.plan = ? AND c.x = 2 AND c.y = 3",
            [self::PLAN]
        );
        $this->assertGreaterThan(0, $entityId, 'le push pose une entité, pas une ligne');
        $this->assertSame(
            0,
            (int) $link->fetchOne(
                'SELECT COUNT(*) FROM map_resources m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
                [self::PLAN]
            ),
            'la table sans lecteur ne reçoit plus rien'
        );

        // Re-push identique : conservée, même entité
        $export = $service->exportPlan(self::PLAN, 0);
        $result = $service->importPlan(self::PLAN, 0, [
            'resources' => [['x' => 2, 'y' => 3, 'name' => $this->type]],
        ], $export['version']);
        $this->assertSame(1, $result['layers']['resources']['kept']);
        $this->assertSame(0, $result['layers']['resources']['inserted']);
        $this->assertSame(
            $entityId,
            (int) $link->fetchOne(
                "SELECT p.id FROM players p JOIN coords c ON c.id = p.coords_id
                  WHERE p.player_type = 'resource' AND c.plan = ? AND c.x = 2 AND c.y = 3",
                [self::PLAN]
            ),
            'même entité, pas re-créée'
        );

        // Gomme : couche vide → l'entité quitte le plateau, satellite compris
        $export = $service->exportPlan(self::PLAN, 0);
        $result = $service->importPlan(self::PLAN, 0, ['resources' => []], $export['version']);
        $this->assertSame(1, $result['layers']['resources']['deleted']);
        $this->assertSame(
            0,
            (int) $link->fetchOne('SELECT COUNT(*) FROM players WHERE id = ?', [$entityId])
        );
        $this->assertSame(
            0,
            (int) $link->fetchOne('SELECT COUNT(*) FROM resources WHERE player_id = ?', [$entityId]),
            'satellite d\'état supprimé'
        );
    }

    /** Un push ne fait pas repousser ce que les joueurs ont épuisé. */
    public function testAnExhaustedResourceSurvivesAPushThatRedrawsIt(): void
    {
        $coordsId = (int) \Classes\View::get_coords_id(
            (object) ['x' => 6, 'y' => 1, 'z' => 0, 'plan' => self::PLAN]
        );
        $entityId = $this->plantResource($this->link(), $this->type, $coordsId, self::PLAN, 6, 1, 0, -2);

        $service = new TiledMapService();
        $export = $service->exportPlan(self::PLAN, 0);
        $this->assertSame(-2, (int) $export['layers']['resources'][0]['damages'], 'le pull dit « à sec »');

        $result = $service->importPlan(self::PLAN, 0, [
            'resources' => [['x' => 6, 'y' => 1, 'name' => $this->type]],
        ], $export['version']);

        $this->assertSame(1, $result['layers']['resources']['kept']);
        $this->assertTrue(
            (new ResourceStateService($this->link()))->isExhausted($entityId),
            'épuisée avant le push, épuisée après'
        );
    }

    /** L'éditeur pousse un niveau à la fois : les autres ne s'effacent pas. */
    public function testPushingOneLevelLeavesTheOthersAlone(): void
    {
        $link = $this->link();
        $below = (int) \Classes\View::get_coords_id(
            (object) ['x' => 1, 'y' => 1, 'z' => -1, 'plan' => self::PLAN]
        );
        $this->plantResource($link, $this->type, $below, self::PLAN, 1, 1, -1);

        $service = new TiledMapService();
        $export = $service->exportPlan(self::PLAN, 0);
        $this->assertSame([], $export['layers']['resources'], 'z=0 ne voit pas la ressource de z=-1');

        $service->importPlan(self::PLAN, 0, ['resources' => []], $export['version']);

        $this->assertSame(
            1,
            (int) $link->fetchOne(
                "SELECT COUNT(*) FROM players p JOIN coords c ON c.id = p.coords_id
                  WHERE p.player_type = 'resource' AND c.plan = ? AND c.z = -1",
                [self::PLAN]
            ),
            'le niveau du dessous est resté debout'
        );
    }

    /**
     * Un obstacle reste refusé, et le refus n'applique rien.
     *
     * Les entités s'écrivent après le commit des couches de lignes : refusé
     * là, le push aurait laissé le sol posé et la ressource non. La ligne
     * d'entité est donc jugée avant que rien ne parte.
     */
    public function testAnObstacleIsRefusedAndNothingIsApplied(): void
    {
        $service = new TiledMapService();
        $export = $service->exportPlan(self::PLAN, 0);

        $obstacle = array_keys(array_filter(
            StructureTypeService::all(),
            static fn(array $spec): bool => $spec['nature'] !== StructureTypeService::NATURE_RESOURCE
        ));

        if ($obstacle === []) {
            $this->markTestSkipped('Aucun type non-ressource en base.');
        }

        try {
            $service->importPlan(self::PLAN, 0, [
                'tiles'     => [['x' => 9, 'y' => 9, 'name' => 'herbe']],
                'resources' => [['x' => 1, 'y' => 1, 'name' => $obstacle[0]]],
            ], $export['version']);
            $this->fail('Obstacle accepté sur la couche resources');
        } catch (RuntimeException $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertStringContainsString('buildings', $e->getMessage(), 'le refus dit où poser');
        }

        $this->assertSame(
            0,
            (int) $this->link()->fetchOne(
                'SELECT COUNT(*) FROM map_tiles m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
                [self::PLAN]
            ),
            'le sol du même push n\'a pas été appliqué'
        );
    }

    private function cleanupFixtures(): void
    {
        $link = $this->link();

        foreach ($link->fetchFirstColumn(
            'SELECT p.id FROM players p JOIN coords c ON c.id = p.coords_id WHERE c.plan = ?',
            [self::PLAN]
        ) as $id) {
            $link->executeStatement('DELETE FROM buildings WHERE player_id = ?', [(int) $id]);
            $link->executeStatement('DELETE FROM resources WHERE player_id = ?', [(int) $id]);
            BuildingService::deleteEntityRows($link, (int) $id);
            BuildingService::purgeEntityCaches((int) $id);
        }

        $link->executeStatement(
            'DELETE m FROM map_resources m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );
        $link->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
        $link->executeStatement('DELETE FROM plans WHERE slug = ?', [self::PLAN]);
        \App\Service\PlanService::forget(self::PLAN);
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
