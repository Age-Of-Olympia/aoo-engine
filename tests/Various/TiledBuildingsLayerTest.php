<?php

namespace Tests\Various;

use App\Service\BuildingService;
use Tests\Support\PlantsResourcesTrait;
use App\Service\RaceService;
use App\Service\TiledMapService;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyBootstrapTrait;
use Tests\Support\PlanFixtureTrait;

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
    use LegacyBootstrapTrait;
    use PlantsResourcesTrait;
    use PlanFixtureTrait;

    private const PLAN = 'plan_test_tiled_bld';

    private string $type;

    protected function setUp(): void
    {
        $this->bootstrapLegacyOrSkip('coords');
        $this->cleanupFixtures();

        $buildings = (new RaceService())->getBuildingTypes();
        if ($buildings === []) {
            $this->markTestSkipped('Aucun type de bâtiment en base.');
        }
        $this->type = $buildings[0]->getName();

        // Coord d'amorce : le plan doit exister pour être exportable
        $this->coordsIdOn(self::PLAN, 0, 0);
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures();
    }

    public function testPushPlacesKeepsAndRemovesDecorBuildings(): void
    {
        $service = new TiledMapService();

        $export = $service->exportPlan(self::PLAN, 0);
        $this->assertSame([], $export['layers']['buildings'], 'plan neuf : aucune entité');
        $this->assertContains($this->type, $export['catalog']['buildings'], 'the palette lists the building types');

        // Pose : une tuile buildings devient une entité
        $result = $service->importPlan(self::PLAN, 0, [
            'buildings' => [['x' => 2, 'y' => 3, 'name' => $this->type]],
        ], $export['version']);
        $this->assertSame(1, $result['layers']['buildings']['inserted']);
        $this->assertSame([], $result['layers']['buildings']['skipped']);

        $entity = $this->link->fetchAssociative(
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
            (int) $this->link->fetchOne(
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
            $this->link->fetchOne('SELECT b.player_id FROM buildings b WHERE b.player_id = ?', [(int) $entity['id']]),
            'satellite supprimé'
        );
    }

    public function testOwnedBuildingsAreProtectedFromTheDiff(): void
    {
        $buildings = new BuildingService();
        $ownerId = (int) $this->link->fetchOne('SELECT id FROM players WHERE id > 0 ORDER BY id LIMIT 1');
        if ($ownerId === 0) {
            $this->markTestSkipped('Aucun joueur en base pour porter le bâtiment.');
        }

        $buildings->place($this->type, $this->tile(5, 5, self::PLAN), $ownerId);

        $service = new TiledMapService();
        $export = $service->exportPlan(self::PLAN, 0);
        $this->assertNotSame(0, $export['layers']['buildings'][0]['player_id'], 'possédé : marqué protégé');

        // Un push qui vide la couche ne touche pas le bâtiment possédé
        $result = $service->importPlan(self::PLAN, 0, ['buildings' => []], $export['version']);
        $this->assertSame(1, $result['layers']['buildings']['protected']);
        $this->assertSame(0, $result['layers']['buildings']['deleted']);
        $this->assertNotFalse($this->link->fetchOne(
            'SELECT p.id FROM players p JOIN coords c ON c.id = p.coords_id WHERE c.plan = ? AND c.x = 5 AND c.y = 5',
            [self::PLAN]
        ));
    }

    public function testOccupiedTileIsSkippedWithoutFailingThePush(): void
    {
        $service = new TiledMapService();

        // Une ressource occupe la case visée
        $coordsId = $this->coordsIdOn(self::PLAN, 7, 7);
        $this->plantResource($this->link, 'arbre1', $coordsId, self::PLAN, 7, 7);

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

    /** params of a buildings row = the id of its god: set, kept, removed, refused, versioned. */
    public function testTheGodOfABuildingTravelsInItsParams(): void
    {
        $godId = -99990;
        $this->link->executeStatement(
            "INSERT INTO players (id, name, race, player_type) VALUES (?, 'Dieu du test', 'dieu', 'npc')",
            [$godId]
        );
        $this->link->executeStatement("INSERT INTO players_options (player_id, name) VALUES (?, 'prayable')", [$godId]);

        try {
            $service = new TiledMapService();
            $label = (new RaceService())->getRaceByName($this->type)->getLabel();
            $at = static fn(array $rows): array => array_values(array_filter($rows, static fn(array $r): bool => $r['x'] === 1))[0];

            $this->assertContains(['id' => $godId, 'name' => 'Dieu du test'], $service->exportPlan(self::PLAN, 0)['gods']);

            $service->importPlan(self::PLAN, 0, [
                'buildings' => [['x' => 1, 'y' => 1, 'name' => $this->type, 'params' => (string) $godId]],
            ], $service->exportPlan(self::PLAN, 0)['version']);

            $export = $service->exportPlan(self::PLAN, 0);
            $this->assertSame((string) $godId, $at($export['layers']['buildings'])['params']);
            $this->assertSame($label . ' de Dieu du test', $this->nameAt(1, 1));

            // In-game consecration after the pull: the pulled version is stale
            $this->link->executeStatement(
                'UPDATE players SET godId = 0 WHERE id = ?',
                [$at($export['layers']['buildings'])['id']]
            );
            try {
                $service->importPlan(self::PLAN, 0, ['buildings' => $export['layers']['buildings']], $export['version']);
                $this->fail('a god changed since the pull must refuse the push');
            } catch (\RuntimeException $e) {
                $this->assertSame(409, $e->getCode());
            }

            // Unknown or non-prayable god: skipped, the building stays
            $export = $service->exportPlan(self::PLAN, 0);
            $result = $service->importPlan(self::PLAN, 0, [
                'buildings' => [['x' => 1, 'y' => 1, 'name' => $this->type, 'params' => '-1']],
            ], $export['version']);
            $this->assertSame(1, $result['layers']['buildings']['kept']);
            $this->assertStringContainsString('Dieu inconnu', $result['layers']['buildings']['skipped'][0] ?? '');

            // No params: no god, the bare type label
            $service->importPlan(self::PLAN, 0, [
                'buildings' => [['x' => 1, 'y' => 1, 'name' => $this->type, 'params' => (string) $godId]],
            ], $service->exportPlan(self::PLAN, 0)['version']);
            $service->importPlan(self::PLAN, 0, [
                'buildings' => [['x' => 1, 'y' => 1, 'name' => $this->type]],
            ], $service->exportPlan(self::PLAN, 0)['version']);
            $this->assertSame('', $at($service->exportPlan(self::PLAN, 0)['layers']['buildings'])['params']);
            $this->assertSame($label, $this->nameAt(1, 1));
        } finally {
            $this->link->executeStatement('DELETE FROM players_options WHERE player_id = ?', [$godId]);
            $this->link->executeStatement('DELETE FROM players WHERE id = ?', [$godId]);
        }
    }

    private function nameAt(int $x, int $y): string
    {
        return (string) $this->link->fetchOne(
            'SELECT p.name FROM players p JOIN coords c ON c.id = p.coords_id WHERE c.plan = ? AND c.x = ? AND c.y = ?',
            [self::PLAN, $x, $y]
        );
    }

    private function cleanupFixtures(): void
    {
        $this->purgePlan($this->link, self::PLAN);
    }
}
