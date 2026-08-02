<?php

namespace Tests\Various;

use App\Entity\Faction;
use App\Service\FactionService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * DB-backed FactionService (factions / faction_roles), the replacement for
 * the datas/[public|private]/factions/*.json files.
 *
 * Pins the read-model contract every migrated call site relies on:
 *  - getFactionData() keeps the historical JSON shape: ->name, ->text,
 *    ->raFont, ->respawnPlan, ->role[] ordered with role[N]->name and
 *    per-role permission flags omitted when false;
 *  - the load-bearing isset() contract: ->hidden and ->secret are ABSENT
 *    when false and present (=1) when true — scripts/faction/body.php gates
 *    on isset($facJson->secret) / !empty($facJson->hidden);
 *  - unknown/empty codes return null (old decode: false — both falsy);
 *  - replaceRoles() reindexes positions 0..n-1 (players.factionRole indexes
 *    into the ordered list);
 *  - deleteFaction() refuses while characters still reference the code.
 *
 * Skips cleanly when the DB is unreachable (same convention as
 * RaceServiceTest). Uses the seeded aoo4 factions.
 */
class FactionServiceTest extends TestCase
{
    /** Id de fixture, hors de portée des ids réels. */
    private const MEMBER_ID = 990101;

    private FactionService $service;

    protected function setUp(): void
    {
        $this->bootstrapOrSkip();
        FactionService::clearCache();
        $this->service = new FactionService();
    }

    public function testGetFactionDataKeepsTheJsonShapeForASeededFaction(): void
    {
        $data = $this->service->getFactionData('forge_sacree');

        $this->assertNotNull($data);
        $this->assertSame('La Forge Sacrée', $data->name);
        $this->assertSame('ra-forging', $data->raFont);
        $this->assertSame('banque_des_lutins', $data->respawnPlan);
        $this->assertIsString($data->text);

        $this->assertIsArray($data->role);
        $this->assertCount(3, $data->role);
        $this->assertSame('Forgeron', $data->role[0]->name);
        $this->assertSame('Roi', $data->role[2]->name);
    }

    public function testRoleFlagsFollowTheLegacyOmittedWhenFalseShape(): void
    {
        $data = $this->service->getFactionData('forge_sacree');

        $this->assertNotNull($data);
        $forgeron = $data->role[0];
        $this->assertSame(1, $forgeron->defaultRole);
        $this->assertSame(1, $forgeron->showForum);
        $this->assertFalse(
            property_exists($forgeron, 'kickMember'),
            'false flags are omitted, like in the JSON files'
        );
    }

    public function testHiddenAndSecretAreOmittedWhenFalse(): void
    {
        // scripts/faction/body.php gates on isset($facJson->secret) and
        // !empty($facJson->hidden): a present-but-falsy key would flip
        // behavior, so absence IS the contract.
        $data = $this->service->getFactionData('forge_sacree');

        $this->assertNotNull($data);
        $this->assertFalse(property_exists($data, 'hidden'));
        $this->assertFalse(property_exists($data, 'secret'));
    }

    public function testHiddenAndSecretArePresentWhenTrue(): void
    {
        $code = 'test_faction_secrete';
        $this->deleteFaction($code);

        try {
            $faction = new Faction();
            $faction->setCode($code);
            $faction->setName('Faction de test');
            $faction->setHidden(true);
            $faction->setSecret(true);
            $this->service->save($faction);

            FactionService::clearCache();
            $data = (new FactionService())->getFactionData($code);

            $this->assertNotNull($data);
            $this->assertSame(1, $data->hidden);
            $this->assertSame(1, $data->secret);
        } finally {
            $this->deleteFaction($code);
            FactionService::clearCache();
        }
    }

    public function testUnknownOrEmptyCodeReturnsNullLikeAMissingJsonFile(): void
    {
        $this->assertNull($this->service->getFactionData('confrerie_inconnue'));
        $this->assertNull($this->service->getFactionByCode('confrerie_inconnue'));
        $this->assertNull($this->service->getFactionData(''));
        $this->assertNull($this->service->getFactionByCode(''));
    }

    public function testFactionCatalogListsTheSeededFactions(): void
    {
        $codes = $this->service->getAllFactionCodes();
        $names = $this->service->getFactionNames();

        foreach (['eryn_dolen', 'forge_sacree', 'saruta_et_freres'] as $expected) {
            $this->assertContains($expected, $codes);
            $this->assertArrayHasKey($expected, $names);
        }
        $this->assertSame('Eryn Dolen', $names['eryn_dolen']);
    }

    public function testReplaceRolesReindexesPositionsFromArrayOrder(): void
    {
        $code = 'test_faction_svc';
        $this->deleteFaction($code);

        try {
            $faction = new Faction();
            $faction->setCode($code);
            $faction->setName('Faction de test');
            $this->service->save($faction);

            $this->service->replaceRoles($faction, [
                ['name' => 'Chef', 'flags' => ['editRole' => true, 'kickMember' => true]],
                ['name' => ''],                                    // blank dropped
                ['name' => 'Recrue', 'flags' => ['defaultRole' => true]],
            ]);

            FactionService::clearCache();
            $reloaded = new FactionService();
            $data = $reloaded->getFactionData($code);

            $this->assertNotNull($data);
            $this->assertCount(2, $data->role, 'blank-name roles are dropped');
            $this->assertSame('Chef', $data->role[0]->name);
            $this->assertSame('Recrue', $data->role[1]->name);
            $this->assertSame(1, $data->role[0]->editRole);
            $this->assertFalse(property_exists($data->role[0], 'defaultRole'));

            $entity = $reloaded->getFactionByCode($code);
            $this->assertNotNull($entity);
            $this->assertSame(
                1,
                $reloaded->getDefaultRolePosition($entity),
                'default role position follows the defaultRole flag'
            );
        } finally {
            $this->deleteFaction($code);
            FactionService::clearCache();
        }
    }

    public function testDeleteFactionRefusesWhileCharactersReferenceIt(): void
    {
        global $link;

        $code = 'test_faction_del';
        $this->deleteFaction($code);

        $faction = new Faction();
        $faction->setCode($code);
        $faction->setName('Faction à supprimer');
        $this->service->save($faction);

        /* Seed the character rather than borrowing one: `players` holds
         * structures too, and countPlayersUsingFaction() counts members among
         * `real` and `npc` only — a forge carries a faction without joining
         * it. Five columns are enough to fabricate one. */
        $playerId = self::MEMBER_ID;
        $link->executeStatement(
            "INSERT INTO players (id, player_type, name, race, faction)
             VALUES (?, 'real', ?, ?, ?)",
            [$playerId, 'Membre de test factions', 'nain', $code]
        );

        try {
            $counts = $this->service->countPlayersUsingFaction($code);
            $this->assertSame(1, $counts['members']);

            try {
                $this->service->deleteFaction($faction);
                $this->fail('deleteFaction should refuse while a character references the code');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString($code, $e->getMessage());
            }
        } finally {
            $link->executeStatement('DELETE FROM players WHERE id = ?', [$playerId]);
            $this->deleteFaction($code);
            FactionService::clearCache();
        }
    }

    private function deleteFaction(string $code): void
    {
        global $link;
        // faction_roles rows follow via ON DELETE CASCADE.
        $link->executeStatement('DELETE FROM factions WHERE code = ?', [$code]);
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
