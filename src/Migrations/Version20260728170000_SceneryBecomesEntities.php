<?php

declare(strict_types=1);

namespace App\Migrations;

use App\Service\Map\SceneryFootprintDeriver;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Scenery becomes entities: one `players` row per OBJECT, one `entity_cells`
 * row per piece.
 *
 * A fourteen-piece fort was fourteen unrelated rows in `map_foregrounds`;
 * it becomes one entity holding fourteen cells. Grouping stops where a piece
 * index repeats, so two objects standing side by side stay two.
 *
 * A `forbidden` trigger that only existed to fence off a decor is removed:
 * the object blocks on its own now, so the fence would outlive what it
 * fenced — and keep barring the way once the decor opens or is taken away.
 *
 * `map_foregrounds` is LEFT UNTOUCHED. The renderer still reads it, and the
 * new entities are excluded from the board and from the landing and building
 * questions, so this migration changes nothing on screen. The next lot flips
 * the renderer over to the entities and drops the source.
 *
 * Runs in PHP rather than SQL: grouping touching cells while refusing a
 * repeated piece index is a graph walk, and it already exists in
 * `SceneryFootprintDeriver`. Reads only the database — no `img/`, no
 * `datas/`, both absent from the deploy checkout.
 */
final class Version20260728170000_SceneryBecomesEntities extends AbstractMigration
{
    private const ID_START = 40000000;

    /** Scenery is decoration: passable, and it does not stop arrows. */
    private const BLOCKS_PASSAGE = 0;
    private const BLOCKS_PROJECTILES = 0;

    private const DEFAULT_PV = 10;
    private const DEFAULT_BG_COLOR = '#6b8f5a';

    /**
     * A `forbidden` trigger fencing off a decor is a workaround for scenery
     * that could not block on its own. The rule moves onto the object, and
     * the trigger goes: leaving it would keep barring the way once the decor
     * opens or is removed, which is the fence outliving what it fenced.
     *
     * Only the ones sitting on a converted cell are touched. On the
     * production copy that is 438 of 12 187 — the other 10 237 mark bare
     * ground out of bounds, which is the trigger's real job.
     */
    private const FENCED_OFF = 'forbidden';

    public function getDescription(): string
    {
        return 'Scenery objects become `scenery` entities with their emprise, leaving map_foregrounds in place';
    }

    public function up(Schema $schema): void
    {
        $objects = (new SceneryFootprintDeriver($this->connection))->objects();

        if ($objects === []) {
            return;
        }

        $taken = $this->cellsAlreadyConverted();
        $nextId = $this->nextId();
        $nextDisplayId = (int) $this->connection->fetchOne(
            "SELECT COALESCE(MAX(display_id), 0) + 1 FROM players WHERE player_type = 'scenery'"
        );

        $seededFamilies = [];

        foreach ($objects as $object) {
            $family = $object['family'];
            $cells = $object['cells'];

            usort($cells, static fn(array $a, array $b): int => $a['piece'] <=> $b['piece']);

            /* Re-running must not double an object: one converted cell is enough
             * to know this one is done. */
            foreach ($cells as $cell) {
                if (isset($taken[$cell['coords_id']])) {
                    continue 2;
                }
            }

            if (!isset($seededFamilies[$family])) {
                $this->seedType($family);
                $seededFamilies[$family] = true;
            }

            $anchor = $cells[0];

            $this->connection->executeStatement(
                "INSERT INTO players
                    (id, player_type, display_id, name, race, avatar, portrait,
                     coords_id, nextTurnTime, registerTime, text)
                 VALUES (?, 'scenery', ?, ?, ?, ?, ?, ?, 0, ?, '')",
                [
                    $nextId,
                    $nextDisplayId,
                    self::labelOf($family),
                    $family,
                    self::imageOf($anchor['name']),
                    self::imageOf($anchor['name']),
                    $anchor['coords_id'],
                    time(),
                ]
            );

            foreach ($cells as $cell) {
                $this->connection->executeStatement(
                    'INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $nextId,
                        $cell['coords_id'],
                        $cell['plan'],
                        $cell['z'],
                        $cell['x'],
                        $cell['y'],
                        $cell['piece'],
                        $this->roleAt((int) $cell['coords_id']),
                    ]
                );

                $taken[$cell['coords_id']] = true;
            }

            $nextId++;
            $nextDisplayId++;
        }

