<?php

namespace Tests\Various;

use App\Service\ImportExport\ExporterRegistry;
use App\Service\ImportExport\ImporterRegistry;
use App\Service\ImportExport\PlanExporter;
use App\Service\ImportExport\PlanImporter;
use App\Service\Map\EntityPlacementService;
use App\Service\PlanConfigService;
use App\Service\PlanService;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyBootstrapTrait;

/**
 * Import/export de plans par bundles JSON : le payload porte l'identité par
 * nom de plan et chaque case par (x, y, z) — aucun id de base. L'export
 * exclut les lignes construites par des joueurs ; l'import est un
 * create-or-replace du contenu authoré qui les préserve. Un aller-retour
 * export → import → export est idempotent.
 *
 * DB-backed ; skip propre quand la base est inaccessible — même convention
 * que FactionImportExportTest.
 */
class PlanImportExportTest extends TestCase
{
    use LegacyBootstrapTrait;

    private const SRC = 'plan_test_ie_src';
    private const IMPORTED = 'plan_test_ie_imp';

    /** Fixture id, out of reach of real ones. */
    private const BUILDER_ID = 990301;

    protected function setUp(): void
    {
        $this->bootstrapLegacyOrSkip('coords');
        $this->cleanupFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures();
    }

    public function testPlanTypeIsRegisteredInBothRegistries(): void
    {
        $this->assertNotNull((new ExporterRegistry())->exporterFor('plan'));
        $this->assertNotNull((new ImporterRegistry())->importerFor('plan'));
    }

    public function testExportCarriesNaturalKeysOnlyAndSkipsPlayerRows(): void
    {
        $this->seedSourcePlan();

        $payload = (new PlanExporter())->exportOne(self::SRC);

        $this->assertSame(self::SRC, $payload['plan']);
        $this->assertSame('Source de test', $payload['config']['name']);
        $this->assertCount(4, $payload['coords'], 'toutes les coords, y compris sans contenu');
        $this->assertContains([0, 1, 0], $payload['coords']);

        $this->assertCount(1, $payload['layers']['resources'], 'le mur player_id est exclu de l\'export');
        $wall = $payload['layers']['resources'][0];
        $this->assertSame('arbre1', $wall['name']);
        $this->assertSame(-1, (int) $wall['damages']);
        $this->assertArrayNotHasKey('id', $wall, 'jamais d\'id DB dans un bundle');
        $this->assertArrayNotHasKey('player_id', $wall);
        $this->assertCount(1, $payload['layers']['routes'], 'the road entity travels as a routes row');
        $this->assertSame('route', $payload['layers']['routes'][0]['name']);
        $this->assertCount(1, $payload['layers']['elements'], 'un élément daté est de l\'état runtime, hors bundle');
        $this->assertSame('feu_test', $payload['layers']['elements'][0]['name']);
        $this->assertArrayNotHasKey('endTime', $payload['layers']['elements'][0], 'endTime = état runtime, hors bundle');
    }

    public function testImportRoundTripCreatesThenUpdatesAPlan(): void
    {
        $this->seedSourcePlan();
        $exporter = new PlanExporter();
        $importer = new PlanImporter();

        $payload = $exporter->exportOne(self::SRC);
        $payload['plan'] = self::IMPORTED;
        $payload['config']['name'] = 'Plan importé';

        $preview = $importer->preview([$payload]);
        $this->assertSame([self::IMPORTED], $preview->created());
        $this->assertFalse($preview->hasRejections());
        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT COUNT(*) FROM coords WHERE plan = ?', [self::IMPORTED]),
            'preview : rien d\'écrit'
        );

        $report = $importer->import([$payload]);
        $this->assertSame([self::IMPORTED], $report->created());

        // Aller-retour : le ré-export du plan importé == le payload (l'export
        // est trié (z, y, x) donc directement comparable)
        $reExported = $exporter->exportOne(self::IMPORTED);
        $this->assertSame($payload['coords'], $reExported['coords']);
        $this->assertEquals($payload['layers'], $reExported['layers']);
        $this->assertSame('Plan importé', $reExported['config']['name']);

