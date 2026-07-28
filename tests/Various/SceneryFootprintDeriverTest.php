<?php

namespace Tests\Various;

use App\Service\Map\SceneryFootprintDeriver;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Reconstruire un objet à partir de ses morceaux posés.
 *
 * Un fort, une pyramide, un géant occupent plusieurs cases, et rien ne le
 * dit : on pose des morceaux nommés `base-00`, `base-01`… que seule leur
 * adjacence relie.
 *
 * Ces cas verrouillent les trois décisions qui font marcher la dérivation,
 * chacune tirée d'un défaut mesuré sur la carte de production.
 */
#[Group('items-golden-master')]
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

    /**
     * LE piège : deux exemplaires COLLÉS ne font pas un objet de quatre cases.
     *
     * La connexité seule les fusionnerait — sur la production, une composante
     * de 29 cases avale treize géants. Le critère est l'unicité de l'indice de
     * morceau dans le groupe.
     */
    public function testTwoTouchingCopiesAreTwoObjectsNotOne(): void
    {
        /* un exemplaire à l'écart : c'est lui qui donne la figure */
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

    /**
     * Une famille dont TOUS les exemplaires se touchent n'est pas dérivable —
     * et le dire vaut mieux que deviner.
     *
     * Sans un exemplaire à l'écart, rien ne dit où s'arrête l'un et où
     * commence l'autre : un agrégat de deux bornes de deux cases est
     * indiscernable d'une borne unique de quatre. La famille part alors dans
     * la liste à trancher, avec `lac_thetis` et `triton_statue`.
     */
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

    /**
     * Une figure TROUÉE est légitime, et son ancre n'est pas le coin.
     *
     * Le géant pétrifié occupe 4 cases dans une boîte de 3×3 : s'ancrer au
     * coin bas-gauche — qui n'est pas posé — donnerait un décalage faux. Cinq
     * familles de production sont dans ce cas.
     */
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

    /** Un exemplaire à qui il manque un morceau est signalé, pas pris pour modèle. */
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

    /**
     * Sans aucun exemplaire complet, la découpe n'est pas dérivable — et on
     * le dit plutôt que de deviner.
     *
     * C'est le cas de `lac_thetis`, dont les suffixes sont deux variantes de
     * lac et non les moitiés d'une figure.
     */
    public function testAFamilyWithoutAnyCompleteCopyIsFlagged(): void
    {
        $this->put('gm_lac-04', 0, 0);
        $this->put('gm_lac-05', 5, 5);

        $deriver = $this->deriver();

        $this->assertArrayNotHasKey('gm_lac', $deriver->derive());
        $this->assertArrayHasKey('gm_lac', $deriver->undecidable());
    }

    /** Un décor d'une seule case n'a pas de découpe — et n'encombre pas le catalogue. */
    public function testASingleTileDecorHasNoFootprint(): void
    {
        $this->put('gm_rocher', 3, 3);
        $this->put('gm_rocher', 4, 4);

        $this->assertArrayNotHasKey('gm_rocher', $this->deriver()->derive());
    }

    /**
     * Ce qui EXISTE sur le disque, par opposition à ce qui est posé.
     *
     * La page d'administration liste les décors à régler, et une famille dont
     * aucun exemplaire n'est posé est justement la plus concernée : elle ne
     * peut pas venir de la carte. Ce cas ne fixe donc pas un contenu — il
     * dépend des assets du déploiement — mais la FORME de la réponse, dont
     * l'éditeur dépend pour afficher les images.
     */
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
