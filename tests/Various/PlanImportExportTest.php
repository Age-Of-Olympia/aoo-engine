<?php

namespace Tests\Various;

use App\Service\ImportExport\ExporterRegistry;
use App\Service\ImportExport\ImporterRegistry;
use App\Service\ImportExport\PlanExporter;
use App\Service\ImportExport\PlanImporter;
use App\Service\Map\EntityPlacementService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

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
    private const SRC = 'plan_test_ie_src';
    private const IMPORTED = 'plan_test_ie_imp';

    /** Fixture id, out of reach of real ones. */
    private const BUILDER_ID = 990301;

    protected function setUp(): void
    {
        $this->bootstrapOrSkip();
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
        $this->assertCount(3, $payload['coords'], 'toutes les coords, y compris sans contenu');
        $this->assertContains([0, 1, 0], $payload['coords']);

        $this->assertCount(1, $payload['layers']['resources'], 'le mur player_id est exclu de l\'export');
        $wall = $payload['layers']['resources'][0];
        $this->assertSame('arbre1', $wall['name']);
        $this->assertSame(-1, (int) $wall['damages']);
        $this->assertArrayNotHasKey('id', $wall, 'jamais d\'id DB dans un bundle');
        $this->assertArrayNotHasKey('player_id', $wall);
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
            (int) $this->link()->fetchOne('SELECT COUNT(*) FROM coords WHERE plan = ?', [self::IMPORTED]),
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
            (int) $this->link()->fetchOne('SELECT COUNT(*) FROM coords WHERE plan = ?', [self::IMPORTED]),
            'tout-ou-rien : rien d\'écrit sur rejet'
        );
    }

    public function testReplacePreservesPlayerBuiltRowsAndWarns(): void
    {
        $this->seedSourcePlan();
        $link = $this->link();
        $importer = new PlanImporter();

        // Remplacement : une seule tuile, plus aucun mur authoré
        $payload = [
            'plan'   => self::SRC,
            'config' => ['name' => 'Source remplacée'],
            'coords' => [[0, 0, 0]],
            'layers' => ['tiles' => [['x' => 0, 'y' => 0, 'z' => 0, 'name' => 'sable']]],
        ];

        $preview = $importer->preview([$payload]);
        $this->assertSame([self::SRC], $preview->updated());
        $this->assertNotEmpty($preview->warnings(), 'constructions de joueurs signalées');

        $importer->import([$payload]);

        $resources = $link->fetchAllAssociative(
            'SELECT p.race FROM players p JOIN coords c ON c.id = p.coords_id
              WHERE p.player_type = "resource" AND c.plan = ?',
            [self::SRC]
        );
        $this->assertSame([], $resources, 'la ressource authorée n\'est plus dessinée : elle est retirée');

        $built = $link->fetchAllAssociative(
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
            3,
            (int) $link->fetchOne('SELECT COUNT(*) FROM coords WHERE plan = ?', [self::SRC])
        );

        $json = json_decode((string) file_get_contents($this->jsonPath(self::SRC)), true);
        $this->assertSame('Source remplacée', $json['name'], 'fichier JSON remplacé en entier');
    }

    /**
     * 3 coords, une tuile, une ressource authorée, une construction de joueur,
     * un élément.
     *
     * Ressource et construction sont posées comme la partie les pose : des
     * entités, pas des lignes de couche. C'est ce que l'import doit retrouver
     * — l'une remplaçable, l'autre intouchable.
     */
    private function seedSourcePlan(): void
    {
        $link = $this->link();

        $ids = [];
        foreach ([[0, 0], [1, 0], [0, 1]] as [$x, $y]) {
            $link->executeStatement('INSERT INTO coords (x, y, z, plan) VALUES (?, ?, 0, ?)', [$x, $y, self::SRC]);
            $ids[$x . ',' . $y] = (int) $link->lastInsertId();
        }

        $link->executeStatement('INSERT INTO map_tiles (coords_id, name, foreground) VALUES (?, ?, 0)', [$ids['0,0'], 'grass']);

        (new EntityPlacementService($link))->create(
            'resource',
            'arbre1',
            $ids['1,0'],
            'Arbre',
            'img/walls/arbre1.png'
        );

        /* Seed the builder: a fresh database holds no player to borrow, and the
         * case exists to prove a player-built row does NOT travel. */
        $builderId = self::BUILDER_ID;
        $link->executeStatement('DELETE FROM players WHERE id = ?', [$builderId]);
        $link->executeStatement(
            "INSERT INTO players (id, player_type, name, race) VALUES (?, 'real', ?, ?)",
            [$builderId, 'Bâtisseur de test plans', 'nain']
        );

        $palissadeId = (new EntityPlacementService($link))->create(
            'building',
            'palissade',
            $ids['0,1'],
            'Palissade',
            'img/walls/palissade.png'
        );
        $link->executeStatement(
            'INSERT INTO buildings (player_id, build_state) VALUES (?, ?)',
            [$palissadeId, 'built']
        );
        // Le propriétaire vit sur l'entité depuis qu'être possédé a cessé
        // d'être un privilège de bâtiment.
        $link->executeStatement(
            'UPDATE players SET owner_id = ? WHERE id = ?',
            [(int) $builderId, $palissadeId]
        );

        $link->executeStatement('INSERT INTO map_elements (coords_id, name, endTime) VALUES (?, ?, 12345)', [$ids['0,1'], 'feu_test']);

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

        foreach (['tiles', 'routes', 'plants', 'resources', 'elements', 'foregrounds', 'triggers', 'dialogs', 'items'] as $layer) {
            $link->executeStatement(
                "DELETE m FROM map_{$layer} m JOIN coords c ON c.id = m.coords_id WHERE c.plan LIKE 'plan_test_ie_%'"
            );
        }
        /* Ce qui est posé sur le plan est une entité : ses cases tiennent la FK
         * vers coords (RESTRICT), et les satellites n'ont pas de FK du tout —
         * ils ne partent donc avec rien. */
        foreach (['resources', 'buildings'] as $satellite) {
            $link->executeStatement(
                "DELETE s FROM {$satellite} s
                   JOIN players p ON p.id = s.player_id
                   JOIN coords c ON c.id = p.coords_id
                  WHERE c.plan LIKE 'plan_test_ie_%'"
            );
        }
        $link->executeStatement(
            "DELETE p FROM players p
               JOIN coords c ON c.id = p.coords_id
              WHERE p.player_type IN ('resource', 'building') AND c.plan LIKE 'plan_test_ie_%'"
        );

        /* The builder stands on no cell, so the join above never reaches it. */
        $link->executeStatement('DELETE FROM players WHERE id = ?', [self::BUILDER_ID]);

        $link->executeStatement("DELETE FROM coords WHERE plan LIKE 'plan_test_ie_%'");

        foreach ([self::SRC, self::IMPORTED] as $plan) {
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
