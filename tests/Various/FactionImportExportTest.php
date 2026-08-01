<?php

namespace Tests\Various;

use App\Factory\EntityManagerFactory;
use App\Entity\Faction;
use App\Service\FactionService;
use App\Service\ImportExport\ExporterRegistry;
use App\Service\ImportExport\FactionExporter;
use App\Service\ImportExport\FactionImporter;
use App\Service\ImportExport\ImporterRegistry;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Import/export de factions par bundles JSON (framework ImportExport) : le
 * payload porte l'identité par code naturel, les drapeaux et la liste
 * ORDONNÉE des rôles (drapeaux booléens explicites) ; un aller-retour
 * export → import reproduit la faction à l'identique.
 *
 * DB-backed (factions seedées d'aoo4) ; skip propre quand la base est
 * inaccessible — même convention que RaceImportExportTest.
 */
class FactionImportExportTest extends TestCase
{
    protected function setUp(): void
    {
        $this->bootstrapOrSkip();
        FactionService::clearCache();
    }

    protected function tearDown(): void
    {
        // Nettoie la faction créée par le test d'import (cascade sur les rôles)
        global $link;
        if (isset($link) && $link instanceof Connection) {
            $link->executeStatement(
                "DELETE FROM factions WHERE code = 'faction_test_import'"
            );
        }
        FactionService::clearCache();
    }

    public function testFactionTypeIsRegisteredInBothRegistries(): void
    {
        $this->assertNotNull((new ExporterRegistry())->exporterFor('faction'));
        $this->assertNotNull((new ImporterRegistry())->importerFor('faction'));
    }

    public function testExportCarriesIdentityFlagsAndOrderedRoles(): void
    {
        $faction = EntityManagerFactory::getEntityManager()
            ->getRepository(Faction::class)->findOneBy(['code' => 'forge_sacree']);
        $this->assertNotNull($faction);

        $payload = (new FactionExporter())->toArray($faction);

        $this->assertSame('forge_sacree', $payload['code']);
        $this->assertSame('La Forge Sacrée', $payload['name']);
        $this->assertSame('ra-forging', $payload['raFont']);
        $this->assertSame('banque_des_lutins', $payload['respawnPlan']);
        $this->assertFalse($payload['secret']);
        $this->assertSame('Forgeron', $payload['roles'][0]['name']);
        $this->assertTrue($payload['roles'][0]['defaultRole']);
        $this->assertFalse($payload['roles'][0]['kickMember'],
            'drapeaux explicites dans le bundle, contrairement au read model runtime');
        $this->assertArrayNotHasKey('id', $payload, 'jamais d\'id DB dans un bundle');
    }

    public function testImportRoundTripCreatesThenUpdatesAFaction(): void
    {
        $importer = new FactionImporter();
        $payload = [
            'code'        => 'faction_test_import',
            'name'        => 'Faction de test',
            'text'        => 'Créée par FactionImportExportTest.',
            'raFont'      => 'ra-anvil',
            'respawnPlan' => 'olympia',
            'hidden'      => false,
            'secret'      => true,
            'roles'       => [
                ['name' => 'Chef', 'editRole' => true, 'kickMember' => true],
                ['name' => 'Recrue', 'defaultRole' => true],
            ],
        ];

        $preview = $importer->preview([$payload]);
        $this->assertSame(['faction_test_import'], $preview->created());
        $this->assertFalse($preview->hasRejections());

        $report = $importer->import([$payload]);
        $this->assertSame(['faction_test_import'], $report->created());

        FactionService::clearCache();
        $service = new FactionService();
        $faction = $service->getFactionByCode('faction_test_import');
        $this->assertNotNull($faction);
        $this->assertTrue($faction->isSecret());
        $this->assertSame(['Chef', 'Recrue'], $faction->getRoleNames());
        $this->assertSame(1, $service->getDefaultRolePosition($faction));

        // Aller-retour : l'export de l'entité importée == le payload normalisé
        $exported = (new FactionExporter())->toArray($faction);
        $this->assertSame('faction_test_import', $exported['code']);
        $this->assertTrue($exported['roles'][0]['editRole']);
        $this->assertFalse($exported['roles'][0]['defaultRole']);
        $this->assertTrue($exported['roles'][1]['defaultRole']);

        // Ré-import du même bundle : mise à jour, pas de doublon
        $payload['name'] = 'Faction de test v2';
        $again = $importer->import([$payload]);
        $this->assertSame(['faction_test_import'], $again->updated());

        FactionService::clearCache();
        $this->assertSame('Faction de test v2',
            (new FactionService())->getFactionByCode('faction_test_import')->getName());
    }

    public function testImportRejectsARoleWithoutNameWithoutWriting(): void
    {
        $importer = new FactionImporter();
        $report = $importer->import([[
            'code'  => 'faction_test_import',
            'name'  => 'Rôle cassé',
            'roles' => [['editRole' => true]], // pas de nom
        ]]);

        $this->assertTrue($report->hasRejections());
        FactionService::clearCache();
        $this->assertNull((new FactionService())->getFactionByCode('faction_test_import'),
            'tout-ou-rien : rien d\'écrit sur rejet');
    }

    public function testImportRejectsAnInvalidCode(): void
    {
        $report = (new FactionImporter())->preview([[
            'code' => 'Pas Un Code',
            'name' => 'Invalide',
        ]]);

        $this->assertTrue($report->hasRejections());
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

        global $link;
        if (!isset($link) || !$link instanceof Connection) {
            $this->markTestSkipped('Global $link not populated by bootstrap.');
        }

        try {
            $link->executeQuery('SELECT 1 FROM factions LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('factions table unreachable: ' . $e->getMessage());
        }
    }
}
