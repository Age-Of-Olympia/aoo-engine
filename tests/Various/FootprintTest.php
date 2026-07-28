<?php

namespace Tests\Various;

use App\Service\Map\Footprint;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * La découpe d'un décor, et les deux invariants qu'elle porte seule.
 *
 * Le concept vivait en tableau associatif : sa forme recopiée mot pour mot
 * dans neuf annotations, et `cells` / `holed` recalculés par trois services
 * qui n'avaient aucun moyen de tomber d'accord. Ces cas fixent ce que le type
 * garantit désormais à leur place.
 *
 * Aucune base de données : une figure est une donnée, pas un enregistrement.
 */
class FootprintTest extends TestCase
{
    /**
     * L'invariant qui manquait : les décalages sont relatifs au premier
     * morceau, quoi qu'on donne à la construction.
     *
     * Toutes les sources respectaient la convention par habitude, et le code
     * qui pose une emprise s'y fiait sans filet — une découpe enregistrée
     * décalée aurait posé ses cases à côté de l'entité.
     */
    public function testOffsetsAreBroughtBackToTheFirstPiece(): void
    {
        $shifted = Footprint::fromOffsets([0 => [5, 5], 1 => [6, 5], 2 => [5, 4]]);

        $this->assertSame(
            [0 => [0, 0], 1 => [1, 0], 2 => [0, -1]],
            $shifted->offsets()
        );
    }

    /** Les morceaux sont rangés par indice : le premier est bien le premier. */
    public function testPiecesAreOrderedWhateverTheirArrivalOrder(): void
    {
        $jumbled = Footprint::fromOffsets([2 => [2, 0], 0 => [0, 0], 1 => [1, 0]]);

        $this->assertSame([0, 1, 2], array_keys($jumbled->offsets()));
        $this->assertSame([0, 0], $jumbled->offsets()[0]);
    }

    /** Ce qui se déduit ne se stocke pas : la boîte suit les morceaux. */
    public function testTheBoxFollowsThePiecesWhenItIsNotGiven(): void
    {
        $figure = Footprint::fromOffsets([0 => [0, 0], 1 => [1, 0], 2 => [0, -1], 3 => [1, -1]]);

        $this->assertSame(2, $figure->width());
        $this->assertSame(2, $figure->height());
        $this->assertSame(4, $figure->cells());
        $this->assertFalse($figure->isHoled(), 'quatre cases dans une boîte de quatre');
    }

    /**
     * Une boîte donnée peut dépasser la figure, et c'est tout l'objet.
     *
     * L'image d'ensemble d'un décor annonce sa taille : un géant de 3×3 qui
     * n'occupe que quatre cases est troué. Déduire la boîte des décalages
     * effacerait justement le trou.
     */
    public function testAGivenBoxMayExceedTheFigureAndRevealsTheHoles(): void
    {
        $giant = Footprint::boxed(3, 3, [0 => [0, 0], 1 => [0, -1], 2 => [-1, -2], 3 => [-2, -2]]);

        $this->assertSame(4, $giant->cells());
        $this->assertTrue($giant->isHoled(), 'quatre cases dans une boîte de neuf');
    }

    /**
     * LE calcul de la figure : où tombent ses cases vue depuis un morceau.
     *
     * Il servait sous quatre formes recopiées — poser depuis la palette,
     * situer les morceaux manquants, étendre une emprise, dessiner le
     * curseur — et chacune se trompait à sa façon quand le morceau choisi
     * n'était pas le premier.
     */
    public function testTheFigureIsSeenFromAnyOfItsPieces(): void
    {
        $tower = Footprint::fromOffsets([0 => [0, 0], 1 => [0, -1]]);

        $this->assertSame(
            [0 => [10, 10], 1 => [10, 9]],
            $tower->cellsAround(0, 10, 10),
            'depuis le premier morceau'
        );

        $this->assertSame(
            [0 => [10, 11], 1 => [10, 10]],
            $tower->cellsAround(1, 10, 10),
            'depuis le second : c\'est LUI qui tombe sur la case visée'
        );
    }

    /** Un morceau inconnu ne déplace rien plutôt que de faire tomber la pose. */
    public function testAnUnknownPieceLeavesTheFigureWhereItIs(): void
    {
        $tower = Footprint::fromOffsets([0 => [0, 0], 1 => [0, -1]]);

        $this->assertSame([0 => [3, 3], 1 => [3, 2]], $tower->cellsAround(99, 3, 3));
    }

    /** Le rôle n'existe que là où un humain s'est prononcé. */
    public function testOnlyDecidedRolesAreKept(): void
    {
        $arch = Footprint::fromOffsets(
            [0 => [0, 0], 1 => [0, -1]],
            [0 => 'block', 1 => '']
        );

        $this->assertSame([0 => 'block'], $arch->roles(), 'le rôle vide n\'est pas un rôle');
        $this->assertSame('block', $arch->roleOf(0, 'part'));
        $this->assertSame('part', $arch->roleOf(1, 'part'), 'sans avis, c\'est le défaut proposé');
    }

    /** Une figure d'une seule case n'a rien à étendre. */
    public function testASingleCellFigureSaysSo(): void
    {
        $this->assertTrue(Footprint::fromOffsets([0 => [0, 0]])->isSingleCell());
        $this->assertFalse(Footprint::fromOffsets([0 => [0, 0], 1 => [1, 0]])->isSingleCell());
    }

    public function testAFigureWithoutAnyPieceIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Footprint::fromOffsets([]);
    }

    /** Une boîte trop petite pour ses morceaux décrit autre chose qu'eux. */
    public function testABoxTooSmallForItsPiecesIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Footprint::boxed(1, 1, [0 => [0, 0], 1 => [1, 0]]);
    }

    /** La frontière avec le JavaScript reste un tableau, et reste complète. */
    public function testTheArrayFormCarriesEverythingTheEditorNeeds(): void
    {
        $figure = Footprint::boxed(2, 2, [0 => [0, 0], 1 => [1, 0]], [1 => 'block']);

        $this->assertSame(
            [
                'w'       => 2,
                'h'       => 2,
                'cells'   => 2,
                'holed'   => true,
                'offsets' => [0 => [0, 0], 1 => [1, 0]],
                'roles'   => [1 => 'block'],
            ],
            $figure->toArray()
        );
    }
}
