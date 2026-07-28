<?php

namespace App\Service\Map;

use App\Entity\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * Place and remove a multi-cell scenery object in one gesture.
 *
 * Works on `map_foregrounds` as it stands, so it does not wait for scenery to
 * become entities. Placement drops the whole figure with the picked piece on
 * the clicked tile; removal takes the whole object from any of its cells.
 */
final class SceneryObjectService
{
    private Connection $conn;
    private EntityTypeFootprintService $footprints;

    public function __construct(?Connection $conn = null, ?EntityTypeFootprintService $footprints = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
        $this->footprints = $footprints ?? new EntityTypeFootprintService($this->conn);
    }

    /**
     * Cells to write for a placement. Empty when the family has no known
     * cut-out — a shape must never be guessed.
     *
     * @return array<string, array{0:int,1:int}> piece name => (x, y)
     */
    public function cellsToPlace(string $pickedName, int $x, int $y): array
    {
        [$family, $pickedPiece] = SceneryFootprintDeriver::splitPiece($pickedName);

        $footprint = $this->footprints->catalogue()[$family] ?? null;

        if ($footprint === null || !isset($footprint->offsets()[$pickedPiece])) {
            return [];
        }

        $cells = [];

        /* The picked piece lands on the clicked tile; the figure places the rest. */
        foreach ($footprint->cellsAround($pickedPiece, $x, $y) as $piece => $position) {
            $cells[$this->pieceName($pickedName, $family, $piece)] = $position;
        }

        return $cells;
    }

    /**
     * Cells of the object a given cell belongs to: the touching group around
     * it, stopped at the first already-seen piece index so a neighbouring
     * copy is not swallowed. Always returns at least the given cell.
     *
     * @return list<int>
     */
    public function objectCellsAt(int $coordsId, string $name): array
    {
        [$family, ] = SceneryFootprintDeriver::splitPiece($name);

        $origin = $this->conn->fetchAssociative(
            'SELECT x, y, z, plan FROM coords WHERE id = ?',
            [$coordsId]
        );

        if ($origin === false) {
            return [$coordsId];
        }

        /* Every cell of the family on this plan and level: an object stops there. */
        $rows = $this->conn->fetchAllAssociative(
            "SELECT f.name, f.coords_id, c.x, c.y
               FROM map_foregrounds f
               JOIN coords c ON c.id = f.coords_id
              WHERE c.plan = ? AND c.z = ?",
            [$origin['plan'], (int) $origin['z']]
        );

        $byKey = [];

        foreach ($rows as $row) {
            [$rowFamily, $piece] = SceneryFootprintDeriver::splitPiece((string) $row['name']);

            if ($rowFamily !== $family) {
                continue;
            }

            $cell = [
                'plan'      => $origin['plan'],
                'z'         => (int) $origin['z'],
                'x'         => (int) $row['x'],
                'y'         => (int) $row['y'],
                'piece'     => $piece,
                'coords_id' => (int) $row['coords_id'],
            ];

            $byKey[TouchingCells::key($cell)] = $cell;
        }

        $start = TouchingCells::key($origin);

        if (!isset($byKey[$start])) {
            return [$coordsId];
        }

        /* Stop rule: a piece index already in the group belongs to the
         * neighbouring copy, not this one. */
        $group = TouchingCells::groupAround(
            $byKey,
            $start,
            static function (array $candidate, array $group): bool {
                foreach ($group as $cell) {
                    if ($cell['piece'] === $candidate['piece']) {
                        return false;
                    }
                }

                return true;
            }
        );

        return array_values(array_map(
            static fn (array $cell): int => (int) $cell['coords_id'],
            $group
        ));
    }

    /**
     * What the editor shows about an object: which figure, what is placed,
     * and what is MISSING.
     *
     * @return array{
     *     family: string, footprint: Footprint,
     *     present: list<int>, missing: array<int, array{0:int,1:int}>,
     *     coords_ids: list<int>
     * }|null null when the cell carries no scenery with a known cut-out
     */
    public function inspect(int $coordsId, string $name): ?array
    {
        [$family, ] = SceneryFootprintDeriver::splitPiece($name);

        $footprint = $this->footprints->catalogue()[$family] ?? null;

        if ($footprint === null) {
            return null;
        }

        $coordsIds = $this->objectCellsAt($coordsId, $name);

        if ($coordsIds === []) {
            return null;
        }

        $in = implode(',', array_map('intval', $coordsIds));

        $present = [];
        $anchorPos = null;

        foreach ($this->conn->fetchAllAssociative(
            "SELECT f.name, c.x, c.y FROM map_foregrounds f
               JOIN coords c ON c.id = f.coords_id
              WHERE f.coords_id IN ({$in})"
        ) as $row) {
            [$rowFamily, $piece] = SceneryFootprintDeriver::splitPiece((string) $row['name']);

            if ($rowFamily !== $family) {
                continue;
            }

            $present[$piece] = true;

            /* The lowest PLACED piece is the reference for locating the missing ones. */
            if ($anchorPos === null || $piece < $anchorPos['piece']) {
                $anchorPos = ['piece' => $piece, 'x' => (int) $row['x'], 'y' => (int) $row['y']];
            }
        }

        $missing = [];

        if ($anchorPos !== null) {
            foreach ($footprint->cellsAround($anchorPos['piece'], $anchorPos['x'], $anchorPos['y']) as $piece => $position) {
                if (!isset($present[$piece])) {
                    $missing[$piece] = $position;
                }
            }
        }

        ksort($present);
        ksort($missing);

        return [
            'family'     => $family,
            'footprint'  => $footprint,
            'present'    => array_keys($present),
            'missing'    => $missing,
            'coords_ids' => $coordsIds,
        ];
    }

    /**
     * Put back the missing pieces of a truncated object.
     *
     * @return int pieces placed
     */
    public function complete(int $coordsId, string $name): int
    {
        $state = $this->inspect($coordsId, $name);

        if ($state === null || $state['missing'] === []) {
            return 0;
        }

        $origin = $this->conn->fetchAssociative(
            'SELECT z, plan FROM coords WHERE id = ?',
            [$coordsId]
        );

        if ($origin === false) {
            return 0;
        }

        $placed = 0;

        foreach ($state['missing'] as $piece => [$x, $y]) {
            $pieceCoordsId = (int) \Classes\View::get_coords_id((object) [
                'x' => $x, 'y' => $y, 'z' => (int) $origin['z'], 'plan' => $origin['plan'],
            ]);

            $this->conn->executeStatement(
                'INSERT INTO map_foregrounds (name, coords_id) VALUES (?, ?)',
                [$this->pieceName($name, $state['family'], $piece), $pieceCoordsId]
            );

            $placed++;
        }

        return $placed;
    }

    /**
     * Piece name in the same convention as the picked one — `-NN`, `_NN` or a
     * bare digit all coexist on disk, so the picked name's shape is copied.
     */
    private function pieceName(string $pickedName, string $family, int $piece): string
    {
        $separator = substr($pickedName, strlen($family), 1);

        if ($separator === '-' || $separator === '_') {
            return $family . $separator . sprintf('%02d', $piece);
        }

        return $family . $piece;
    }
}
