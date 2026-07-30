<?php

namespace Tests\Action\Combat;

use App\Service\BuildingService;
use Classes\View;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;
use Tests\Support\PlantsResources;

/**
 * « Qu'est-ce qui bloque une case ? » — la table de vérité, gelée.
 *
 * Test étalon au sens du glossaire : une photographie du comportement
 * existant, figée AVANT de refactorer. Le lot suivant fait converger ces
 * prédicats vers un service unique ; ce test est là pour qu'aucune
 * divergence ne disparaisse ni n'apparaisse en silence à ce moment-là.
 *
 * # Ce qui est épinglé
 *
 * Quatre prédicats serveur, qui ne consultent PAS les mêmes tables :
 *
 *   | prédicat                        | players | map_triggers | map_elements |
 *   |---------------------------------|---------|--------------|--------------|
 *   | View::get_coords_taken (plan)   |   oui   |     oui      |     non      |
 *   | View::is_free (une case)        |   oui   |     oui      |     non      |
 *   | BuildingService::place          |   oui   |     non      |  oui (sauf   |
 *   |                                 |         |              |  buildable)  |
 *   | BuildingService::lineOfFireReport| selon  |     non      |     non      |
 *   |                                 | la race |              |              |
 *
 * Un déclencheur bloque donc la construction pour `is_free` mais pas pour
 * `place()` ; un mur arrête la flèche, une table non — et rien de tout cela
 * n'est écrit ailleurs que dans le code.
 *
 * # Ce qui n'est PAS épinglé, et pourquoi
 *
 * `go.php` — le prédicat du PAS, le plus important — n'est pas testable : c'est
 * un script de premier niveau qui `exit()`. C'est en soi l'argument de son
 * extraction dans le service unique du lot suivant. Idem pour les prédicats
 * clients (`js/view.js`, `js/blocked-tiles.js`), qui déduisent du DOM.
 *
 * # Une capacité sans usage, relevée en écrivant ce test
 *
 * `RaceService::getPassableStructureNames()` rend aujourd'hui une liste VIDE :
 * aucun type de structure n'a `blocks_passage = 0`. La branche « structures
 * passables » de `go.php` ne s'exécute donc jamais pour l'instant.
 *
 * Ce n'est PAS du code mort à retirer : c'est le mécanisme du **mur factice**
 * — une structure qui a l'air d'arrêter et qu'on traverse. Il attend son cas
 * d'usage, il ne le remplace pas.
 *
 * En revanche le commentaire de `js/view.js` qui cite la table de bois en
 * exemple de structure passable est faux : `table_bois` bloque le pas, elle ne
 * laisse passer que les projectiles.
 */