        // Ré-import du même bundle : mise à jour, pas de doublon
        $again = $importer->import([$payload]);
        $this->assertSame([self::IMPORTED], $again->updated());
        $this->assertEquals($payload['layers'], $exporter->exportOne(self::IMPORTED)['layers'], 'ré-import idempotent');
    }

    /**
     * Old maps carry a road and a wall on the same cell. The structure wins
     * and the road is not placed — the other way round refused the wall.
     */
    public function testAStructureTakesTheCellFromARoad(): void
    {
        $importer = new PlanImporter();

        $payload = [
            'plan' => self::IMPORTED,
            'config' => ['name' => 'Plan encombré'],
            'coords' => [[0, 0, 0], [1, 0, 0]],
            'layers' => [
                'routes' => [
                    ['name' => 'route', 'x' => 0, 'y' => 0, 'z' => 0],
                    ['name' => 'route', 'x' => 1, 'y' => 0, 'z' => 0],
                ],
                'buildings' => [
                    ['name' => 'mur_pierre', 'x' => 0, 'y' => 0, 'z' => 0],
                ],
            ],
        ];

        $report = $importer->import([$payload]);

        $this->assertSame([self::IMPORTED], $report->created());
        $this->assertSame(
            [],
            array_filter($report->warnings(), static fn (array $w): bool => str_contains($w['message'], 'Bâtiment non posé')),
            'le mur est posé, pas refusé'
        );
        $this->assertNotSame(
            [],
            array_filter($report->warnings(), static fn (array $w): bool => str_contains($w['message'], 'sous une structure')),
            "l'import dit ce qu'il a laissé de côté"
        );

        $onCells = $this->link->fetchAllKeyValue(
            "SELECT c.x, p.player_type FROM players p JOIN coords c ON c.id = p.coords_id
              WHERE c.plan = ? AND p.player_type IN ('building', 'route') ORDER BY c.x",
            [self::IMPORTED]
        );
        $this->assertSame(['0' => 'building', '1' => 'route'], $onCells);
    }

    /**
     * A plan is loaded step by step: interrupted, it resumes where it stopped
     * instead of starting over.
     */
    public function testAnInterruptedImportResumesWhereItStopped(): void
    {
        $this->seedSourcePlan();
        $importer = new PlanImporter();
        $report = new \App\Service\ImportExport\ImportReport();

        $payload = (new PlanExporter())->exportOne(self::SRC);
        $payload['plan'] = self::IMPORTED;

        $run = $importer->runFor($importer->payloadFor($payload), $report);
        $this->assertGreaterThan(1, $run->total(), 'un plan se découpe en étapes');

        // Two steps, then we walk away: the cursor stays in the database.
        $run->next();
        $run->next();
        $this->assertSame(2, $run->step());
        $this->assertFalse($run->isDone());

        $fingerprint = \App\Service\ImportExport\PlanImportProgress::fingerprint($importer->payloadFor($payload));
        $this->assertSame(
            2,
            (new \App\Service\ImportExport\PlanImportProgress())->stepOf($fingerprint, self::IMPORTED)
        );

        // The same bundle picked up later: it restarts at step 2.
        $resumed = $importer->runFor($importer->payloadFor($payload), $report);
        $this->assertSame(2, $resumed->step(), 'le nouveau run repart de l\'étape enregistrée');

        $resumed->runToEnd();

        $this->assertEqualsCanonicalizing(
            $payload['layers'],
            (new PlanExporter())->exportOne(self::IMPORTED)['layers'],
            'le plan repris vaut le plan chargé d\'un trait'
        );
        $this->assertSame(
            0,
            (new \App\Service\ImportExport\PlanImportProgress())->stepOf($fingerprint, self::IMPORTED),
            'fini : plus rien à reprendre'
        );
    }

    public function testImportRejectsInvalidPayloadsWithoutWriting(): void
    {
        $importer = new PlanImporter();

        $cases = [
            'couche inconnue'   => ['plan' => self::IMPORTED, 'layers' => ['loot' => []]],
            'ligne sans nom'    => ['plan' => self::IMPORTED, 'layers' => ['resources' => [['x' => 0, 'y' => 0, 'z' => 0]]]],
            'nom de plan'       => ['plan' => 'Pas Un Plan'],
            'coords non triple' => ['plan' => self::IMPORTED, 'coords' => [[1, 2]]],
        ];

        foreach ($cases as $label => $payload) {
            $report = $importer->import([$payload]);
            $this->assertTrue($report->hasRejections(), $label);
        }

        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT COUNT(*) FROM coords WHERE plan = ?', [self::IMPORTED]),
            'tout-ou-rien : rien d\'écrit sur rejet'
        );
    }

    public function testReplacePreservesPlayerBuiltRowsAndWarns(): void
    {
        $this->seedSourcePlan();
        $importer = new PlanImporter();

        // Remplacement : une seule tuile, plus aucune ressource ni mur authoré
        $payload = [
            'plan'   => self::SRC,
            'config' => ['name' => 'Source remplacée'],
            'coords' => [[0, 0, 0]],
            'layers' => ['tiles' => [['x' => 0, 'y' => 0, 'z' => 0, 'name' => 'sable']], 'buildings' => []],
        ];

        $preview = $importer->preview([$payload]);
        $this->assertSame([self::SRC], $preview->updated());
        $this->assertNotEmpty($preview->warnings(), 'constructions de joueurs signalées');

        $importer->import([$payload]);

        $resources = $this->link->fetchAllAssociative(
            'SELECT p.race FROM players p JOIN coords c ON c.id = p.coords_id
              WHERE p.player_type = "resource" AND c.plan = ?',
            [self::SRC]
        );
        $this->assertSame([], $resources, 'la ressource authorée n\'est plus dessinée : elle est retirée');

        $built = $this->link->fetchAllAssociative(
            'SELECT p.race, p.owner_id FROM players p
               JOIN buildings b ON b.player_id = p.id
               JOIN coords c ON c.id = p.coords_id
              WHERE c.plan = ?',
            [self::SRC]
        );
        $this->assertCount(1, $built, 'la construction du joueur survit au remplacement');
        $this->assertSame('palissade', $built[0]['race']);
        $this->assertNotNull($built[0]['owner_id']);

        // Les coords existantes survivent (FK joueurs/logs) même hors payload
        $this->assertSame(
            4,
            (int) $this->link->fetchOne('SELECT COUNT(*) FROM coords WHERE plan = ?', [self::SRC])
        );

        PlanService::forget(self::SRC);
        $config = (new PlanConfigService())->readFull(self::SRC);
        $this->assertSame('Source remplacée', $config['name'], 'config remplacée en entier');
    }

    /**
     * A tile, an authored resource, a road, a decor wall, a player-built
     * palissade, two elements, and one coordinate without content.
     *
     * Resource, road and buildings are placed the way the game places them:
     * as entities, not layer rows. That is what the import has to find back
     * — replaceable for the authored ones, untouchable for the player's.
     */
    private function seedSourcePlan(): void
    {
        $ids = [];
        foreach ([[0, 0], [1, 0], [0, 1], [1, 1]] as [$x, $y]) {
            $this->link->executeStatement('INSERT INTO coords (x, y, z, plan) VALUES (?, ?, 0, ?)', [$x, $y, self::SRC]);
            $ids[$x . ',' . $y] = (int) $this->link->lastInsertId();
        }

        $this->link->executeStatement('INSERT INTO map_tiles (coords_id, name, foreground) VALUES (?, ?, 0)', [$ids['0,0'], 'grass']);

        (new EntityPlacementService($this->link))->create(
            'resource',
            'arbre1',
            $ids['1,0'],
            'Arbre',
            'img/walls/arbre1.png'
        );

        (new EntityPlacementService($this->link))->create(
            'route',
            'route',
            $ids['1,1'],
            'Route',
            'img/routes/route.png'
        );

        /* Seed the builder: a fresh database holds no player to borrow, and the
         * case exists to prove a player-built row does NOT travel. */
        $builderId = self::BUILDER_ID;
        $this->link->executeStatement('DELETE FROM players WHERE id = ?', [$builderId]);
        $this->link->executeStatement(
            "INSERT INTO players (id, player_type, name, race) VALUES (?, 'real', ?, ?)",
            [$builderId, 'Bâtisseur de test plans', 'nain']
        );

        $palissadeId = (new EntityPlacementService($this->link))->create(
            'building',
            'palissade',
            $ids['0,1'],
            'Palissade',
            'img/walls/palissade.png'
        );
        $this->link->executeStatement(
            'INSERT INTO buildings (player_id, build_state) VALUES (?, ?)',
            [$palissadeId, 'built']
        );
        // Le propriétaire vit sur l'entité depuis qu'être possédé a cessé
        // d'être un privilège de bâtiment.
        $this->link->executeStatement(
            'UPDATE players SET owner_id = ? WHERE id = ?',
            [(int) $builderId, $palissadeId]
        );

        // Decor: no owner, no faction, built — the wall a bundle must carry.
        $wallId = (new EntityPlacementService($this->link))->create(
            'building',
            'mur_pierre',
            $ids['0,0'],
            'Mur',
            'img/walls/mur_pierre.png'
        );
        $this->link->executeStatement('INSERT INTO buildings (player_id, build_state) VALUES (?, ?)', [$wallId, 'built']);

        $this->link->executeStatement('INSERT INTO map_elements (coords_id, name, endTime) VALUES (?, ?, 0)', [$ids['0,1'], 'feu_test']);
        // Dated = the game's: never exported
        $this->link->executeStatement('INSERT INTO map_elements (coords_id, name, endTime) VALUES (?, ?, 12345)', [$ids['0,0'], 'sang_test']);

        (new PlanConfigService())->replace(self::SRC, ['name' => 'Source de test', 'player_visibility' => false]);
    }

    private function cleanupFixtures(): void
    {
        if ($this->link === null) {
            return;
        }

        foreach (['tiles', 'routes', 'plants', 'resources', 'elements', 'foregrounds', 'triggers', 'dialogs', 'items'] as $layer) {
            $this->link->executeStatement(
                "DELETE m FROM map_{$layer} m JOIN coords c ON c.id = m.coords_id WHERE c.plan LIKE 'plan_test_ie_%'"
            );
        }
        /* Ce qui est posé sur le plan est une entité : ses cases tiennent la FK
         * vers coords (RESTRICT), et les satellites n'ont pas de FK du tout —
         * ils ne partent donc avec rien. */
        foreach (['resources', 'buildings'] as $satellite) {
            $this->link->executeStatement(
                "DELETE s FROM {$satellite} s
                   JOIN players p ON p.id = s.player_id
                   JOIN coords c ON c.id = p.coords_id
                  WHERE c.plan LIKE 'plan_test_ie_%'"
            );
        }
        $this->link->executeStatement(
            "DELETE p FROM players p
               JOIN coords c ON c.id = p.coords_id
              WHERE p.player_type IN ('resource', 'building', 'route') AND c.plan LIKE 'plan_test_ie_%'"
        );

        /* The builder stands on no cell, so the join above never reaches it. */
        $this->link->executeStatement('DELETE FROM players WHERE id = ?', [self::BUILDER_ID]);
        $this->link->executeStatement("DELETE FROM plan_import_progress WHERE plan LIKE 'plan_test_ie_%'");

        $this->link->executeStatement("DELETE FROM coords WHERE plan LIKE 'plan_test_ie_%'");

        $this->link->executeStatement("DELETE FROM plans WHERE slug LIKE 'plan_test_ie_%'");
        PlanService::forget();
        // L'identity map gagnerait sur la base : une entité Plan d'un test
        // précédent masquerait la ligne recréée.
        \App\Factory\EntityManagerFactory::getEntityManager()->clear();
    }
}
