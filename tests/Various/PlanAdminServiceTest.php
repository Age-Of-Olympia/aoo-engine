<?php

namespace Tests\Various;

use App\Service\PlanAdminService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Cycle de vie admin des plans (PlanAdminService) : création vierge (coord
 * d'amorce + JSON minimal), clonage ensembliste (lignes joueur exclues,
 * endTime non copié, JSON surchargé) et suppression avec bilan préalable
 * (joueur = blocage absolu, PNJ = forçable avec cascade).
 *
 * DB-backed ; skip propre quand la base est inaccessible — même convention
 * que FactionImportExportTest. Tous les plans de test sont préfixés
 * plan_test_ et nettoyés par clé naturelle.
 */
class PlanAdminServiceTest extends TestCase
{
    private const SRC = 'plan_test_adm_src';
    private const CLONE = 'plan_test_adm_clone';
    private const BLANK = 'plan_test_adm_blank';

    /** Ids de personnages de fixture, hors de portée des ids réels. */
    private const PLAYER_ID = 990001;
    private const NPC_ID = -990001;

    protected function setUp(): void
    {
        $this->bootstrapOrSkip();
        $this->cleanupFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures();
    }

    public function testCreateBlankPlanWritesJsonAndSeedCoord(): void
    {
        // View::get_coords_id() trace la coord d'amorce via error_log ;
        // PHPUnit 12.1 capture error_log autour du test et l'imprime (test
        // « risky ») sauf si on le déclare attendu
        $this->expectErrorLog();

        (new PlanAdminService())->createBlankPlan(self::BLANK, ['name' => 'Plan de test', 'player_visibility' => 'false']);

        $this->assertFileExists($this->jsonPath(self::BLANK));
        $json = json_decode((string) file_get_contents($this->jsonPath(self::BLANK)), true);
        $this->assertSame('Plan de test', $json['name']);
        $this->assertFalse($json['player_visibility']);

        $link = $this->link();
        $this->assertSame(
            1,
            (int) $link->fetchOne('SELECT COUNT(*) FROM coords WHERE plan = ?', [self::BLANK]),
            'une seule coord d\'amorce'
        );
        $this->assertEquals(
            ['x' => 0, 'y' => 0, 'z' => 0],
            array_map('intval', $link->fetchAssociative('SELECT x, y, z FROM coords WHERE plan = ?', [self::BLANK]))
        );
    }

    public function testCreateBlankPlanRejectsInvalidNameAndWritesNothing(): void
    {
        try {
            (new PlanAdminService())->createBlankPlan('Pas Un Plan');
            $this->fail('Nom invalide accepté');
        } catch (RuntimeException $e) {
            $this->assertSame(400, $e->getCode());
        }
    }

    public function testCreateBlankPlanRefusesExistingCoordsOrOrphanJson(): void
    {
        $service = new PlanAdminService();

        // Coords existants (plan seedé)
        $this->seedSourcePlan();
        try {
            $service->createBlankPlan(self::SRC);
            $this->fail('Plan à coords existants accepté');
        } catch (RuntimeException $e) {
            $this->assertSame(409, $e->getCode());
        }

        // Fichier JSON orphelin, sans coords — le trou que createPlan() seul ne voit pas
        file_put_contents($this->jsonPath(self::BLANK), '{"name": "Orphelin"}');
        try {
            $service->createBlankPlan(self::BLANK);
            $this->fail('Plan à JSON orphelin accepté');
        } catch (RuntimeException $e) {
            $this->assertSame(409, $e->getCode());
        }
    }

