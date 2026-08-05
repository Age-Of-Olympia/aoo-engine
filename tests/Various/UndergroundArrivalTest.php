<?php

namespace Tests\Various;

use Classes\View;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * L'arrivée d'un téléporteur SOUS TERRE se fait sur du sol creusé.
 *
 * Le déclencheur `tp` (scripts/map/triggers/tp.php) débarque le joueur sur
 * une case libre AUTOUR de la destination (View::get_free_coords_id_arround)
 * — et « libre » ignorait le sol : sous terre, où seule la case de
 * l'escalier est creusée (map_tiles), l'arrivée tombait dans la roche
 * pleine. go.php voyait alors une case non creusée et démarrait `creuser`,
 * refusé « On ne creuse que sous terre » puisque le joueur n'a pas encore
 * bougé de la surface : l'escalier d'Arcadia ne menait jamais en bas.
 *
 * La règle : sous terre (z < 0), on ne débarque que sur une case creusée ;
 * à défaut, sur la destination elle-même (l'escalier, creusé par
 * construction). En surface, rien ne change.
 */
class UndergroundArrivalTest extends LegacyPlayerFixtureTestCase
{
    /** @var array<int, int> coords_id des map_tiles semées, à nettoyer */
    private array $seededTiles = [];

    protected function tearDown(): void
    {
        foreach ($this->seededTiles as $coordsId) {
            $this->link->executeStatement('DELETE FROM map_tiles WHERE coords_id = ?', [$coordsId]);
        }
        parent::tearDown();
    }

    private function digTile(int $x, int $y, int $z, string $name = 'caverne'): int
    {
        $coordsId = (int) View::get_coords_id((object) ['x' => $x, 'y' => $y, 'z' => $z, 'plan' => 'gaia']);
        $this->link->executeStatement(
            'INSERT INTO map_tiles (name, coords_id) VALUES (?, ?)',
            [$name, $coordsId]
        );
        $this->seededTiles[] = $coordsId;

        return $coordsId;
    }

    public function testArrivalFallsBackToTheStairsWhenNothingElseIsDug(): void
    {
        [$x, $y] = $this->farTile();
        $stairsId = $this->digTile($x, $y, -1, 'escalier_vers_le_haut');

        $goCoords = (object) ['x' => $x, 'y' => $y, 'z' => -1, 'plan' => 'gaia'];
        $landedId = (int) View::get_free_coords_id_arround($goCoords);

        $this->assertSame(
            $stairsId,
            $landedId,
            'seule case creusée du niveau : on débarque sur l\'escalier lui-même, pas dans la roche'
        );
    }

    public function testArrivalPrefersAFreeDugNeighbour(): void
    {
        [$x, $y] = $this->farTile();
        $this->digTile($x, $y, -1, 'escalier_vers_le_haut');
        $dugNeighbourId = $this->digTile($x + 1, $y, -1);

        $goCoords = (object) ['x' => $x, 'y' => $y, 'z' => -1, 'plan' => 'gaia'];
        $landedId = (int) View::get_free_coords_id_arround($goCoords);

        $this->assertSame(
            $dugNeighbourId,
            $landedId,
            'une voisine creusée et libre existe : on y débarque (la destination reste réservée)'
        );
    }

    public function testArrivalDoesNotDriftToADistantDugTile(): void
    {
        [$x, $y] = $this->farTile();
        $stairsId = $this->digTile($x, $y, -1, 'escalier_vers_le_haut');
        $this->digTile($x + 3, $y, -1);

        $goCoords = (object) ['x' => $x, 'y' => $y, 'z' => -1, 'plan' => 'gaia'];

        $this->assertSame(
            $stairsId,
            (int) View::get_free_coords_id_arround($goCoords),
            'une salle creusée à trois cases ne vole pas l\'arrivée : l\'escalier d\'abord'
        );
    }

    public function testSurfaceArrivalIsUnchanged(): void
    {
        [$x, $y] = $this->farTile();

        $goCoords = (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => 'gaia'];
        View::get_free_coords_id_arround($goCoords);

        $this->assertNotSame(
            [$x, $y],
            [(int) $goCoords->x, (int) $goCoords->y],
            'en surface la destination reste réservée : on débarque à côté'
        );
        $this->assertLessThanOrEqual(1, abs((int) $goCoords->x - $x));
        $this->assertLessThanOrEqual(1, abs((int) $goCoords->y - $y));
    }
}