        $this->releaseFences();
    }

    /**
     * Drop the fences the objects now carry themselves. Derived from the
     * cells rather than remembered, so a re-run finds nothing left to do.
     */
    private function releaseFences(): void
    {
        $this->connection->executeStatement(
            "DELETE t FROM map_triggers t
               JOIN entity_cells ec ON ec.coords_id = t.coords_id
               JOIN players p ON p.id = ec.player_id
              WHERE t.name = ? AND ec.role = 'block' AND p.player_type = 'scenery'",
            [self::FENCED_OFF]
        );
    }

    /**
     * Leaves `map_foregrounds` alone on the way back too: it was never
     * emptied, so removing the entities is enough to undo this.
     */
    public function down(Schema $schema): void
    {
        /* The fences come back before the cells that describe where they
         * stood are removed. */
        $this->addSql(
            "INSERT INTO map_triggers (name, coords_id, params)
             SELECT '" . self::FENCED_OFF . "', ec.coords_id, ''
               FROM entity_cells ec
               JOIN players p ON p.id = ec.player_id
              WHERE ec.role = 'block' AND p.player_type = 'scenery'"
        );

        $this->addSql(
            "DELETE ec FROM entity_cells ec
               JOIN players p ON p.id = ec.player_id
              WHERE p.player_type = 'scenery'"
        );
        $this->addSql("DELETE FROM players WHERE player_type = 'scenery'");
    }

    /** @return array<int, true> cells already held by a scenery entity */
    private function cellsAlreadyConverted(): array
    {
        $taken = [];

        foreach ($this->connection->fetchFirstColumn(
            "SELECT ec.coords_id FROM entity_cells ec
               JOIN players p ON p.id = ec.player_id
              WHERE p.player_type = 'scenery'"
        ) as $coordsId) {
            $taken[(int) $coordsId] = true;
        }

        return $taken;
    }

    private function nextId(): int
    {
        $max = (int) $this->connection->fetchOne(
            'SELECT COALESCE(MAX(id), 0) FROM players WHERE id BETWEEN ? AND ?',
            [self::ID_START, self::ID_START + 9999999]
        );

        return $max === 0 ? self::ID_START : $max + 1;
    }

    /** Fenced off means the object itself blocks; otherwise it is plain scenery. */
    private function roleAt(int $coordsId): string
    {
        $fencedOff = (bool) $this->connection->fetchOne(
            'SELECT 1 FROM map_triggers WHERE coords_id = ? AND name = ? LIMIT 1',
            [$coordsId, self::FENCED_OFF]
        );

        return $fencedOff ? 'block' : 'cover';
    }

    /**
     * The type a scenery family stands for, created once and left to the admin
     * to refine. `races` carries no image: the entity's own `avatar` does.
     */
    private function seedType(string $family): void
    {
        $this->connection->executeStatement(
            "INSERT IGNORE INTO races
                (code, name, label, description, playable, hidden, kind, structure_nature,
                 bleeds, wound_color, blocks_passage, blocks_projectiles, bgColor, color,
                 faction, plan, pv)
             VALUES (?, ?, ?, '', 0, 1, 'structure', 'edifice', '', '#cd7f32', ?, ?, ?, 'black', '', '', ?)",
            [
                strtoupper($family),
                $family,
                self::labelOf($family),
                self::BLOCKS_PASSAGE,
                self::BLOCKS_PROJECTILES,
                self::DEFAULT_BG_COLOR,
                self::DEFAULT_PV,
            ]
        );
    }

    private static function labelOf(string $family): string
    {
        return ucfirst(str_replace('_', ' ', $family));
    }

    private static function imageOf(string $pieceName): string
    {
        return 'img/foregrounds/' . $pieceName . '.png';
    }
}
