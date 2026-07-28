<?php

namespace Tests\Various;

use App\Service\Map\SceneryObjectService;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Poser, inspecter et retirer un décor multi-cases D'UN SEUL GESTE.
 *
 * L'éditeur travaillait case par case : poser un fort demandait de placer
 * ses quatorze morceaux à la main, en effacer un n'en retirait qu'un. C'est
 * la fabrique des trente et quelques fragments orphelins que porte la carte.
 *
 * Le cas le plus important est celui du voisin : deux décors collés sont
 * adjacents, et confondre l'un avec l'autre ferait disparaître celui qu'on
 * ne visait pas.
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

    /** Une figure modèle, pour que la découpe soit dérivable. */
    private function seedModel(string $family): void
    {
        $this->put($family . '-00', 0, 1);
        $this->put($family . '-01', 0, 0);
    }

    /**
     * Le morceau choisi tombe sur la case cliquée, le reste se place autour.
     *
     * L'animateur prend n'importe quel morceau dans la palette : c'est CELUI-LÀ
     * qui doit atterrir où il a cliqué, sinon la figure part de travers.
     */
    public function testThePickedPieceLandsOnTheClickedTile(): void
    {
        $this->seedModel('gm_veilleur');

        $cells = $this->service()->cellsToPlace('gm_veilleur-01', 10, 10);

        $this->assertSame([10, 10], $cells['gm_veilleur-01'], 'le morceau choisi est sur la case visée');
        $this->assertSame([10, 11], $cells['gm_veilleur-00'], 'et l\'autre au-dessus, comme le modèle');
    }

    /** Une famille sans découpe connue ne se devine pas : pose simple. */
    public function testAnUnknownFamilyIsNotGuessed(): void
    {
        $this->assertSame([], $this->service()->cellsToPlace('gm_inconnu', 5, 5));
    }

    /** Toutes les cases d'un objet se retrouvent depuis n'importe laquelle. */
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

    /**
     * LE cas qui compte : le voisin collé n'est pas absorbé.
     *
     * Deux tours côte à côte se touchent. Retirer la première ne doit pas
     * emporter la seconde — c'est l'unicité de l'indice de morceau qui borne
     * l'objet, pas la connexité.
     */
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

    /** Une figure tronquée se voit, et se complète. */
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

    /** Compléter une figure déjà complète ne pose rien. */
    public function testCompletingACompleteFigureDoesNothing(): void
    {
        $this->seedModel('gm_stele');
        $bas = $this->put('gm_stele-01', 50, 50);
        $this->put('gm_stele-00', 50, 51);

        $this->assertSame(0, $this->service()->complete($bas, 'gm_stele-01'));
    }
}
