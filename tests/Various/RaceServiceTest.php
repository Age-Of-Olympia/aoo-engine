<?php

namespace Tests\Various;

use App\Entity\CharacterRace;
use App\Entity\Race;
use App\Service\RaceService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * DB-backed RaceService (races / race_starter_actions / race_spells), the
 * replacement for the datas/[public|private]/races/*.json files and the
 * RACES / RACES_EXT constants.
 *
 * Pins the read-model contract every migrated call site relies on:
 *  - getRaceData() keeps the historical JSON shape (->name is the display
 *    label, ->actions / ->spells are ordered name lists, ->actionsPack is
 *    their union) and returns null for unknown races (old decode: false —
 *    both falsy).
 *  - color / mvt helpers keep their legacy fallbacks.
 *  - playable/all name lists replace the constants.
 *
 * Skips cleanly when the DB is unreachable (same convention as
 * PlayerCaracsServiceCharacterizationTest). Uses the seeded aoo4 races.
 */
class RaceServiceTest extends TestCase
{
    private RaceService $service;

    protected function setUp(): void
    {
        $this->bootstrapOrSkip();
        RaceService::clearCache();
        $this->service = new RaceService();
    }

    public function testGetRaceDataKeepsTheJsonShapeForASeededRace(): void
    {
        $data = $this->service->getRaceData('nain');

        $this->assertNotNull($data);
        $this->assertSame('Nain', $data->name, '->name is the display label');
        $this->assertSame('#FF0000', $data->bgColor);
        $this->assertSame('forge_sacree', $data->faction);

        foreach (array_keys(CARACS) as $key) {
            $this->assertIsInt($data->{$key}, "carac '{$key}' present and int");
        }
        $this->assertSame(4, $data->mvt);
        $this->assertSame(50, $data->pv);

        $this->assertIsArray($data->actions);
        /* Attaque de base : deux actions du catalogue depuis la scission
         * d'« attaquer » (cf. Version20260725110000). */
        $this->assertContains('melee', $data->actions);
        $this->assertContains('distance', $data->actions);
        $this->assertIsArray($data->spells);
    }

    public function testActionsPackIsTheUnionOfStarterActionsAndSpells(): void
    {
        $data = $this->service->getRaceData('nain');

        $this->assertNotNull($data);
        $expected = array_values(array_unique(array_merge($data->actions, $data->spells)));
        $this->assertSame($expected, $data->actionsPack);
    }

    public function testUnknownRaceReturnsNullAndLegacyFallbacks(): void
    {
        $this->assertNull($this->service->getRaceData('atlante_inconnu'));
        $this->assertNull($this->service->getRaceByName('atlante_inconnu'));
        $this->assertSame(4, $this->service->getRaceMaxMvt('atlante_inconnu'));
        $this->assertSame('#FFFFFF', $this->service->getRaceBackgroundColor('atlante_inconnu'));
        $this->assertSame('#000000', RaceService::getRaceColor('atlante_inconnu'));
        $this->assertSame('#000000', RaceService::getRaceColor(null), 'empty race = "commun" = black');
        $this->assertSame('#000000', RaceService::getRaceColor(''));
    }

    public function testLookupIsCaseInsensitiveLikeTheJsonPathLookupWas(): void
    {
        $this->assertNotNull($this->service->getRaceByName('NAIN'));
        $this->assertSame('#FF0000', RaceService::getRaceColor('Nain'));
    }

    public function testRaceNameListsReplaceTheConstants(): void
    {
        $playable = $this->service->getPlayableRaceNames();
        $all = $this->service->getAllRaceNames();

        $this->assertContains('nain', $playable);
        $this->assertContains('elfe', $playable);
        $this->assertNotContains('ame', $playable, 'hidden system race is not playable');

        $this->assertContains('ame', $all);
        $this->assertContains('lutin', $all);
        $this->assertSame(array_intersect($all, $playable), array_intersect($playable, $all));
    }

    /**
     * `playable` says a type may be driven; `hidden` says it is not put in
     * front of a player. A playable building type carries both — it is driven
     * through faction access, never registered as — so registration has to ask
     * the second question too. Today every playable race happens to be visible,
     * which is exactly why this would go unnoticed.
     */
    public function testAPlayableButHiddenTypeIsNeverOfferedAtRegistration(): void
    {
        $name = 'test_race_pilotee';
        $this->deleteRace($name);

        try {
            $race = new CharacterRace();
            $race->setName($name);
            $race->setCode(strtoupper($name));
            $race->setLabel('Type piloté de test');
            $race->setPlayable(true);
            $race->setHidden(true);
            $this->service->save($race);

            RaceService::clearCache();

            $this->assertNotContains($name, (new RaceService())->getPlayableRaceNames());
        } finally {
            $this->deleteRace($name);
            RaceService::clearCache();
        }
    }

    public function testReplaceNameListsRoundTripOnAThrowawayRace(): void
    {
        $name = 'test_race_svc';
        $this->deleteRace($name);

        try {
            $race = new CharacterRace();
            $race->setName($name);
            $race->setCode(strtoupper($name));
            $race->setLabel('Race de test');
            $race->setPlayable(false);
            $race->setHidden(true);
            $this->service->save($race);

            $this->service->replaceNameLists(
                $race,
                ['attaquer', 'repos', '  attaquer  ', ''],   // dupes/blank dropped, order kept
                ['dmg1/pic_de_pierre']
            );

            RaceService::clearCache();
            $reloaded = (new RaceService())->getRaceData($name);

            $this->assertNotNull($reloaded);
            $this->assertSame(['attaquer', 'repos'], $reloaded->actions);
            $this->assertSame(['dmg1/pic_de_pierre'], $reloaded->spells);
            $this->assertSame(['attaquer', 'repos', 'dmg1/pic_de_pierre'], $reloaded->actionsPack);
        } finally {
            $this->deleteRace($name);
            RaceService::clearCache();
        }
    }

    private function deleteRace(string $name): void
    {
        global $link;
        // race_starter_actions / race_spells rows follow via ON DELETE CASCADE.
        $link->executeStatement('DELETE FROM races WHERE name = ?', [$name]);
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
            $link->executeQuery('SELECT 1 FROM races LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('races table unreachable: ' . $e->getMessage());
        }
    }
}
