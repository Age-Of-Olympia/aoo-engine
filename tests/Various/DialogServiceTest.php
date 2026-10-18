<?php

namespace Tests\Various;

use App\Service\DialogService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\LegacyBootstrapTrait;

/**
 * Passerelle des dialogues de jeu (DialogService) : seule la table `dialogs`
 * compte, un fichier JSON sans ligne n'est jamais lu ; le read model garde la
 * forme des anciens fichiers ; le dialogue `register` est réécrit en base par
 * refreshRegisterDialog().
 *
 * DB-backed ; skip propre quand la base est inaccessible — même convention
 * que FactionImportExportTest. Fixtures préfixées dialog_test_.
 */
class DialogServiceTest extends TestCase
{
    use LegacyBootstrapTrait;

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
        $this->bootstrapLegacyOrSkip('dialogs');
        @mkdir(dirname($this->jsonPath('dialog_test_svc')), 0777, true);
        $this->cleanupFixtures();
        DialogService::clearCache();
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures();
        DialogService::clearCache();
    }

    public function testOnlyTheTableServesDialogs(): void
    {
        // A leftover JSON file is never read, with or without a row
        file_put_contents(
            $this->jsonPath('dialog_test_svc'),
            json_encode(['id' => 'dialog_test_svc', 'name' => 'Fichier', 'type' => 'pnj', 'dialog' => self::NODES])
        );

        $service = new DialogService();
        $this->assertNull($service->loadDialog('dialog_test_svc'), 'no row: no dialog');

        $service->saveGameDialog('dialog_test_svc', self::NODES, ['npc_name' => 'Base']);
        DialogService::clearCache();

        $fromDb = $service->loadDialog('dialog_test_svc');
        $this->assertSame('Base', $fromDb->name);

        // Read model in the shape of the old files: same keys, object nodes
        foreach (['id', 'name', 'type', 'dialog'] as $key) {
            $this->assertObjectHasProperty($key, $fromDb);
        }
        $this->assertSame('bonjour', $fromDb->dialog[0]->id);
        $this->assertSame('Continuer', $fromDb->dialog[0]->options[0]->text);

        $service->saveGameDialog('dialog_test_svc', self::NODES, ['npc_name' => 'Base', 'is_active' => false]);
        DialogService::clearCache();
        $this->assertNull($service->loadDialog('dialog_test_svc'), 'inactive row: no dialog');
    }

    public function testRefreshRegisterDialogRewritesTheDbRow(): void
    {
        /* The row is seeded on every real database, so skipping when it exists
         * meant never running. Snapshot it instead and put it back verbatim:
         * `refreshRegisterDialog()` works on that one fixed name and cannot be
         * pointed at a fixture of our own. */
        $existing = $this->link->fetchAssociative("SELECT * FROM dialogs WHERE name = 'register'");

        $service = new DialogService();
        $registerNodes = [[
            'id' => 'bonjour', 'text' => 'Quel corps ?', 'shuffle' => 1,
            'options' => [['go' => 'nain', 'text' => 'placeholder']],
        ]];
        $service->saveGameDialog(DialogService::REGISTER_DIALOG, $registerNodes, ['npc_name' => 'La Gardienne']);

        try {
            $service->refreshRegisterDialog();
            DialogService::clearCache();

            $row = $this->link->fetchAssociative("SELECT npc_name, dialog_data FROM dialogs WHERE name = 'register'");
            $this->assertSame('La Gardienne', $row['npc_name'], 'identité préservée');

            $nodes = json_decode($row['dialog_data'], true);
            $options = $nodes[0]['options'];
            $this->assertNotEmpty($options, 'options de races régénérées');
            foreach ($options as $option) {
                $this->assertArrayHasKey('go', $option);
                $this->assertMatchesRegularExpression('/âmes/u', $option['text']);
            }
        } finally {
            $this->link->executeStatement("DELETE FROM dialogs WHERE name = 'register'");

            if ($existing !== false) {
                $columns = array_keys($existing);
                $this->link->executeStatement(
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
        $service->saveGameDialog('dialog_test_ref', self::NODES);
        $this->link->executeStatement("INSERT INTO coords (x, y, z, plan) VALUES (0, 0, 0, 'dialog_test_plan')");
        $coordsId = (int) $this->link->lastInsertId();
        $this->link->executeStatement(
            'INSERT INTO map_dialogs (coords_id, name, params) VALUES (?, ?, ?)',
            [$coordsId, 'pnj', 'Un PNJ,gaia,dialog_test_ref']
        );

        try {
            $service->deleteGameDialog('dialog_test_ref');
            $this->fail('Suppression d\'un dialogue référencé acceptée');
        } catch (RuntimeException $e) {
            $this->assertSame(409, $e->getCode());
        }

        $this->link->executeStatement('DELETE FROM map_dialogs WHERE coords_id = ?', [$coordsId]);
        $service->deleteGameDialog('dialog_test_ref');
        $this->assertFalse($service->gameDialogExists('dialog_test_ref'));
    }

    private function cleanupFixtures(): void
    {
        if ($this->link === null) {
            return;
        }

        $this->link->executeStatement("DELETE FROM dialogs WHERE name LIKE 'dialog_test_%'");
        $this->link->executeStatement(
            "DELETE m FROM map_dialogs m JOIN coords c ON c.id = m.coords_id WHERE c.plan = 'dialog_test_plan'"
        );
        $this->link->executeStatement("DELETE FROM coords WHERE plan = 'dialog_test_plan'");

        if (file_exists($this->jsonPath('dialog_test_svc'))) {
            unlink($this->jsonPath('dialog_test_svc'));
        }
        json()->forget('dialogs', 'dialog_test_svc');
    }

    private function jsonPath(string $name): string
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/datas/public/dialogs/' . $name . '.json';
    }
}
