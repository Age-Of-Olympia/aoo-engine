<?php

namespace Tests\Various;

use App\Service\Map\SceneryFootprintDeriver;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Deriving scenery cut-outs from the map: the three decisions that make it
 * work — grouping by touching cells, piece-index uniqueness as the stop rule,
 * and anchoring on the first piece.
 */
#[Group('items-baseline')]
class SceneryFootprintDeriverTest extends LegacyPlayerFixtureTestCase
{
    private const PLAN = 'plan_test_decoupes';

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

    /** Pose un morceau de décor sur la carte de test. */
    private function put(string $name, int $x, int $y): void
    {
        $coordsId = (int) View::get_coords_id(
            (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => self::PLAN]
        );

        $this->link->executeStatement(
            'INSERT INTO map_foregrounds (name, coords_id) VALUES (?, ?)',
            [$name, $coordsId]
        );
    }

    private function deriver(): SceneryFootprintDeriver
    {
        return new SceneryFootprintDeriver();
    }

    /** Les trois conventions de nommage donnent la même famille. */
    public function testTheThreeNamingConventionsAreUnderstood(): void
    {
        $this->assertSame(['tour', 1], SceneryFootprintDeriver::splitPiece('tour-01'));
        $this->assertSame(['tour', 1], SceneryFootprintDeriver::splitPiece('tour_01'));
        $this->assertSame(['tour', 1], SceneryFootprintDeriver::splitPiece('tour1'));
        $this->assertSame(['rocher', 0], SceneryFootprintDeriver::splitPiece('rocher'));
    }

    /** Une figure simple : deux morceaux l'un sur l'autre. */
    public function testATwoPieceFigureIsDerived(): void
    {
        $this->put('gm_totem-00', 0, 1);
        $this->put('gm_totem-01', 0, 0);

        $derived = $this->deriver()->derive()['gm_totem'] ?? null;
        $f = $derived['footprint'] ?? null;

        $this->assertNotNull($f);
        $this->assertSame(1, $f->width());
        $this->assertSame(2, $f->height());
        $this->assertSame(2, $f->cells());
        $this->assertFalse($f->isHoled());
        $this->assertSame(1, $derived['instances']);
        $this->assertSame([0 => [0, 0], 1 => [0, -1]], $f->offsets(), 'décalages relatifs au premier morceau');
    }

    /** Grouping is not plain connectivity; piece-index uniqueness is the stop rule. */
    public function testTwoTouchingCopiesAreTwoObjectsNotOne(): void
    {
        /* a copy standing apart: that one gives the figure */
        $this->put('gm_borne-00', 0, 1);
        $this->put('gm_borne-01', 0, 0);
        /* deux autres, collés l'un à l'autre */
        $this->put('gm_borne-00', 6, 1);
        $this->put('gm_borne-01', 6, 0);
        $this->put('gm_borne-00', 7, 1);
        $this->put('gm_borne-01', 7, 0);

        $derived = $this->deriver()->derive()['gm_borne'] ?? null;
        $f = $derived['footprint'] ?? null;

        $this->assertNotNull($f);
        $this->assertSame(2, $f->cells(), 'la figure fait deux cases, pas quatre');
        $this->assertSame(3, $derived['instances'], 'l\'agrégat en vaut deux, plus celui à l\'écart');
        $this->assertSame(0, $derived['truncated'], 'aucun n\'est incomplet');
    }

    /** A cluster carries every piece several times: it is not a model. */
    public function testAFamilyWhoseCopiesAllTouchIsFlaggedRatherThanGuessed(): void
    {
        $this->put('gm_serree-00', 0, 1);
        $this->put('gm_serree-01', 0, 0);
        $this->put('gm_serree-00', 1, 1);
        $this->put('gm_serree-01', 1, 0);

        $deriver = $this->deriver();

        $this->assertArrayNotHasKey('gm_serree', $deriver->derive());
        $this->assertArrayHasKey('gm_serree', $deriver->undecidable());
    }

    /** A holed figure may have no cell at its bottom-left corner. */
    public function testAHoledFigureKeepsItsShapeAndAnchorsOnItsFirstPiece(): void
    {
        $this->put('gm_geant-00', 2, 2);
        $this->put('gm_geant-01', 2, 1);
        $this->put('gm_geant-02', 1, 0);
        $this->put('gm_geant-03', 0, 0);

        $derived = $this->deriver()->derive()['gm_geant'] ?? null;
        $f = $derived['footprint'] ?? null;

        $this->assertNotNull($f);
        $this->assertSame(3, $f->width());
        $this->assertSame(3, $f->height());
        $this->assertSame(4, $f->cells(), 'quatre cases dans une boîte de neuf');
        $this->assertTrue($f->isHoled());
        $this->assertSame(
            [0 => [0, 0], 1 => [0, -1], 2 => [-1, -2], 3 => [-2, -2]],
            $f->offsets()
        );
    }