    public function testClonePlanCopiesAuthoredContentOnly(): void
    {
        $this->seedSourcePlan();
        $link = $this->link();

        $report = (new PlanAdminService())->clonePlan(self::SRC, self::CLONE, ['name' => 'Clone de test']);

        $this->assertSame(3, $report['coords']);
        $this->assertSame(1, $report['layers']['tiles']);
        $this->assertSame(1, $report['layers']['walls'], 'le mur construit par un joueur n\'est pas copié');
        $this->assertSame(1, $report['layers']['elements']);

        // Le mur restant est bien l'authoré, et player_id n'a pas voyagé
        $walls = $link->fetchAllAssociative(
            'SELECT m.name, m.player_id, m.damages FROM map_walls m
             JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
            [self::CLONE]
        );
        $this->assertCount(1, $walls);
        $this->assertSame('arbre1', $walls[0]['name']);
        $this->assertNull($walls[0]['player_id']);
        $this->assertSame(-1, (int) $walls[0]['damages'], 'damages (intention d\'auteur) copié tel quel');

        // endTime est de l'état runtime : jamais copié (défaut schéma)
        $endTime = $link->fetchOne(
            'SELECT m.endTime FROM map_elements m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
            [self::CLONE]
        );
        $this->assertSame(0, (int) $endTime);

        // JSON copié avec surcharge du nom
        $json = json_decode((string) file_get_contents($this->jsonPath(self::CLONE)), true);
        $this->assertSame('Clone de test', $json['name']);
        $this->assertFalse($json['player_visibility'], 'le reste du JSON source voyage');
    }

    public function testClonePlanRefusesExistingTargetAndUnknownSource(): void
    {
        $service = new PlanAdminService();
        $this->seedSourcePlan();

        try {
            $service->clonePlan(self::SRC, self::SRC);
            $this->fail('Cible existante acceptée');
        } catch (RuntimeException $e) {
            $this->assertSame(409, $e->getCode());
        }

        try {
            $service->clonePlan('plan_test_adm_absent', self::CLONE);
            $this->fail('Source inconnue acceptée');
        } catch (RuntimeException $e) {
            $this->assertSame(404, $e->getCode());
        }
    }

    public function testDeletePlanIsBlockedByARealPlayerEvenForced(): void
    {
        $this->seedSourcePlan();
        $link = $this->link();
        $coordsId = (int) $link->fetchOne('SELECT id FROM coords WHERE plan = ? LIMIT 1', [self::SRC]);
        $link->executeStatement(
            'INSERT INTO players (id, name, coords_id, race) VALUES (?, ?, ?, ?)',
            [self::PLAYER_ID, 'Joueur de test plans', $coordsId, 'nain']
        );

        $service = new PlanAdminService();
        $preflight = $service->deletePreflight(self::SRC);
        $this->assertSame('players', $preflight['blockers'][0]['check']);
        $this->assertFalse($preflight['blockers'][0]['forceable']);

        try {
            $service->deletePlan(self::SRC, true);
            $this->fail('Suppression acceptée malgré un joueur présent');
        } catch (RuntimeException $e) {
            $this->assertSame(409, $e->getCode());
        }
        $this->assertGreaterThan(0, (int) $link->fetchOne('SELECT COUNT(*) FROM coords WHERE plan = ?', [self::SRC]));
    }

    public function testDeletePlanForcedCascadesNpcsAndRemovesEverything(): void
    {
        $this->seedSourcePlan();
        $link = $this->link();
        $coordsId = (int) $link->fetchOne('SELECT id FROM coords WHERE plan = ? LIMIT 1', [self::SRC]);
        $link->executeStatement(
            'INSERT INTO players (id, name, coords_id, race) VALUES (?, ?, ?, ?)',
            [self::NPC_ID, 'PNJ de test plans', $coordsId, 'nain']
        );

        $service = new PlanAdminService();

        // Sans force : le PNJ bloque
        try {
            $service->deletePlan(self::SRC);
            $this->fail('Suppression acceptée malgré un PNJ');
        } catch (RuntimeException $e) {
            $this->assertSame(409, $e->getCode());
        }

        $report = $service->deletePlan(self::SRC, true);

        $this->assertSame(1, $report['npcs']);
        $this->assertSame(3, $report['coords']);
        $this->assertNull($link->fetchOne('SELECT 1 FROM players WHERE id = ?', [self::NPC_ID]) ?: null);
        $this->assertSame(0, (int) $link->fetchOne('SELECT COUNT(*) FROM coords WHERE plan = ?', [self::SRC]));
        foreach (['tiles', 'walls', 'elements'] as $layer) {
            $this->assertSame(
                0,
                (int) $link->fetchOne(
                    'SELECT COUNT(*) FROM map_' . $layer . ' m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
                    [self::SRC]
                )
            );
        }
        $this->assertFileDoesNotExist($this->jsonPath(self::SRC));
        $this->assertContains('datas/private/plans/' . self::SRC . '.json', $report['files']);
    }

