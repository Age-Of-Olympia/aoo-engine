<?php

namespace Tests\Various;

use App\Service\Map\SceneryObjectService;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Placing, inspecting and removing a multi-cell scenery object in ONE gesture.
 *
 * The case that matters most is the neighbour: two touching objects are
 * adjacent, and confusing them would erase the one not aimed at.
 */
#[Group('items-golden-master')]
class SceneryObjectServiceTest extends LegacyPlayerFixtureTestCase
{
    private const PLAN = 'plan_test_objets';

    protected function tearDown(): void
    {
        $link = $this->link;

        $link->executeStatement(
            'DELETE m FROM map_foregrounds m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
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

    private function put(string $name, int $x, int $y): int
    {
        $id = $this->coordsId($x, $y);
        $this->link->executeStatement(
            'INSERT INTO map_foregrounds (name, coords_id) VALUES (?, ?)',
            [$name, $id]
        );

        return $id;
    }

    private function service(): SceneryObjectService
    {
        return new SceneryObjectService();
    }

    /** A model figure, so the cut-out can be derived. */
    private function seedModel(string $family): void
    {
        $this->put($family . '-00', 0, 1);
        $this->put($family . '-01', 0, 0);
    }

    /** Any piece can be picked; that one must land where the click was. */
    public function testThePickedPieceLandsOnTheClickedTile(): void
    {
        $this->seedModel('gm_veilleur');

        $cells = $this->service()->cellsToPlace('gm_veilleur-01', 10, 10);

        $this->assertSame([10, 10], $cells['gm_veilleur-01'], 'le morceau choisi est sur la case visée');
        $this->assertSame([10, 11], $cells['gm_veilleur-00'], 'et l\'autre au-dessus, comme le modèle');
    }

    /** A family without a known cut-out is not guessed: plain placement. */
    public function testAnUnknownFamilyIsNotGuessed(): void
    {
        $this->assertSame([], $this->service()->cellsToPlace('gm_inconnu', 5, 5));
    }

    /** Every cell of an object is found from any of them. */
    public function testTheWholeObjectIsFoundFromAnyOfItsTiles(): void
    {
        $this->seedModel('gm_tour');
        $bas = $this->put('gm_tour-01', 20, 20);
        $haut = $this->put('gm_tour-00', 20, 21);

        $fromBottom = $this->service()->objectCellsAt($bas, 'gm_tour-01');
        $fromTop = $this->service()->objectCellsAt($haut, 'gm_tour-00');

        sort($fromBottom);
        sort($fromTop);
        $expected = [$bas, $haut];
        sort($expected);

        $this->assertSame($expected, $fromBottom);
        $this->assertSame($expected, $fromTop, 'le même objet, vu de son autre bout');
    }

    /** Two towers side by side touch; removing one must leave the other standing. */
    public function testATouchingNeighbourIsNotSwallowed(): void
    {
        $this->seedModel('gm_borne');

        $mienBas = $this->put('gm_borne-01', 30, 30);
        $mienHaut = $this->put('gm_borne-00', 30, 31);
        $voisinBas = $this->put('gm_borne-01', 31, 30);
        $voisinHaut = $this->put('gm_borne-00', 31, 31);

        $cells = $this->service()->objectCellsAt($mienBas, 'gm_borne-01');

        $this->assertCount(2, $cells, 'un objet, deux cases');
        $this->assertContains($mienBas, $cells);
        $this->assertContains($mienHaut, $cells);
        $this->assertNotContains($voisinBas, $cells, 'le voisin reste debout');
        $this->assertNotContains($voisinHaut, $cells);
    }

    /** A truncated figure is seen, and completed. */
    public function testATruncatedObjectIsSeenAndCompleted(): void
    {
        $this->seedModel('gm_arche');

        $bas = $this->put('gm_arche-01', 40, 40);

        $state = $this->service()->inspect($bas, 'gm_arche-01');

        $this->assertNotNull($state);
        $this->assertSame('gm_arche', $state['family']);
        $this->assertCount(1, $state['missing'], 'il manque le haut');

        $this->assertSame(1, $this->service()->complete($bas, 'gm_arche-01'));

        $after = $this->service()->inspect($bas, 'gm_arche-01');
        $this->assertSame([], $after['missing'], 'la figure est complète');
    }

    /** Completing an already complete figure places nothing. */
    public function testCompletingACompleteFigureDoesNothing(): void
    {
        $this->seedModel('gm_stele');
        $bas = $this->put('gm_stele-01', 50, 50);
        $this->put('gm_stele-00', 50, 51);

        $this->assertSame(0, $this->service()->complete($bas, 'gm_stele-01'));
    }
}
