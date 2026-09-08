<?php

namespace Tests\Various;

use App\Factory\EntityManagerFactory;
use App\Entity\Race;
use App\Service\ImportExport\ExporterRegistry;
use App\Service\ImportExport\ImporterRegistry;
use App\Service\ImportExport\RaceExporter;
use App\Service\ImportExport\RaceImporter;
use App\Service\RaceService;
use PHPUnit\Framework\TestCase;
use Tests\Support\SeedsCharactersTrait;
use Tests\Support\LegacyBootstrapTrait;

/**
 * Import/export de races par bundles JSON (framework ImportExport) : le
 * payload porte l'identité par nom naturel, les 16 CARACS et les deux
 * listes ; un aller-retour export → import reproduit la race à l'identique.
 *
 * DB-backed (races seedées d'aoo4) ; skip propre quand la base est
 * inaccessible — même convention que RaceServiceTest.
 */
class RaceImportExportTest extends TestCase
{
    use LegacyBootstrapTrait;
    use SeedsCharactersTrait;

    protected function setUp(): void
    {
        $this->bootstrapLegacyOrSkip('races');
        RaceService::clearCache();
    }

    protected function tearDown(): void
    {
        // Nettoie la race créée par le test d'import (cascade sur les listes)
        if ($this->link !== null) {
            $this->removeSeededCharacters($this->link);
            $this->link->executeStatement(
                "DELETE FROM races WHERE name = 'race_test_import'"
            );
            $this->link->executeStatement(
                "DELETE FROM entity_type_footprints WHERE type_name = 'race_test_import'"
            );
        }
        RaceService::clearCache();
    }

    public function testRaceTypeIsRegisteredInBothRegistries(): void
    {
        $this->assertNotNull((new ExporterRegistry())->exporterFor('race'));
        $this->assertNotNull((new ImporterRegistry())->importerFor('race'));
    }

    public function testExportCarriesIdentityCaracsAndLists(): void
    {
        $race = EntityManagerFactory::getEntityManager()
            ->getRepository(Race::class)->findOneBy(['name' => 'nain']);
        $this->assertNotNull($race);

        $payload = (new RaceExporter())->toArray($race);

        $this->assertSame('nain', $payload['name']);
        $this->assertSame('Nain', $payload['label']);
        $this->assertSame('#FF0000', $payload['bgColor']);
        $this->assertSame($race->getCarac('mvt'), $payload['caracs']['mvt']);
        /* L'attaque de base est accordée sous ses deux noms de catalogue
         * depuis la scission d'« attaquer » (melee au contact, distance
         * au tir) — cf. Version20260725110000. */
        $this->assertContains('melee', $payload['starterActions']);
        $this->assertContains('distance', $payload['starterActions']);
        $this->assertArrayNotHasKey('id', $payload, 'jamais d\'id DB dans un bundle');
    }

    public function testImportRoundTripCreatesThenUpdatesARace(): void
    {
        $importer = new RaceImporter();
        $payload = [
            'name'           => 'race_test_import',
            'label'          => 'Race de test',
            'description'    => 'Créée par RaceImportExportTest.',
            'playable'       => false,
            'hidden'         => true,
            'bgColor'        => '#123456',
            'color'          => 'black',
            'faction'        => 'test',
            'plan'           => '',
            'animateurId'    => null,
            'caracs'         => array_fill_keys(Race::CARAC_KEYS, 3),
            'starterActions' => ['attaquer', 'repos'],
            'spells'         => ['soins/barbier'],
        ];

        $preview = $importer->preview([$payload]);
        $this->assertSame(['race_test_import'], $preview->created());
        $this->assertFalse($preview->hasRejections());

        $report = $importer->import([$payload]);
        $this->assertSame(['race_test_import'], $report->created());

        RaceService::clearCache();
        $race = (new RaceService())->getRaceByName('race_test_import');
        $this->assertNotNull($race);
        $this->assertSame('#123456', $race->getBgColor());
        $this->assertSame(3, $race->getCarac('mvt'));
        $this->assertSame(['attaquer', 'repos'], $race->getStarterActionNames());
        $this->assertSame(['soins/barbier'], $race->getSpellNames());

        // Ré-import du même bundle : mise à jour, pas de doublon
        $payload['label'] = 'Race de test v2';
        $again = $importer->import([$payload]);
        $this->assertSame(['race_test_import'], $again->updated());

        RaceService::clearCache();
        $this->assertSame('Race de test v2',
            (new RaceService())->getRaceByName('race_test_import')->getLabel());
    }

