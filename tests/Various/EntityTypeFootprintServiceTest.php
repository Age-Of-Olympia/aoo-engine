<?php

namespace Tests\Various;

use App\Service\Map\EntityTypeFootprintService;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * La découpe déclarée, et son droit de contredire les autres sources.
 *
 * Trois sources savent dire la forme d'un décor multi-cases : la déclaration,
 * la carte, les images d'ensemble. Elles ne se valent pas, et le désaccord
 * n'est pas théorique — l'image de `geant_petrifie` annonce 1×2 cases quand
 * quatre morceaux existent et que la carte en montre une figure de 3×3
 * trouée.
 *
 * Ce qui est fixé ici est donc l'ORDRE, et lui seul importe : une déclaration
 * l'emporte sur ce que la carte montre. Sans cela on ne pourrait jamais
 * réparer un décor mal posé, puisque c'est le décor mal posé qui ferait
 * autorité.
 */
#[Group('items-golden-master')]
class EntityTypeFootprintServiceTest extends LegacyPlayerFixtureTestCase
{
    private const PLAN = 'plan_test_decoupes';

    /** @var list<string> les familles déclarées par un cas, à défaire après lui */
    private array $declaredFamilies = [];

    protected function tearDown(): void
    {
        $link = $this->link;

        foreach ($this->declaredFamilies as $family) {
            $link->executeStatement('DELETE FROM entity_type_footprints WHERE type_name = ?', [$family]);
        }

        $this->declaredFamilies = [];

        $link->executeStatement(
            'DELETE m FROM map_foregrounds m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );

        parent::tearDown();

        $link->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
    }

    private function service(): EntityTypeFootprintService
    {
        return new EntityTypeFootprintService($this->link);
    }

    /**
     * Une famille déclarée par le cas courant, défaite au démontage.
     *
     * @param array<int, array{0:int,1:int}> $offsets
     * @param array<int, string> $roles
     */
    private function declare(string $family, int $w, int $h, array $offsets, array $roles = []): void
    {
        $this->declaredFamilies[] = $family;
        $this->service()->declare($family, $w, $h, $offsets, $roles);
    }

    /** Une figure posée sur la carte, dont la découpe se déduit. */
    private function seedOnMap(string $family): void
    {
        foreach ([[0, 1, '00'], [0, 0, '01']] as [$x, $y, $piece]) {
            $this->link->executeStatement(
                'INSERT INTO map_foregrounds (name, coords_id) VALUES (?, ?)',
                [
                    $family . '-' . $piece,
                    (int) View::get_coords_id((object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => self::PLAN]),
                ]
            );
        }
    }

    /**
     * LE cas : la déclaration l'emporte sur la carte.
     *
     * La carte montre une figure de 1×2 ; on déclare 2×2. C'est la
     * déclaration qui doit sortir du catalogue, sans quoi corriger un décor
     * mal posé serait impossible.
     */
    public function testADeclarationOverridesWhatTheMapShows(): void
    {
        $this->seedOnMap('gm_decl_tour');

        $this->assertSame('map', $this->service()->sourceOf('gm_decl_tour'));
        $this->assertSame(2, $this->service()->catalogue()['gm_decl_tour']['cells']);

        $this->declare('gm_decl_tour', 2, 2, [0 => [0, 0], 1 => [0, -1], 2 => [1, 0], 3 => [1, -1]]);

        $footprint = $this->service()->catalogue()['gm_decl_tour'];

        $this->assertSame('declared', $this->service()->sourceOf('gm_decl_tour'));
        $this->assertSame(4, $footprint['cells'], 'la déclaration, pas les deux cases de la carte');
        $this->assertSame(2, $footprint['w']);
    }

    /** Oubliée, la famille retombe sur ce que la carte montre. */
    public function testForgettingReturnsTheTypeToTheMap(): void
    {
        $this->seedOnMap('gm_decl_oubli');
        $this->declare('gm_decl_oubli', 3, 3, [0 => [0, 0], 1 => [1, 1]]);

        $this->assertSame('declared', $this->service()->sourceOf('gm_decl_oubli'));

        $this->service()->forget('gm_decl_oubli');

        $this->assertSame('map', $this->service()->sourceOf('gm_decl_oubli'));
        $this->assertSame(2, $this->service()->catalogue()['gm_decl_oubli']['cells']);
    }

    /**
     * Une figure trouée survit à l'aller-retour.
     *
     * C'est ce qui justifie de stocker des décalages plutôt qu'une boîte :
     * le géant est haut de trois cases et large de trois, mais n'en occupe
     * que quatre. Une boîte 3×3 en promettrait neuf.
     */
    public function testAHoledFigureSurvivesTheRoundTrip(): void
    {
        $offsets = [0 => [0, 0], 1 => [0, -1], 2 => [-1, -2], 3 => [-2, -2]];

        $this->declare('gm_decl_geant', 3, 3, $offsets);

        $footprint = $this->service()->catalogue()['gm_decl_geant'];

        $this->assertSame($offsets, $footprint['offsets'], 'les décalages, au morceau près');
        $this->assertSame(4, $footprint['cells']);
        $this->assertTrue($footprint['holed'], '4 cases dans une boîte de 9');
    }

    /**
     * Le rôle se déclare par morceau.
     *
     * C'est ce qui permet à la base d'un décor de bloquer pendant que sa
     * partie haute se traverse : une seule valeur pour toute la figure ne
     * saurait pas le dire.
     */
    public function testRolesAreKeptPieceByPiece(): void
    {
        $this->declare(
            'gm_decl_arche',
            1,
            2,
            [0 => [0, 0], 1 => [0, -1]],
            [0 => 'block', 1 => 'cover']
        );

        $this->assertSame(
            [0 => 'block', 1 => 'cover'],
            $this->service()->declared()['gm_decl_arche']['roles']
        );
    }

    /**
     * Une famille absente de `races` se déclare quand même.
     *
     * C'est la raison d'être de la clé par nom : sur les découpes connues, la
     * quasi-totalité des familles de décor n'ont pas encore de ligne dans le
     * catalogue des types. Leur refuser la déclaration la rendrait inutile
     * là où elle sert.
     */
    public function testAFamilyAbsentFromTheTypeCatalogueCanStillBeDeclared(): void
    {
        $absent = (int) $this->link->fetchOne(
            'SELECT COUNT(*) FROM races WHERE name = ?',
            ['gm_decl_inconnue']
        );

        $this->assertSame(0, $absent, 'la famille n\'est pas un type du catalogue');

        $this->declare('gm_decl_inconnue', 1, 2, [0 => [0, 0], 1 => [0, -1]]);

        $this->assertSame('declared', $this->service()->sourceOf('gm_decl_inconnue'));
    }

    /** Une découpe sans morceau ne décrit rien : elle est refusée. */
    public function testAnEmptyFootprintIsRefused(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service()->declare('gm_decl_vide', 1, 1, []);
    }

    /** Les dimensions restent dans des bornes qu'un décor peut atteindre. */
    public function testOutOfRangeDimensionsAreRefused(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service()->declare('gm_decl_immense', 99, 99, [0 => [0, 0]]);
    }
}
