<?php

namespace Tests\Various;

use App\Service\PlanAdminService;
use App\Service\PlanConfigService;
use App\Service\PlanService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\PlantsResourcesTrait;

/**
 * Cycle de vie admin des plans (PlanAdminService) : création vierge (coord
 * d'amorce + config minimale), clonage ensembliste (lignes joueur exclues,
 * endTime non copié, config surchargée) et suppression avec bilan préalable
 * (joueur = blocage absolu, PNJ = forçable avec cascade).
 *
 * DB-backed ; skip propre quand la base est inaccessible — même convention
 * que FactionImportExportTest. Tous les plans de test sont préfixés
 * plan_test_ et nettoyés par clé naturelle.
 */
class PlanAdminServiceTest extends TestCase
{
    use PlantsResourcesTrait;

    private const SRC = 'plan_test_adm_src';
    private const CLONE = 'plan_test_adm_clone';
    private const BLANK = 'plan_test_adm_blank';

    /** Ids de personnages de fixture, hors de portée des ids réels. */
    private const PLAYER_ID = 990001;
    private const NPC_ID = -990001;
    /** Un bâtiment porte un id POSITIF : c'est tout l'objet du test dédié. */
    private const BUILDING_ID = 990002;

    protected function setUp(): void
    {
        $this->bootstrapOrSkip();
        $this->cleanupFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures();
    }

    public function testCreateBlankPlanWritesConfigAndSeedCoord(): void
    {
        // View::get_coords_id() trace la coord d'amorce via error_log ;
        (new PlanAdminService())->createBlankPlan(self::BLANK, ['name' => 'Plan de test', 'player_visibility' => 'false']);

        $config = $this->readConfig(self::BLANK);
        $this->assertNotNull($config);
        $this->assertSame('Plan de test', $config['name']);
        $this->assertFalse($config['player_visibility']);

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

        // Config orpheline, sans coords — le trou que createPlan() seul ne voit pas
        (new PlanConfigService())->replace(self::BLANK, ['name' => 'Orphelin']);
        try {
            $service->createBlankPlan(self::BLANK);
            $this->fail('Plan à config orpheline accepté');
        } catch (RuntimeException $e) {
            $this->assertSame(409, $e->getCode());
        }
    }

