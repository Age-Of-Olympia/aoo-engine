<?php

namespace Tests\Various;

use App\Service\DialogSeedService;
use App\Service\DialogService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Passerelle des dialogues de jeu (DialogService) : la table `dialogs` prime
 * sur les fichiers JSON legacy, qui restent le repli tant qu'une ligne
 * manque ; le read model DB est identique au décodage fichier ; le dialogue
 * `register` est réécrit en base par refreshRegisterDialog() ; le seed est
 * create-only (jamais d'écrasement d'une ligne éditée en admin).
 *
 * DB-backed ; skip propre quand la base est inaccessible — même convention
 * que FactionImportExportTest. Fixtures préfixées dialog_test_.
 */
class DialogServiceTest extends TestCase
{
    private const NODES = [
        ['id' => 'bonjour', 'text' => 'Salut PLAYER_NAME', 'options' => [
            ['go' => 'suite', 'text' => 'Continuer'],
        ]],
        ['id' => 'suite', 'text' => 'Voilà.', 'options' => [
            ['url' => 'merchant.php?targetId=TARGET_ID', 'text' => 'Boutique'],
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

    public function testDbRowTakesPrecedenceOverLegacyFile(): void
    {
        file_put_contents(
            $this->jsonPath('dialog_test_svc'),
            json_encode(['id' => 'dialog_test_svc', 'name' => 'Fichier', 'type' => 'pnj', 'dialog' => self::NODES])
        );

        $service = new DialogService();

        // Repli fichier tant que la ligne n'existe pas
        $fromFile = $service->loadDialog('dialog_test_svc');
        $this->assertNotNull($fromFile);
        $this->assertSame('Fichier', $fromFile->name);

        $service->saveGameDialog('dialog_test_svc', self::NODES, ['npc_name' => 'Base']);
        DialogService::clearCache();

        $fromDb = $service->loadDialog('dialog_test_svc');
        $this->assertSame('Base', $fromDb->name, 'la table prime sur le fichier');

        // Read model identique au décodage fichier : mêmes clés, nœuds objets
        foreach (['id', 'name', 'type', 'dialog'] as $key) {
            $this->assertObjectHasProperty($key, $fromDb);
        }
        $this->assertSame('bonjour', $fromDb->dialog[0]->id);
        $this->assertSame('Continuer', $fromDb->dialog[0]->options[0]->text);
    }

    public function testInactiveRowFallsBackToFile(): void
    {
        file_put_contents(
            $this->jsonPath('dialog_test_svc'),
            json_encode(['id' => 'dialog_test_svc', 'name' => 'Fichier', 'type' => 'pnj', 'dialog' => self::NODES])
        );
        (new DialogService())->saveGameDialog('dialog_test_svc', self::NODES, ['npc_name' => 'Base', 'is_active' => false]);
        DialogService::clearCache();

        $loaded = (new DialogService())->loadDialog('dialog_test_svc');
        $this->assertSame('Fichier', $loaded->name, 'ligne inactive : repli fichier');
    }

    public function testRefreshRegisterDialogRewritesTheDbRow(): void
    {
        $link = $this->link();

        /* The row is seeded on every real database, so skipping when it exists
         * meant never running. Snapshot it instead and put it back verbatim:
         * `refreshRegisterDialog()` works on that one fixed name and cannot be
         * pointed at a fixture of our own. */
        $existing = $link->fetchAssociative("SELECT * FROM dialogs WHERE name = 'register'");

        $service = new DialogService();
        $registerNodes = [[
            'id' => 'bonjour', 'text' => 'Quel corps ?', 'shuffle' => 1,
            'options' => [['go' => 'nain', 'text' => 'placeholder']],
        ]];
        $service->saveGameDialog(DialogService::REGISTER_DIALOG, $registerNodes, ['npc_name' => 'La Gardienne']);

        try {
            $service->refreshRegisterDialog();
            DialogService::clearCache();

            $row = $link->fetchAssociative("SELECT npc_name, dialog_data FROM dialogs WHERE name = 'register'");
            $this->assertSame('La Gardienne', $row['npc_name'], 'identité préservée');

            $nodes = json_decode($row['dialog_data'], true);
            $options = $nodes[0]['options'];
            $this->assertNotEmpty($options, 'options de races régénérées');
            foreach ($options as $option) {
                $this->assertArrayHasKey('go', $option);
                $this->assertMatchesRegularExpression('/âmes/u', $option['text']);
            }
        } finally {
            $link->executeStatement("DELETE FROM dialogs WHERE name = 'register'");

            if ($existing !== false) {
                $columns = array_keys($existing);
                $link->executeStatement(
                    'INSERT INTO dialogs (' . implode(', ', $columns) . ') VALUES ('
                        . implode(', ', array_fill(0, count($columns), '?')) . ')',
                    array_values($existing)
                );
            }

            DialogService::clearCache();
        }
    }

    public function testValidationRejectsBrokenNodes(): void
    {
        $cases = [
            'liste vide'        => [],
            'nœud sans id'      => [['text' => 'x', 'options' => []]],
            'option sans cible' => [['id' => 'a', 'text' => 'x', 'options' => [['text' => 'y']]]],
            'options non liste' => [['id' => 'a', 'text' => 'x', 'options' => ['go' => 'b']]],
        ];

        foreach ($cases as $label => $nodes) {
            try {
                DialogService::assertValidDialogData($nodes);
                $this->fail('Accepté : ' . $label);
            } catch (RuntimeException $e) {
                $this->assertSame(400, $e->getCode(), $label);
            }
        }

        // Les extras legacy/tutoriel passent (shuffle, avatar, set)
        $valid = DialogService::assertValidDialogData([[
            'id' => 'bonjour', 'text' => 'x', 'shuffle' => 1, 'avatar' => 'gaia',
            'options' => [['set' => ['flag' => 1], 'text' => 'y']],
        ]]);
        $this->assertSame(1, $valid[0]['shuffle']);
    }

    public function testDeleteGuardsRegisterAndReferencedDialogs(): void
    {
        $service = new DialogService();

        try {
            $service->deleteGameDialog(DialogService::REGISTER_DIALOG);
            $this->fail('Suppression de register acceptée');
        } catch (RuntimeException $e) {
            $this->assertSame(409, $e->getCode());
        }

        // Dialogue référencé par un déclencheur map_dialogs
        $link = $this->link();
        $service->saveGameDialog('dialog_test_ref', self::NODES);
        $link->executeStatement("INSERT INTO coords (x, y, z, plan) VALUES (0, 0, 0, 'dialog_test_plan')");
        $coordsId = (int) $link->lastInsertId();
        $link->executeStatement(
            'INSERT INTO map_dialogs (coords_id, name, params) VALUES (?, ?, ?)',
            [$coordsId, 'pnj', 'Un PNJ,gaia,dialog_test_ref']
        );

        try {
            $service->deleteGameDialog('dialog_test_ref');
            $this->fail('Suppression d\'un dialogue référencé acceptée');
        } catch (RuntimeException $e) {
            $this->assertSame(409, $e->getCode());
        }

        $link->executeStatement('DELETE FROM map_dialogs WHERE coords_id = ?', [$coordsId]);
        $service->deleteGameDialog('dialog_test_ref');
        $this->assertFalse($service->gameDialogExists('dialog_test_ref'));
    }

    public function testSeedCreatesMissingRowsAndPreservesExistingOnes(): void
    {
        // Répertoire datas isolé : le seed ne voit que la fixture
        $root = sys_get_temp_dir() . '/dialog_test_seed_root';
        @mkdir($root . '/datas/public/dialogs', 0777, true);
        file_put_contents(
            $root . '/datas/public/dialogs/dialog_test_seed.json',
            json_encode(['id' => 'dialog_test_seed', 'name' => 'Seedé', 'type' => 'pnj', 'dialog' => self::NODES])
        );
        file_put_contents($root . '/datas/public/dialogs/dialog_test_broken.json', '{pas du json');

        $seeder = new DialogSeedService(null, null, $root);

        $report = $seeder->seed();
        $this->assertContains('dialog_test_seed', $report['created']);
        $this->assertNotEmpty($report['unreadable'], 'le JSON cassé est signalé, pas seedé');

        // Édition admin puis re-seed : la ligne est préservée
        (new DialogService())->saveGameDialog('dialog_test_seed', self::NODES, ['npc_name' => 'Édité en admin']);
        $again = $seeder->seed();
        $this->assertContains('dialog_test_seed', $again['kept']);
        $this->assertSame(
            'Édité en admin',
            $this->link()->fetchOne("SELECT npc_name FROM dialogs WHERE name = 'dialog_test_seed'")
        );

        unlink($root . '/datas/public/dialogs/dialog_test_seed.json');
        unlink($root . '/datas/public/dialogs/dialog_test_broken.json');
    }

    private function cleanupFixtures(): void
    {
        global $link;
        if (!isset($link) || !$link instanceof Connection) {
            return;
        }

        $link->executeStatement("DELETE FROM dialogs WHERE name LIKE 'dialog_test_%'");
        $link->executeStatement(
            "DELETE m FROM map_dialogs m JOIN coords c ON c.id = m.coords_id WHERE c.plan = 'dialog_test_plan'"
        );
        $link->executeStatement("DELETE FROM coords WHERE plan = 'dialog_test_plan'");

        if (file_exists($this->jsonPath('dialog_test_svc'))) {
            unlink($this->jsonPath('dialog_test_svc'));
        }
        json()->forget('dialogs', 'dialog_test_svc');
    }

    private function jsonPath(string $name): string
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/datas/public/dialogs/' . $name . '.json';
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

        if (!is_dir($_SERVER['DOCUMENT_ROOT'] . '/datas/public/dialogs')) {
            $this->markTestSkipped('datas/public/dialogs absent (datas non provisionné).');
        }
    }
}