    /**
     * Plan source de fixture : 3 coords en z=0, une tuile, deux murs (dont
     * un construit par un joueur réel existant), un élément avec endTime,
     * et un fichier JSON.
     */
    private function seedSourcePlan(): void
    {
        $link = $this->link();

        $ids = [];
        foreach ([[0, 0], [1, 0], [0, 1]] as [$x, $y]) {
            $link->executeStatement(
                'INSERT INTO coords (x, y, z, plan) VALUES (?, ?, 0, ?)',
                [$x, $y, self::SRC]
            );
            $ids[$x . ',' . $y] = (int) $link->lastInsertId();
        }

        $link->executeStatement(
            'INSERT INTO map_tiles (coords_id, name, foreground) VALUES (?, ?, 0)',
            [$ids['0,0'], 'grass']
        );
        $link->executeStatement(
            'INSERT INTO map_walls (coords_id, name, damages) VALUES (?, ?, -1)',
            [$ids['1,0'], 'arbre1']
        );

        // Mur « construit par un joueur » : FK players → un joueur réel du seed
        $builderId = $link->fetchOne('SELECT id FROM players WHERE id > 0 ORDER BY id LIMIT 1');
        if ($builderId === false) {
            $this->markTestSkipped('Aucun joueur en base pour porter le mur player_id.');
        }
        $link->executeStatement(
            'INSERT INTO map_walls (coords_id, name, damages, player_id) VALUES (?, ?, 0, ?)',
            [$ids['1,0'], 'palissade', (int) $builderId]
        );

        $link->executeStatement(
            'INSERT INTO map_elements (coords_id, name, endTime) VALUES (?, ?, 12345)',
            [$ids['0,1'], 'feu_test']
        );

        file_put_contents(
            $this->jsonPath(self::SRC),
            json_encode(['name' => 'Source de test', 'player_visibility' => false], JSON_PRETTY_PRINT) . "\n"
        );
        json()->forget('plans', self::SRC);
    }

    private function cleanupFixtures(): void
    {
        global $link;
        if (!isset($link) || !$link instanceof Connection) {
            return;
        }

        $link->executeStatement(
            'DELETE FROM players WHERE id IN (?, ?)',
            [self::PLAYER_ID, self::NPC_ID]
        );

        foreach (['tiles', 'routes', 'plants', 'walls', 'elements', 'foregrounds', 'triggers', 'dialogs', 'items'] as $layer) {
            $link->executeStatement(
                "DELETE m FROM map_{$layer} m JOIN coords c ON c.id = m.coords_id WHERE c.plan LIKE 'plan_test_adm_%'"
            );
        }
        $link->executeStatement("DELETE FROM coords WHERE plan LIKE 'plan_test_adm_%'");

        foreach ([self::SRC, self::CLONE, self::BLANK] as $plan) {
            if (file_exists($this->jsonPath($plan))) {
                unlink($this->jsonPath($plan));
            }
            json()->forget('plans', $plan);
        }
    }

    private function jsonPath(string $plan): string
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/datas/private/plans/' . $plan . '.json';
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

        // Les services de plan résolvent fichiers JSON et PNG via
        // DOCUMENT_ROOT ; en CLI il vaut la racine du dépôt (== docroot web)
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

        if (!is_dir($_SERVER['DOCUMENT_ROOT'] . '/datas/private/plans')) {
            $this->markTestSkipped('datas/private/plans absent (datas non provisionné).');
        }
    }
}
