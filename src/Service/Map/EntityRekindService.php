<?php

namespace App\Service\Map;

use App\Entity\BuildingDetails;
use App\Entity\Race;
use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * Brings placed entities back in line with the family of their type.
 *
 * `players.player_type` is copied from `races.type_kind` at placement, so a
 * type moved to another family in admin left every exemplar already on the
 * board in the old one. This service rewrites the discriminator and the
 * family's own satellite rows; it is the only re-kind path, used by the type
 * save and by the admin repair page.
 *
 * What stays: the id (every foreign key points at it), owner, faction, life,
 * inventory and anything held inside. Only the rows that mean nothing for the
 * new family go: `buildings` and `construction_sites` when leaving building,
 * `resources` when leaving resource.
 */
final class EntityRekindService
{
    /** Families an exemplar can be placed as; a character type cannot be re-kinded onto the board. */
    public const FAMILIES = [
        Race::FAMILY_BUILDING,
        Race::FAMILY_SCENERY,
        Race::FAMILY_RESOURCE,
        Race::FAMILY_PLANT,
        Race::FAMILY_ROUTE,
    ];

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * Placed entities whose family differs from their type's.
     *
     * @return list<array{id: int, display_id: int, race: string, label: string, player_type: string,
     *                    type_kind: string, plan: ?string, z: ?int, x: ?int, y: ?int}>
     */
    public function mismatches(?string $typeName = null): array
    {
        $in = $this->familyList();
        $rows = $this->conn->fetchAllAssociative(
            "SELECT p.id, p.display_id, p.race, r.label, p.player_type, r.type_kind, c.plan, c.z, c.x, c.y
               FROM players p
               JOIN races r ON CONVERT(r.name USING utf8mb4) = CONVERT(p.race USING utf8mb4)
               LEFT JOIN coords c ON c.id = p.coords_id
              WHERE p.player_type IN ({$in}) AND r.type_kind IN ({$in})
                AND p.player_type <> r.type_kind"
                . ($typeName !== null ? ' AND p.race = ?' : '')
                . ' ORDER BY r.label, c.plan, c.z, c.x, c.y, p.id',
            $typeName !== null ? [$typeName] : []
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'display_id' => (int) $row['display_id'],
            'race' => (string) $row['race'],
            'label' => (string) $row['label'],
            'player_type' => (string) $row['player_type'],
            'type_kind' => (string) $row['type_kind'],
            'plan' => $row['plan'] !== null ? (string) $row['plan'] : null,
            'z' => $row['z'] !== null ? (int) $row['z'] : null,
            'x' => $row['x'] !== null ? (int) $row['x'] : null,
            'y' => $row['y'] !== null ? (int) $row['y'] : null,
        ], $rows);
    }

    /** Re-kind every exemplar of a type that disagrees with it. */
    public function rekindType(string $typeName): int
    {
        return $this->rekind(array_column($this->mismatches($typeName), 'id'));
    }

    /**
     * Give each entity its type's family, in one transaction.
     *
     * Ids already in line, unknown, or whose type is not a placeable family
     * are skipped, so the call is idempotent.
     *
     * @param list<int> $entityIds
     * @return int entities re-kinded
     */
    public function rekind(array $entityIds): int
    {
        if ($entityIds === []) {
            return 0;
        }

        $in = $this->familyList();
        $marks = implode(',', array_fill(0, count($entityIds), '?'));
        $changed = [];

        // ponytail: one syncCells per entity (~ms each); batch it if a type with thousands of exemplars is re-kinded
        $this->conn->transactional(function (Connection $conn) use ($in, $marks, $entityIds, &$changed): void {
            $rows = $conn->fetchAllAssociative(
                "SELECT p.id, p.player_type, r.type_kind, r.default_dialog, p.coords_id
                   FROM players p
                   JOIN races r ON CONVERT(r.name USING utf8mb4) = CONVERT(p.race USING utf8mb4)
                  WHERE p.id IN ({$marks}) AND p.player_type IN ({$in}) AND r.type_kind IN ({$in})
                    AND p.player_type <> r.type_kind
                  FOR UPDATE",
                array_map('intval', $entityIds)
            );

            $cells = new EntityCellService($conn);
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $from = (string) $row['player_type'];
                $to = (string) $row['type_kind'];

                // Display ids are numbered per family: take the next one in the new family.
                $conn->executeStatement(
                    'UPDATE players
                        SET player_type = ?,
                            display_id = (SELECT n FROM (SELECT COALESCE(MAX(display_id), 0) + 1 n
                                                           FROM players WHERE player_type = ?) t)
                      WHERE id = ?',
                    [$to, $to, $id]
                );

                if ($from === Race::FAMILY_BUILDING) {
                    $conn->executeStatement('DELETE FROM buildings WHERE player_id = ?', [$id]);
                    $conn->executeStatement('DELETE FROM construction_sites WHERE player_id = ?', [$id]);
                }
                if ($from === Race::FAMILY_RESOURCE) {
                    $conn->executeStatement('DELETE FROM resources WHERE player_id = ?', [$id]);
                }
                if ($to === Race::FAMILY_BUILDING) {
                    $conn->executeStatement(
                        'INSERT IGNORE INTO buildings (player_id, build_state, dialog) VALUES (?, ?, ?)',
                        [$id, BuildingDetails::STATE_BUILT, (string) $row['default_dialog']]
                    );
                }

                // The default cell role depends on the family.
                $cells->syncCells($id);
                $changed[$id] = (int) $row['coords_id'];
            }
        });

        foreach (array_chunk(array_filter($changed), 500) as $coordsIds) {
            BoardChanges::cellId(...$coordsIds);
        }

        return count($changed);
    }

    private function familyList(): string
    {
        return "'" . implode("','", self::FAMILIES) . "'";
    }
}
