<?php

namespace App\Service\Map;

use App\Entity\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * Whether `map_foregrounds` can go yet, and what still holds it back.
 *
 * The table is on its way out: scenery became entities, and the renderer is
 * to draw them from `entity_cells`. Until then it is load-bearing in two
 * ways that are easy to forget — it still feeds the screen, and it is the
 * EVIDENCE a figure's shape is derived from. A family whose cut-out is only
 * guessed from pieces standing on the map loses that shape the day the table
 * goes, and nobody could check it afterwards: the artwork lies (the
 * `geant_petrifie` image claims 1×2 for a four-piece figure).
 *
 * So the answer is computed, not remembered, and it is shown where the
 * shapes are settled.
 */
final class MapForegroundsRetirement
{
    /**
     * Whether the renderer still reads the table.
     *
     * Turn this off with the change that makes `Classes\View` draw scenery
     * from the entities — the notice follows on its own.
     */
    public const RENDERER_READS_TABLE = true;

    private ?Connection $conn;

    private ?EntityTypeFootprintService $footprints;

    /** Whether the renderer still reads the table — see the constant. */
    private bool $rendererReadsTable;

    public function __construct(
        ?Connection $conn = null,
        ?EntityTypeFootprintService $footprints = null,
        ?bool $rendererReadsTable = null
    ) {
        $this->conn = $conn;
        $this->footprints = $footprints;
        $this->rendererReadsTable = $rendererReadsTable ?? self::RENDERER_READS_TABLE;
    }

    private function conn(): Connection
    {
        return $this->conn ??= EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * @return array{
     *     droppable: bool,
     *     rows: int,
     *     orphanRows: int,
     *     shapesFromMap: list<string>,
     *     blockers: list<string>
     * }
     */
    public function status(): array
    {
        $rows = (int) $this->conn()->fetchOne('SELECT COUNT(*) FROM map_foregrounds');
        $orphanRows = $this->orphanRows();
        $shapesFromMap = $this->shapesFromMap();

        $blockers = [];

        if ($this->rendererReadsTable) {
            $blockers[] = 'le rendu de la carte y lit encore le décor';
        }

        if ($shapesFromMap !== []) {
            $blockers[] = count($shapesFromMap) . ' '
                . (count($shapesFromMap) > 1 ? 'familles tiennent leur forme' : 'famille tient sa forme')
                . ' des morceaux posés sur la carte, et rien d\'autre ne la sait';
        }

        if ($orphanRows > 0) {
            $blockers[] = $orphanRows . ' '
                . ($orphanRows > 1 ? 'lignes ne sont couvertes' : 'ligne n\'est couverte')
                . ' par aucune entité : ce décor-là disparaîtrait';
        }

        return [
            'droppable'     => $blockers === [],
            'rows'          => $rows,
            'orphanRows'    => $orphanRows,
            'shapesFromMap' => $shapesFromMap,
            'blockers'      => $blockers,
        ];
    }

    /** Pieces on the board that no entity claims — they would simply vanish. */
    private function orphanRows(): int
    {
        return (int) $this->conn()->fetchOne(
            "SELECT COUNT(*)
               FROM map_foregrounds f
              WHERE NOT EXISTS (
                    SELECT 1
                      FROM entity_cells ec
                      JOIN players p ON p.id = ec.player_id
                     WHERE ec.coords_id = f.coords_id
                       AND p.player_type = 'scenery'
              )"
        );
    }

    /**
     * Families whose shape is read off the map and nowhere else.
     *
     * @return list<string>
     */
    private function shapesFromMap(): array
    {
        $service = $this->footprints ??= new EntityTypeFootprintService($this->conn());
        $families = [];

        foreach (array_keys($service->catalogue()) as $family) {
            if ($service->sourceOf((string) $family) === 'map') {
                $families[] = (string) $family;
            }
        }

        sort($families);

        return $families;
    }
}