    public function testBuildWorkAndFootprintTravelWithTheType(): void
    {
        // Export side: the atelier carries its work and its declared cut-out.
        $atelier = EntityManagerFactory::getEntityManager()
            ->getRepository(Race::class)->findOneBy(['name' => 'atelier']);
        if ($atelier === null) {
            $this->markTestSkipped("type 'atelier' not seeded (run migrations).");
        }

        $payload = (new RaceExporter())->toArray($atelier);
        $this->assertSame(10, $payload['build_work'], 'the migration seed travels');
        $this->assertSame(2, $payload['footprint']['w'] ?? null);
        $this->assertSame(2, $payload['footprint']['h'] ?? null);

        // Import side: both land on a fresh type, keyed by its name.
        $payload['name'] = 'race_test_import';
        $payload['label'] = 'Type de test';
        $payload['build_work'] = 7;
        $payload['footprint'] = ['w' => 3, 'h' => 1, 'offsets' => [[0, 0], [1, 0], [2, 0]], 'roles' => []];
        $payload['starterActions'] = [];
        $payload['spells'] = [];

        (new RaceImporter())->import([$payload]);

        RaceService::clearCache();
        $this->assertSame(7, (new RaceService())->getRaceByName('race_test_import')->getBuildWork());
        $row = $this->link->fetchAssociative(
            "SELECT w, h FROM entity_type_footprints WHERE type_name = 'race_test_import'"
        );
        $this->assertSame(['w' => 3, 'h' => 1], array_map('intval', $row ?: []), 'the cut-out lands with its type');
    }

    public function testDeleteRemovesAnUnreferencedRaceAndItsLists(): void
    {
        $importer = new RaceImporter();
        $importer->import([[
            'name'           => 'race_test_import',
            'label'          => 'À supprimer',
            'bgColor'        => '#123456',
            'caracs'         => array_fill_keys(Race::CARAC_KEYS, 1),
            'starterActions' => ['attaquer'],
            'spells'         => [],
        ]]);

        RaceService::clearCache();
        $service = new RaceService();
        $race = $service->getRaceByName('race_test_import');
        $this->assertNotNull($race);
        $raceId = $race->getId();

        $service->deleteRace($race);

        RaceService::clearCache();
        $this->assertNull((new RaceService())->getRaceByName('race_test_import'));
        $this->assertSame(0, (int) $this->link->fetchOne(
            'SELECT COUNT(*) FROM race_starter_actions WHERE race_id = ?', [$raceId]
        ), 'les listes partent en cascade');
    }

    public function testDeleteRefusesARaceStillUsedByCharacters(): void
    {
        /* La race doit être RÉFÉRENCÉE : le cas s'en charge lui-même, au lieu
         * de compter sur le monde de développement pour héberger un nain. */
        $this->seedCharacter($this->link, 'nain');
        RaceService::clearCache();

        $service = new RaceService();
        $race = $service->getRaceByName('nain');
        $this->assertNotNull($race);
        $this->assertGreaterThan(0, $service->countPlayersUsingRace('nain'));

        // La ventilation joueurs/inactifs/PNJ couvre la même population
        $counts = $service->countCharactersByRaceName()['nain'] ?? null;
        $this->assertNotNull($counts);
        $this->assertGreaterThan(0, $counts['players']);
        $this->assertLessThanOrEqual($counts['players'], $counts['inactive']);
        $this->assertSame(
            $service->countPlayersUsingRace('nain'),
            $counts['players'] + $counts['npcs'],
            'joueurs + PNJ = total du garde-fou'
        );

        $this->expectException(\RuntimeException::class);
        $service->deleteRace($race);
    }

    public function testImportRejectsInvalidBgColorWithoutWriting(): void
    {
        $importer = new RaceImporter();
        $report = $importer->import([[
            'name'    => 'race_test_import',
            'bgColor' => 'white', // doit être hexadécimal (#RRGGBB)
        ]]);

        $this->assertTrue($report->hasRejections());
        RaceService::clearCache();
        $this->assertNull((new RaceService())->getRaceByName('race_test_import'),
            'tout-ou-rien : rien d\'écrit sur rejet');
    }
}