#[Group('items-golden-master')]
class BlockingPredicatesGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    use PlantsResources;

    private const PLAN = 'plan_test_blocage';

    protected function tearDown(): void
    {
        $link = $this->link;

        $this->uprootResources($link, self::PLAN);

        $link->executeStatement(
            'DELETE l FROM map_triggers l JOIN coords c ON c.id = l.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );

        // Le parent supprime les entités de fixture, qui occupent ces cases.
        parent::tearDown();

        $link->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
    }

    private function tile(int $x, int $y): object
    {
        return (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => self::PLAN];
    }

    private function coordsId(int $x, int $y): int
    {
        return (int) View::get_coords_id($this->tile($x, $y));
    }

    private function putResource(string $name, int $x, int $y): void
    {
        $this->plantResource($this->link, $name, $this->coordsId($x, $y), self::PLAN, $x, $y);
    }

    private function putTrigger(string $name, int $x, int $y): void
    {
        $this->link->executeStatement(
            'INSERT INTO map_triggers (name, coords_id, params) VALUES (?, ?, \'\')',
            [$name, $this->coordsId($x, $y)]
        );
    }

    /** Vrai si place() refuse la case. */
    private function buildRefused(int $x, int $y): bool
    {
        try {
            $id = (new BuildingService())->place('mur_pierre', $this->tile($x, $y));
            $this->trackEntityId($id);

            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    }

    public function testEmptyTileIsFreeForEveryone(): void
    {
        $this->requireBuildingsOrSkip();
        $this->coordsId(0, 0);

        $this->assertTrue(View::is_free($this->tile(0, 0)), 'case vide : libre');
        $this->assertFalse($this->buildRefused(0, 0), 'case vide : constructible');
    }

    /** Une ressource bloque TOUT : occupation, construction, projectile. */
    public function testResourceBlocksEveryPredicate(): void
    {
        $this->requireBuildingsOrSkip();
        $this->putResource('arbre1', 1, 0);

        $this->assertFalse(View::is_free($this->tile(1, 0)), 'is_free voit la ressource');
        $this->assertContains('1,0', View::get_coords_taken($this->tile(0, 0)), 'get_coords_taken aussi');
        $this->assertTrue($this->buildRefused(1, 0), 'place() refuse');
    }

    /**
     * Un DÉCLENCHEUR bloque l'occupation mais PAS la construction : c'est la
     * divergence la plus nette du lot, et elle est involontaire — personne ne
     * l'a écrite, elle tombe d'une absence de filtre.
     */
    public function testTriggerBlocksOccupancyButNotBuilding(): void
    {
        $this->requireBuildingsOrSkip();
        $this->putTrigger('forbidden', 2, 0);

        $this->assertFalse(View::is_free($this->tile(2, 0)), 'is_free compte les déclencheurs');
        $this->assertContains('2,0', View::get_coords_taken($this->tile(0, 0)), 'get_coords_taken aussi');
        $this->assertFalse($this->buildRefused(2, 0), 'place() les ignore — divergence gelée');
    }

    /** Une entité bloque l'occupation et la construction. */
    public function testEntityBlocksOccupancyAndBuilding(): void
    {
        $this->requireBuildingsOrSkip();
        $this->placeStructure('mur_pierre', 3, 0, self::PLAN);

        $this->assertFalse(View::is_free($this->tile(3, 0)));
        $this->assertContains('3,0', View::get_coords_taken($this->tile(0, 0)));
        $this->assertTrue($this->buildRefused(3, 0));
    }

    /**
     * Le projectile, lui, consulte le CATALOGUE : `races.blocks_projectiles`.
     * Un mur arrête la flèche, une table de bois la laisse passer — alors que
     * les deux bloquent le pas.
     */
    public function testProjectileBlockingFollowsTheCatalogue(): void
    {
        $this->requireBuildingsOrSkip();

        $service = new BuildingService();

        $this->placeStructure('mur_pierre', 5, 0, self::PLAN);
        $mur = $service->lineOfFireReport($this->tile(4, 0), $this->tile(6, 0));
        $this->assertNotNull($mur['blocker'], 'un mur arrête la flèche');

        $this->placeStructure('table_bois', 5, 2, self::PLAN);
        $table = $service->lineOfFireReport($this->tile(4, 2), $this->tile(6, 2));
        $this->assertNull($table['blocker'], 'une table la laisse passer');
    }

    /**
     * La multi-occupation est une CAPACITÉ (arbitrage du 2026-07-27, besoin
     * d'animation), pas une dérive : deux occupants sur une case doivent tenir.
     * `place()` la refuse au joueur, mais la base l'accepte — c'est ce que le
     * lot d'emprise devra distinguer.
     */
    public function testTwoOccupantsOnOneTileAreTolerated(): void
    {
        $this->requireBuildingsOrSkip();

        $this->placeStructure('mur_pierre', 7, 0, self::PLAN);
        $this->putResource('arbre1', 7, 0);

        $this->assertSame(
            2,
            (int) $this->link->fetchOne(
                'SELECT (SELECT COUNT(*) FROM players WHERE coords_id = c.id)
                 FROM coords c WHERE c.id = ?',
                [$this->coordsId(7, 0)]
            ),
            'la base accepte deux occupants sur une case'
        );
        $this->assertFalse(View::is_free($this->tile(7, 0)));
    }
}
