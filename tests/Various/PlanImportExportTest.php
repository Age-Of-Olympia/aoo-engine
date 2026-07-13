<?php

namespace Tests\Various;

use App\Service\ImportExport\ExporterRegistry;
use App\Service\ImportExport\ImporterRegistry;
use App\Service\ImportExport\PlanExporter;
use App\Service\ImportExport\PlanImporter;
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

        $this->assertCount(1, $payload['layers']['walls'], 'le mur player_id est exclu de l\'export');
        $wall = $payload['layers']['walls'][0];
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
            'ligne sans nom'    => ['plan' => self::IMPORTED, 'layers' => ['walls' => [['x' => 0, 'y' => 0, 'z' => 0]]]],
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

        $walls = $link->fetchAllAssociative(
            'SELECT m.name, m.player_id FROM map_walls m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
            [self::SRC]
        );
        $this->assertCount(1, $walls, 'le mur authoré est remplacé (supprimé), le mur joueur survit');
        $this->assertSame('palissade', $walls[0]['name']);
        $this->assertNotNull($walls[0]['player_id']);

        // Les coords existantes survivent (FK joueurs/logs) même hors payload
        $this->assertSame(
            3,
            (int) $link->fetchOne('SELECT COUNT(*) FROM coords WHERE plan = ?', [self::SRC])
        );

        $json = json_decode((string) file_get_contents($this->jsonPath(self::SRC)), true);
        $this->assertSame('Source remplacée', $json['name'], 'fichier JSON remplacé en entier');
    }

    /** Même fixture que PlanAdminServiceTest : 3 coords, tuile, murs (authoré + joueur), élément. */
    private function seedSourcePlan(): void
    {
        $link = $this->link();

        $ids = [];
        foreach ([[0, 0], [1, 0], [0, 1]] as [$x, $y]) {
            $link->executeStatement('INSERT INTO coords (x, y, z, plan) VALUES (?, ?, 0, ?)', [$x, $y, self::SRC]);
            $ids[$x . ',' . $y] = (int) $link->lastInsertId();
        }

        $link->executeStatement('INSERT INTO map_tiles (coords_id, name, foreground) VALUES (?, ?, 0)', [$ids['0,0'], 'grass']);
        $link->executeStatement('INSERT INTO map_walls (coords_id, name, damages) VALUES (?, ?, -1)', [$ids['1,0'], 'arbre1']);

        $builderId = $link->fetchOne('SELECT id FROM players WHERE id > 0 ORDER BY id LIMIT 1');
        if ($builderId === false) {
            $this->markTestSkipped('Aucun joueur en base pour porter le mur player_id.');
        }
        $link->executeStatement(
            'INSERT INTO map_walls (coords_id, name, damages, player_id) VALUES (?, ?, 0, ?)',
            [$ids['1,0'], 'palissade', (int) $builderId]
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

        foreach (['tiles', 'routes', 'plants', 'walls', 'elements', 'foregrounds', 'triggers', 'dialogs', 'items'] as $layer) {
            $link->executeStatement(
                "DELETE m FROM map_{$layer} m JOIN coords c ON c.id = m.coords_id WHERE c.plan LIKE 'plan_test_ie_%'"
            );
        }
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
