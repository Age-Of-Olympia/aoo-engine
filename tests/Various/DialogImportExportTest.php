<?php

namespace Tests\Various;

use App\Service\DialogService;
use App\Service\ImportExport\DialogExporter;
use App\Service\ImportExport\DialogImporter;
use App\Service\ImportExport\ExporterRegistry;
use App\Service\ImportExport\ImporterRegistry;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Import/export de dialogues par bundles JSON : identité par code naturel
 * (`name`, aucun id de base) ; create-or-update tout-ou-rien ; un
 * aller-retour export → suppression → import → ré-export est idempotent.
 *
 * DB-backed ; skip propre quand la base est inaccessible — même convention
 * que FactionImportExportTest. Fixtures préfixées dialog_test_.
 */
class DialogImportExportTest extends TestCase
{
    private const NAME = 'dialog_test_ie';

    private const NODES = [
        ['id' => 'bonjour', 'text' => 'Bienvenue PLAYER_NAME', 'shuffle' => 1, 'options' => [
            ['go' => 'infos', 'text' => 'Dis-m\'en plus.'],
            ['url' => 'merchant.php?targetId=TARGET_ID', 'text' => 'Boutique'],
        ]],
        ['id' => 'infos', 'text' => 'Voilà tout.', 'options' => [
            ['go' => 'EXIT', 'text' => '[partir]'],
        ]],
    ];

    protected function setUp(): void
    {
        $this->bootstrapOrSkip();
        $this->cleanupFixtures();
        DialogService::clearCache();
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures();
        DialogService::clearCache();
    }

    public function testDialogTypeIsRegisteredInBothRegistries(): void
    {
        $this->assertNotNull((new ExporterRegistry())->exporterFor('dialog'));
        $this->assertNotNull((new ImporterRegistry())->importerFor('dialog'));
    }

    public function testExportCarriesNaturalKeysOnly(): void
    {
        (new DialogService())->saveGameDialog(self::NAME, self::NODES, [
            'npc_name' => 'Testeur', 'type' => 'pnj', 'custom' => 'x',
        ]);

        $payload = (new DialogExporter())->exportOne(self::NAME);

        $this->assertSame(self::NAME, $payload['name']);
        $this->assertSame('Testeur', $payload['npcName']);
        $this->assertTrue($payload['active']);
        $this->assertArrayNotHasKey('id', $payload, 'jamais d\'id DB dans un bundle');
        $this->assertSame('bonjour', $payload['nodes'][0]['id']);
        $this->assertSame(1, $payload['nodes'][0]['shuffle'], 'les extras (shuffle…) voyagent');
    }

    public function testImportRoundTripCreatesThenUpdates(): void
    {
        $service = new DialogService();
        $exporter = new DialogExporter();
        $importer = new DialogImporter();

        $service->saveGameDialog(self::NAME, self::NODES, ['npc_name' => 'Testeur']);
        $payload = $exporter->exportOne(self::NAME);

        // Suppression puis ré-import : création à l'identique
        $this->link()->executeStatement('DELETE FROM dialogs WHERE name = ?', [self::NAME]);
        DialogService::clearCache();

        $preview = $importer->preview([$payload]);
        $this->assertSame([self::NAME], $preview->created());
        $this->assertFalse($service->gameDialogExists(self::NAME), 'preview : rien d\'écrit');

        $report = $importer->import([$payload]);
        $this->assertSame([self::NAME], $report->created());
        $this->assertEquals($payload, $exporter->exportOne(self::NAME), 'aller-retour idempotent');

        // Ré-import modifié : mise à jour, pas de doublon
        $payload['npcName'] = 'Testeur v2';
        $again = $importer->import([$payload]);
        $this->assertSame([self::NAME], $again->updated());
        $this->assertSame('Testeur v2', $exporter->exportOne(self::NAME)['npcName']);
    }

    public function testImportRejectsInvalidPayloadsWithoutWriting(): void
    {
        $importer = new DialogImporter();

        $cases = [
            'nom invalide'  => ['name' => 'Pas Un Code', 'nodes' => self::NODES],
            'nœuds cassés'  => ['name' => self::NAME, 'nodes' => [['text' => 'sans id']]],
            'pas un objet'  => 'n\'importe quoi',
        ];

        foreach ($cases as $label => $payload) {
            $report = $importer->import([$payload]);
            $this->assertTrue($report->hasRejections(), $label);
        }

        // Doublon dans le lot : tout-ou-rien, rien d'écrit
        $valid = ['name' => self::NAME, 'nodes' => self::NODES];
        $report = $importer->import([$valid, $valid]);
        $this->assertTrue($report->hasRejections());
        $this->assertFalse((new DialogService())->gameDialogExists(self::NAME), 'tout-ou-rien : rien d\'écrit');
    }

    private function cleanupFixtures(): void
    {
        global $link;
        if (isset($link) && $link instanceof Connection) {
            $link->executeStatement("DELETE FROM dialogs WHERE name LIKE 'dialog_test_%'");
        }
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
            $link->executeQuery('SELECT 1 FROM dialogs LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('dialogs table unreachable (migration non appliquée ?): ' . $e->getMessage());
        }
    }
}