    public function testClonePlanCopiesAuthoredContentOnly(): void
    {
        $this->seedSourcePlan();
        $this->seedSourceResources();
        $link = $this->link();

        $report = (new PlanAdminService())->clonePlan(self::SRC, self::CLONE, ['name' => 'Clone de test']);

        $this->assertSame(3, $report['coords']);
        $this->assertSame(1, $report['layers']['tiles']);
        $this->assertSame(2, $report['layers']['resources'], 'les ressources voyagent, entités comprises');
        $this->assertSame(1, $report['layers']['elements']);

        /* Les ressources du clone sont des ENTITÉS, debout au même endroit,
           et celle qui était à sec l'est restée. */
        $resources = $link->fetchAllAssociative(
            "SELECT p.race, c.x, c.y, r.exhausted_at
               FROM players p
               JOIN coords c ON c.id = p.coords_id
          LEFT JOIN resources r ON r.player_id = p.id
              WHERE p.player_type = 'resource' AND c.plan = ?
           ORDER BY p.race",
            [self::CLONE]
        );
        $this->assertCount(2, $resources);
        $this->assertSame('arbre1', $resources[0]['race']);
        $this->assertNull($resources[0]['exhausted_at'], 'la ressource debout le reste');
        $this->assertSame('arbre2', $resources[1]['race']);
        $this->assertNotNull($resources[1]['exhausted_at'], 'la ressource à sec aussi');

        $this->assertSame(
            0,
            (int) $link->fetchOne(
                'SELECT COUNT(*) FROM map_resources m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
                [self::CLONE]
            ),
            'rien n\'atterrit dans la table retirée'
        );

        // endTime est de l'état runtime : jamais copié (défaut schéma)
        $endTime = $link->fetchOne(
            'SELECT m.endTime FROM map_elements m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
            [self::CLONE]
        );
        $this->assertSame(0, (int) $endTime);

        // Config copiée avec surcharge du nom
        $config = $this->readConfig(self::CLONE);
        $this->assertSame('Clone de test', $config['name']);
        $this->assertFalse($config['player_visibility'], 'le reste de la config source voyage');
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
        /* The shared seed already minted this character; put it on the plan,
           which is what this case is about. */
        $this->seedBuilder();
        $link->executeStatement(
            'UPDATE players SET coords_id = ? WHERE id = ?',
            [$coordsId, self::PLAYER_ID]
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

    /**
     * Un bâtiment n'est pas un joueur, et il ne survit pas à la purge.
     *
     * Le service reconnaissait ses habitants au SIGNE de leur identifiant :
     * un bâtiment, positif depuis la conversion des murs, était compté comme
     * un joueur — l'écran de zone dangereuse annonçait des centaines de
     * joueurs sur des plans qui n'en avaient aucun — puis survivait à la
     * cascade et faisait échouer la suppression des coords sur la clé
     * étrangère, annulant la purge entière.
     */
    public function testBuildingIsNotCountedAsPlayerAndIsCascaded(): void
    {
        $this->seedSourcePlan();
        $link = $this->link();
        $coordsId = (int) $link->fetchOne('SELECT id FROM coords WHERE plan = ? LIMIT 1', [self::SRC]);

        $link->executeStatement(
            'INSERT INTO players (id, player_type, name, coords_id, race) VALUES (?, ?, ?, ?, ?)',
            [self::BUILDING_ID, 'building', 'Mur de test plans', $coordsId, 'mur_pierre']
        );
        // Les satellites qui bloquaient la suppression avant correction.
        $link->executeStatement(
            "INSERT INTO buildings (player_id, build_state) VALUES (?, 'built')",
            [self::BUILDING_ID]
        );
        $link->executeStatement(
            "INSERT INTO players_options (player_id, name) VALUES (?, 'raceHint')",
            [self::BUILDING_ID]
        );
        $link->executeStatement(
            "INSERT INTO players_bonus (player_id, name, n) VALUES (?, 'pv', -12)",
            [self::BUILDING_ID]
        );

        $service = new PlanAdminService();

        $counts = $service->countCharactersOnPlan(self::SRC);
        $this->assertSame(0, (int) $counts['players'], 'un bâtiment n\'est pas un joueur');

        $report = $service->deletePlan(self::SRC, true);

        $this->assertSame(0, (int) $link->fetchOne('SELECT COUNT(*) FROM coords WHERE plan = ?', [self::SRC]));
        $this->assertSame(0, (int) $link->fetchOne('SELECT COUNT(*) FROM players WHERE id = ?', [self::BUILDING_ID]));
        $this->assertSame(0, (int) $link->fetchOne('SELECT COUNT(*) FROM buildings WHERE player_id = ?', [self::BUILDING_ID]));
        $this->assertSame(0, (int) $link->fetchOne('SELECT COUNT(*) FROM players_options WHERE player_id = ?', [self::BUILDING_ID]));
        $this->assertSame(0, (int) $link->fetchOne('SELECT COUNT(*) FROM players_bonus WHERE player_id = ?', [self::BUILDING_ID]));
    }

    public function testDeletePlanForcedCascadesNpcsAndRemovesEverything(): void
    {
        $this->seedSourcePlan();
        $link = $this->link();
        $coordsId = (int) $link->fetchOne('SELECT id FROM coords WHERE plan = ? LIMIT 1', [self::SRC]);
        $link->executeStatement(
            /* player_type explicite : le service ne reconnaît plus un PNJ au
             * signe de son identifiant. Player::put_player($name,$race,pnj:true)
             * écrit 'npc' depuis toujours ; la fixture s'appuyait sur la seule
             * convention d'id, qui ne distingue plus un PNJ d'un bâtiment. */
            'INSERT INTO players (id, player_type, name, coords_id, race) VALUES (?, ?, ?, ?, ?)',
            [self::NPC_ID, 'npc', 'PNJ de test plans', $coordsId, 'nain']
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
        foreach (['tiles', 'resources', 'elements'] as $layer) {
            $this->assertSame(
                0,
                (int) $link->fetchOne(
                    'SELECT COUNT(*) FROM map_' . $layer . ' m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
                    [self::SRC]
                )
            );
        }
        $this->assertNull($this->readConfig(self::SRC));
        $this->assertContains('config du plan (base)', $report['files']);
    }

    /**
     * Plan source de fixture : 3 coords en z=0, une tuile, deux murs (dont
     * un construit par un joueur réel existant), un élément avec endTime,
     * et une config de plan.
     */
    /**
     * Un bâtiment n'est pas une ligne de carte mais une ENTITÉ : la boucle
     * des couches ne le voyait pas, donc un clone le perdait. Sans effet tant
     * que c'étaient des murs ; le jour où l'autel devient une entité, cloner
     * un plan lui retire ses autels.
     */
    public function testCloneCarriesDecorBuildingsButNotWhatAPlayerBuilt(): void
    {
        $this->seedSourcePlan();

        $coords = (object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => self::SRC];
        $buildings = new \App\Service\BuildingService();
        $buildings->place('barricade', $coords, null, '', null, overScenery: true);

        /* Une case libre à soi : (0,1) porte déjà un élément du seed, et une
           case occupée fait refuser la pose. */
        $this->link()->executeStatement(
            'INSERT INTO coords (x, y, z, plan) VALUES (5, 5, 0, ?)',
            [self::SRC]
        );

        $owned = (object) ['x' => 5, 'y' => 5, 'z' => 0, 'plan' => self::SRC];

        $buildings->place('barricade', $owned, $this->seedBuilder(), '', null, overScenery: true);

        (new PlanAdminService())->clonePlan(self::SRC, self::CLONE);

        $onClone = $this->link()->fetchAllAssociative(
            "SELECT p.race, p.owner_id FROM buildings b
               JOIN players p ON p.id = b.player_id
               JOIN coords c ON c.id = p.coords_id
              WHERE c.plan = ?",
            [self::CLONE]
        );

        $this->assertCount(1, $onClone, 'le décor suit, ce qu\'un joueur a bâti reste chez lui');
        $this->assertSame('barricade', $onClone[0]['race']);
        $this->assertNull($onClone[0]['owner_id']);
    }

    /**
     * Une structure tient une case par son `coords_id` : elle retient la clé
     * étrangère au moment de supprimer les coordonnées. Absente du bilan, la
     * suppression le passait puis échouait sur une erreur de base.
     */
    public function testDeletePreflightRefusesAPlanHoldingStructures(): void
    {
        $this->seedSourcePlan();

        (new \App\Service\BuildingService())->place(
            'barricade',
            (object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => self::SRC],
            null,
            '',
            null,
            overScenery: true
        );

        $preflight = (new PlanAdminService())->deletePreflight(self::SRC);

        $structures = array_values(array_filter(
            $preflight['blockers'],
            static fn(array $b): bool => $b['check'] === 'structures'
        ));

        $this->assertCount(1, $structures, 'le bilan doit nommer les structures');
        $this->assertSame(1, $structures[0]['count']);
        $this->assertTrue($structures[0]['forceable'], 'la suppression forcée les emporte déjà');
    }

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
            'INSERT INTO map_elements (coords_id, name, endTime) VALUES (?, ?, 12345)',
            [$ids['0,1'], 'feu_test']
        );

        (new PlanConfigService())->replace(self::SRC, ['name' => 'Source de test', 'player_visibility' => false]);
    }

    /**
     * Deux ressources sur le plan source : une debout, une à sec.
     *
     * À part du seed commun : une ressource est une ENTITÉ, et les cas de
     * suppression comptent les habitants d'un plan — leur faire porter des
     * arbres leur ferait dire autre chose que ce qu'ils vérifient.
     */
    private function seedSourceResources(): void
    {
        $link = $this->link();

        foreach ([['arbre1', 1, 0, -1], ['arbre2', 0, 1, -2]] as [$name, $x, $y, $damages]) {
            $coordsId = (int) $link->fetchOne(
                'SELECT id FROM coords WHERE plan = ? AND z = 0 AND x = ? AND y = ?',
                [self::SRC, $x, $y]
            );

            $this->plantResource($link, $name, $coordsId, self::SRC, $x, $y, 0, $damages);
        }
    }

    /** The fixture character every player-built row hangs from. */
    private function seedBuilder(): int
    {
        $this->link()->executeStatement(
            "INSERT IGNORE INTO players (id, player_type, name, race) VALUES (?, 'real', ?, ?)",
            [self::PLAYER_ID, 'Bâtisseur de test plans', 'nain']
        );

        return self::PLAYER_ID;
    }

    private function cleanupFixtures(): void
    {
        global $link;
        if (!isset($link) || !$link instanceof Connection) {
            return;
        }

        /* Map layers first: a player-built wall holds its builder by foreign
           key, so the character cannot leave before what it built. */
        foreach (['tiles', 'routes', 'plants', 'resources', 'elements', 'foregrounds', 'triggers', 'dialogs', 'items'] as $layer) {
            $link->executeStatement(
                "DELETE m FROM map_{$layer} m JOIN coords c ON c.id = m.coords_id WHERE c.plan LIKE 'plan_test_adm_%'"
            );
        }

        foreach (['buildings', 'players_options', 'players_bonus'] as $satellite) {
            $link->executeStatement(
                "DELETE FROM {$satellite} WHERE player_id IN (?, ?, ?)",
                [self::PLAYER_ID, self::NPC_ID, self::BUILDING_ID]
            );
        }

        $link->executeStatement(
            'DELETE FROM players WHERE id IN (?, ?, ?)',
            [self::PLAYER_ID, self::NPC_ID, self::BUILDING_ID]
        );
        /* Le clone POSE des entités : elles retiennent les coordonnées par
           leur clé étrangère, et sans elles le nettoyage échouait. */
        foreach ($link->fetchFirstColumn(
            "SELECT p.id FROM players p JOIN coords c ON c.id = p.coords_id WHERE c.plan LIKE 'plan_test_adm_%'"
        ) as $entityId) {
            $link->executeStatement('DELETE FROM entity_cells WHERE player_id = ?', [(int) $entityId]);
            /* Le satellite d'abord : `fk_buildings_player` n'a pas de cascade,
               c'est le service qui le défait à la main partout ailleurs. */
            foreach (['buildings', 'unique_objects', 'resources'] as $satellite) {
                $link->executeStatement("DELETE FROM {$satellite} WHERE player_id = ?", [(int) $entityId]);
            }
            \App\Service\BuildingService::deleteEntityRows($link, (int) $entityId);
        }

        $link->executeStatement(
            "DELETE ec FROM entity_cells ec JOIN coords c ON c.id = ec.coords_id WHERE c.plan LIKE 'plan_test_adm_%'"
        );
        $link->executeStatement("DELETE FROM coords WHERE plan LIKE 'plan_test_adm_%'");

        $link->executeStatement("DELETE FROM plans WHERE slug LIKE 'plan_test_adm_%'");
        PlanService::forget();
        // L'identity map gagnerait sur la base : une entité Plan d'un test
        // précédent masquerait la ligne recréée.
        \App\Factory\EntityManagerFactory::getEntityManager()->clear();
    }

    /** Config courante du plan (forme JSON legacy), ou null. */
    private function readConfig(string $plan): ?array
    {
        PlanService::forget($plan);

        return (new PlanConfigService())->readFull($plan);
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

    }
}