    /** A copy missing a piece is reported, not taken as the model. */
    public function testATruncatedCopyIsReportedAndNotUsedAsTheModel(): void
    {
        /* complet */
        $this->put('gm_arche-00', 0, 1);
        $this->put('gm_arche-01', 1, 1);
        $this->put('gm_arche-02', 0, 0);
        $this->put('gm_arche-03', 1, 0);
        /* tronqué, à l'écart */
        $this->put('gm_arche-00', 8, 8);
        $this->put('gm_arche-01', 9, 8);

        $derived = $this->deriver()->derive()['gm_arche'] ?? null;
        $f = $derived['footprint'] ?? null;

        $this->assertNotNull($f);
        $this->assertSame(4, $f->cells(), 'la figure complète fait foi');
        $this->assertSame(2, $derived['instances']);
        $this->assertSame(1, $derived['truncated']);
    }

    public function testAFamilyWithoutAnyCompleteCopyIsFlagged(): void
    {
        $this->put('gm_lac-04', 0, 0);
        $this->put('gm_lac-05', 5, 5);

        $deriver = $this->deriver();

        $this->assertArrayNotHasKey('gm_lac', $deriver->derive());
        $this->assertArrayHasKey('gm_lac', $deriver->undecidable());
    }

    /** Objects are enumerated one per copy, cells and coords included. */
    public function testObjectsAreEnumeratedOnePerCopy(): void
    {
        $this->put('gm_enum-00', 0, 1);
        $this->put('gm_enum-01', 0, 0);
        $this->put('gm_enum-00', 5, 1);
        $this->put('gm_enum-01', 5, 0);

        $mine = array_values(array_filter(
            $this->deriver()->objects(),
            static fn(array $object): bool => $object['family'] === 'gm_enum'
        ));

        $this->assertCount(2, $mine, 'two copies, two objects');

        foreach ($mine as $object) {
            $this->assertCount(2, $object['cells']);
            $this->assertSame([0, 1], array_column($object['cells'], 'piece'));

            foreach ($object['cells'] as $cell) {
                $this->assertGreaterThan(0, $cell['coords_id'], 'the conversion needs the coords id');
                $this->assertSame(self::PLAN, $cell['plan']);
            }
        }
    }

    /** Touching copies stay separate objects, as the grouping promises. */
    public function testTouchingCopiesAreEnumeratedSeparately(): void
    {
        $this->put('gm_serre-00', 10, 1);
        $this->put('gm_serre-01', 10, 0);
        $this->put('gm_serre-00', 11, 1);
        $this->put('gm_serre-01', 11, 0);

        $mine = array_filter(
            $this->deriver()->objects(),
            static fn(array $object): bool => $object['family'] === 'gm_serre'
        );

        $this->assertCount(2, $mine);
    }

    /** A single-cell decor has no cut-out, and does not clutter the catalogue. */
    public function testASingleTileDecorHasNoFootprint(): void
    {
        $this->put('gm_rocher', 3, 3);
        $this->put('gm_rocher', 4, 4);

        $this->assertArrayNotHasKey('gm_rocher', $this->deriver()->derive());
    }

    /** Pins the SHAPE of the answer, not its content, which depends on deployed assets. */
    public function testDiskPiecesAreGroupedByFamilyAndAddressable(): void
    {
        $onDisk = $this->deriver()->piecesOnDisk();

        if ($onDisk === []) {
            $this->markTestSkipped('Aucun décor sur ce déploiement : img/foregrounds/ est absent.');
        }

        foreach ($onDisk as $family => $pieces) {
            $this->assertNotSame('', (string) $family);
            $this->assertNotEmpty($pieces, 'une famille sans morceau ne devrait pas être listée');

            foreach ($pieces as $url) {
                $this->assertStringStartsWith('/img/foregrounds/' . $family, $url);
                $this->assertStringEndsWith('.png', $url);
            }

            $this->assertSame(
                array_keys($pieces),
                array_values(array_unique(array_keys($pieces))),
                'un morceau n\'apparaît qu\'une fois par famille'
            );
        }
    }
}
