<?php

namespace Tests\Various;

use App\Service\GroundLootService;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Les plantes se cueillent au geste, plus au passage.
 *
 * Marcher sur une fleur la récoltait : go.php incluait un script qui donnait
 * l'objet et supprimait la ligne. Le pas et la cueillette sont deux gestes ;
 * ces cas épinglent que la plante se voit dans la bourse au sol, qu'elle part
 * quand on ramasse, et qu'elle reste tant qu'on ne ramasse pas.
 */
#[Group('items-golden-master')]
class GroundLootPlantsTest extends LegacyPlayerFixtureTestCase
{
    private const PLAN = 'plan_test_cueillette';

    protected function tearDown(): void
    {
        $link = $this->link;

        $link->executeStatement(
            'DELETE p FROM map_plants p JOIN coords c ON c.id = p.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );

        parent::tearDown();

        $link->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
    }

    private function coordsId(int $x, int $y): int
    {
        return (int) View::get_coords_id(
            (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => self::PLAN]
        );
    }

    /** Une plante porte le nom de l'objet qu'elle rend : il doit exister. */
    private function somePlantName(): string
    {
        $name = $this->link->fetchOne(
            'SELECT i.name FROM items i ORDER BY i.id LIMIT 1'
        );

        if ($name === false || $name === null) {
            $this->markTestSkipped('Aucun objet au catalogue.');
        }

        return (string) $name;
    }

    private function plantAt(int $coordsId, string $name): void
    {
        $this->link->executeStatement(
            'INSERT INTO map_plants (coords_id, name) VALUES (?, ?)',
            [$coordsId, $name]
        );
    }

    /** La bourse au sol montre les plantes : sans les voir, on n'y pense pas. */
    public function testPlantsShowInTheGroundList(): void
    {
        $name = $this->somePlantName();
        $this->plantAt($this->coordsId(1, 1), $name);

        $loot = (new GroundLootService())->listAt(1, 1, 0, self::PLAN);

        $this->assertCount(1, $loot['plants']);
        $this->assertSame($name, $loot['plants'][0]->name);
    }

    /** Ramasser cueille la plante : elle quitte la case. */
    public function testCollectingTakesThePlantOffTheTile(): void
    {
        $name = $this->somePlantName();
        $coordsId = $this->coordsId(2, 1);
        $this->plantAt($coordsId, $name);

        $player = $this->createRealPlayer('cueilleur');
        $player->get_data();

        $picked = (new GroundLootService())->collect(
            $player,
            $coordsId,
            (object) ['x' => 2, 'y' => 1, 'z' => 0, 'plan' => self::PLAN]
        );

        $this->assertNotEmpty($picked, 'la cueillette rend ce qu\'elle a pris');
        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT COUNT(*) FROM map_plants WHERE coords_id = ?', [$coordsId])
        );
        $this->assertSame([], (new GroundLootService())->listAt(2, 1, 0, self::PLAN)['plants']);
    }

    /** Tant que personne ne ramasse, la fleur reste — on peut passer dessus. */
    public function testAnUncollectedPlantStays(): void
    {
        $name = $this->somePlantName();
        $coordsId = $this->coordsId(3, 1);
        $this->plantAt($coordsId, $name);

        $this->assertSame(
            1,
            (int) $this->link->fetchOne('SELECT COUNT(*) FROM map_plants WHERE coords_id = ?', [$coordsId])
        );
    }

    /** Une case sans rien ne rend rien, plantes comprises. */
    public function testAnEmptyTileCollectsNothing(): void
    {
        $player = $this->createRealPlayer('cueilleur_vide');
        $player->get_data();

        $this->assertSame(
            [],
            (new GroundLootService())->collect(
                $player,
                $this->coordsId(4, 1),
                (object) ['x' => 4, 'y' => 1, 'z' => 0, 'plan' => self::PLAN]
            )
        );
    }
}
