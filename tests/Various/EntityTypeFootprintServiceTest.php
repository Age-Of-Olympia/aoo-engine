<?php

namespace Tests\Various;

use App\Service\Map\EntityTypeFootprintService;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Declared cut-outs, and their right to contradict the guessed ones.
 *
 * What these cases pin is the ORDER: a declaration wins over what the map
 * shows, otherwise a badly placed decor would be its own authority.
 */
#[Group('items-golden-master')]
class EntityTypeFootprintServiceTest extends LegacyPlayerFixtureTestCase
{
    private const PLAN = 'plan_test_decoupes';

    /** @var list<string> families a case declared, undone afterwards */
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

    /** A figure placed on the map, from which the cut-out derives. */
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

    /** Otherwise a badly placed decor would be its own authority. */
    public function testADeclarationOverridesWhatTheMapShows(): void
    {
        $this->seedOnMap('gm_decl_tour');

        $this->assertSame('map', $this->service()->sourceOf('gm_decl_tour'));
        $this->assertSame(2, $this->service()->catalogue()['gm_decl_tour']->cells());

        $this->declare('gm_decl_tour', 2, 2, [0 => [0, 0], 1 => [0, -1], 2 => [1, 0], 3 => [1, -1]]);

        $footprint = $this->service()->catalogue()['gm_decl_tour'];

        $this->assertSame('declared', $this->service()->sourceOf('gm_decl_tour'));
        $this->assertSame(4, $footprint->cells(), 'la déclaration, pas les deux cases de la carte');
        $this->assertSame(2, $footprint->width());
    }

    /** Forgotten, the family falls back to what the map shows. */
    public function testForgettingReturnsTheTypeToTheMap(): void
    {
        $this->seedOnMap('gm_decl_oubli');
        $this->declare('gm_decl_oubli', 3, 3, [0 => [0, 0], 1 => [1, 1]]);

        $this->assertSame('declared', $this->service()->sourceOf('gm_decl_oubli'));

        $this->service()->forget('gm_decl_oubli');

        $this->assertSame('map', $this->service()->sourceOf('gm_decl_oubli'));
        $this->assertSame(2, $this->service()->catalogue()['gm_decl_oubli']->cells());
    }

    /** Why offsets are stored rather than a box: a 3×3 box would promise nine cells. */
    public function testAHoledFigureSurvivesTheRoundTrip(): void
    {
        $offsets = [0 => [0, 0], 1 => [0, -1], 2 => [-1, -2], 3 => [-2, -2]];

        $this->declare('gm_decl_geant', 3, 3, $offsets);

        $footprint = $this->service()->catalogue()['gm_decl_geant'];

        $this->assertSame($offsets, $footprint->offsets(), 'les décalages, au morceau près');
        $this->assertSame(4, $footprint->cells());
        $this->assertTrue($footprint->isHoled(), '4 cases dans une boîte de 9');
    }

    /** One value for the whole figure could not say that only its base blocks. */
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
            $this->service()->declared()['gm_decl_arche']->roles()
        );
    }

    /** The reason the key is the name: scenery families have no row in `races` yet. */
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

    /** A cut-out without any piece describes nothing: refused. */
    public function testAnEmptyFootprintIsRefused(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service()->declare('gm_decl_vide', 1, 1, []);
    }

    /** Dimensions stay within what a decor can reach. */
    public function testOutOfRangeDimensionsAreRefused(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service()->declare('gm_decl_immense', 99, 99, [0 => [0, 0]]);
    }
}
