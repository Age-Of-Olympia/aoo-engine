<?php

namespace App\Service\Map;

use App\Entity\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * Place and remove a multi-cell scenery object in one gesture.
 *
 * Placement drops the whole figure with the picked piece on the clicked tile
 * AND makes it an entity holding its cells; removal takes both away from any
 * of its cells. Without the entity a declared cut-out would be a drawing:
 * the roles an animator marks live on the cells, and nothing would read them.
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
     * Put a whole object on the map: its pieces, and the entity holding them.
     *
     * The entity is what carries the cut-out's roles, so a figure placed
     * without one blocks nothing however it is marked in the editor.
     *
     * @return int the number of pieces placed; 0 when the family has no
     *             known cut-out, which the caller falls back on
     */
    public function placeObject(string $pickedName, int $x, int $y, int $z, string $plan): int
    {
        $cells = $this->cellsToPlace($pickedName, $x, $y);

        if ($cells === []) {
            return 0;
        }

        [$family, ] = SceneryFootprintDeriver::splitPiece($pickedName);
        $anchorCoordsId = null;

        foreach ($cells as $pieceName => [$px, $py]) {
            $coordsId = (int) \Classes\View::get_coords_id(
                (object) ['x' => $px, 'y' => $py, 'z' => $z, 'plan' => $plan]
            );

            $this->conn->executeStatement(
                'INSERT INTO map_foregrounds (name, coords_id) VALUES (?, ?)',
                [$pieceName, $coordsId]
            );

            /* The entity stands on its first piece, the offsets' origin. */
            $anchorCoordsId ??= $coordsId;
        }

        $this->makeEntity($family, (int) $anchorCoordsId, (string) array_key_first($cells));

        return count($cells);
    }

    /**
     * Remove the entity holding a cell, if one does.
     *
     * @return int entities removed
     */
    public function removeEntitiesOn(array $coordsIds): int
    {
        if ($coordsIds === []) {
            return 0;
        }

        $in = implode(',', array_map('intval', $coordsIds));

        $ids = $this->conn->fetchFirstColumn(
            "SELECT DISTINCT p.id FROM players p
               JOIN entity_cells ec ON ec.player_id = p.id
              WHERE ec.coords_id IN ({$in}) AND p.player_type = 'scenery'"
        );

        foreach ($ids as $id) {
            \App\Service\BuildingService::deleteEntityRows($this->conn, (int) $id);
        }

        return count($ids);
    }

    /**
     * Turn scenery placed WITHOUT an entity into one, and say how many.
     *
     * The L4 conversion ran once. Anything laid down since — through the
     * editor, before placement learned to make entities — is pieces on the
     * map that no entity holds, so its cut-out's roles are read by nobody.
     *
     * @return int objects converted
     */
    public function convertOrphans(): int
    {
        $converted = 0;

        foreach ((new SceneryFootprintDeriver($this->conn))->objects() as $object) {
            $coordsIds = array_column($object['cells'], 'coords_id');
            $in = implode(',', array_map('intval', $coordsIds));

            $held = (bool) $this->conn->fetchOne(
                "SELECT 1 FROM entity_cells ec
                   JOIN players p ON p.id = ec.player_id
                  WHERE ec.coords_id IN ({$in}) AND p.player_type = 'scenery' LIMIT 1"
            );

            if ($held) {
                continue;
            }

            $cells = $object['cells'];
            usort($cells, static fn(array $a, array $b): int => $a['piece'] <=> $b['piece']);

            $this->makeEntity(
                $object['family'],
                (int) $cells[0]['coords_id'],
                (string) $cells[0]['name']
            );

            $converted++;
        }

        return $converted;
    }

    /**
     * The type a scenery family stands for, created on first need.
     *
     * @return bool true when it had to be created
     *
     * Without it the entity's race names nothing, and a cell whose role is
     * `part` — which defers to the type — blocks by default. A decor placed
     * through the editor would turn solid all over instead of only where an
     * animator marked it.
     *
     * Scenery is passable and screens nothing until a cell says otherwise;
     * `block` cells are what make it solid, and the catalogue page refines
     * the rest.
     */
    public function ensureType(string $family): bool
    {
        $created = (int) $this->conn->executeStatement(
            "INSERT IGNORE INTO races
                (code, name, label, description, playable, hidden, kind, structure_nature,
                 bleeds, wound_color, blocks_passage, blocks_projectiles, bgColor, color,
                 faction, plan, pv)
             VALUES (?, ?, ?, '', 0, 1, 'structure', 'edifice', '', '#cd7f32', 0, 1, '#6b8f5a', 'black', '', '', 10)",
            [strtoupper($family), $family, ucfirst(str_replace('_', ' ', $family))]
        );

        \App\Service\RaceService::clearCache();

        return $created > 0;
    }

    /**
     * What a family's type says about its blocking cells, or null when the
     * family has no type yet — in which case nothing it is marked with works.
     *
     * @return array{blocks_passage: bool, blocks_projectiles: bool}|null
     */
    public function typeSettings(string $family): ?array
    {
        $row = $this->conn->fetchAssociative(
            'SELECT blocks_passage, blocks_projectiles FROM races WHERE name = ?',
            [$family]
        );

        return $row === false ? null : [
            'blocks_passage'    => (bool) $row['blocks_passage'],
            'blocks_projectiles' => (bool) $row['blocks_projectiles'],
        ];
    }

    /** Set the two dials a marked cell defers to. */
    public function setTypeSettings(string $family, bool $blocksPassage, bool $blocksProjectiles): void
    {
        $this->ensureType($family);

        $this->conn->executeStatement(
            'UPDATE races SET blocks_passage = ?, blocks_projectiles = ? WHERE name = ?',
            [(int) $blocksPassage, (int) $blocksProjectiles, $family]
        );

        \App\Service\RaceService::clearCache();
    }

    /**
     * The next free id in the scenery range.
     *
     * Not `getNextEntityId()`: that global lives in `config/functions.php`,
     * loaded by the web entry point only, and this service also runs from the
     * console. The range is read from the constant when it is there, so the
     * two never drift apart.
     */
    private function nextSceneryId(): int
    {
        $range = defined('ENTITY_ID_RANGES')
            ? ENTITY_ID_RANGES['scenery']
            : ['start' => 40000000, 'end' => 49999999];

        $max = (int) $this->conn->fetchOne(
            'SELECT COALESCE(MAX(id), 0) FROM players WHERE id BETWEEN ? AND ?',
            [$range['start'], $range['end']]
        );

        return $max === 0 ? (int) $range['start'] : $max + 1;
    }

    /** The scenery entity a placed figure belongs to, cells included. */
    private function makeEntity(string $family, int $anchorCoordsId, string $anchorPieceName): void
    {
        $this->ensureType($family);

        $id = $this->nextSceneryId();

        $this->conn->executeStatement(
            "INSERT INTO players
                (id, player_type, display_id, name, race, avatar, portrait,
                 coords_id, nextTurnTime, registerTime, text)
             VALUES (?, 'scenery', ?, ?, ?, ?, ?, ?, 0, ?, '')",
            [
                $id,
                (int) $this->conn->fetchOne(
                    "SELECT COALESCE(MAX(display_id), 0) + 1 FROM players WHERE player_type = 'scenery'"
                ),
                ucfirst(str_replace('_', ' ', $family)),
                $family,
                'img/foregrounds/' . $anchorPieceName . '.png',
                'img/foregrounds/' . $anchorPieceName . '.png',
                $anchorCoordsId,
                time(),
            ]
        );

        (new EntityCellService($this->conn))->syncCells($id, $this->footprints);
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

        $group = TouchingCells::groupAround($byKey, $start, SceneryFootprintDeriver::distinctPieces());

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
