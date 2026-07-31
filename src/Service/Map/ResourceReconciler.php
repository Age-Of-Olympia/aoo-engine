<?php

namespace App\Service\Map;

use App\Entity\EntityManagerFactory;
use App\Service\BuildingService;
use Classes\View;
use Doctrine\DBAL\Connection;

/**
 * Makes a plan's resources match what a bundle says they should be.
 *
 * A plan import used to be a purge and a refill: `DELETE` every authored row
 * of the plan, `INSERT` the payload back. That works for rows, and not for
 * entities — recreating them hands out 26 656 fresh ids and drops what the
 * world has done to them since.
 *
 * So this compares instead. The identity of a resource is where it stands and
 * what it is — (z, x, y, type) within the plan. What the payload adds is
 * created, what it no longer names is removed, and what both agree on is left
 * strictly alone, ids and state included: a tree the players have exhausted
 * stays exhausted across a re-import, and keeps its regrow.
 *
 * The payload's `damages` is therefore authoring intent, not a verdict: it
 * seeds a resource being created, and says nothing about one already standing.
 */
final class ResourceReconciler
{
    private Connection $conn;

    /** La famille réconciliée : `resource` par défaut, `plant` pour les fleurs. */
    private string $family;

    /** Où vivent les sprites de cette famille. */
    private string $spriteDir;

    public function __construct(
        ?Connection $conn = null,
        string $family = 'resource',
        string $spriteDir = 'img/walls/'
    ) {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
        $this->family = $family;
        $this->spriteDir = $spriteDir;
    }

    /** Le réconciliateur des plantes — même geste, autre famille. */
    public static function forPlants(?Connection $conn = null): self
    {
        return new self($conn, 'plant', 'img/plants/');
    }

    /**
     * @param list<array{name: string, x: int, y: int, z: int, damages?: int}> $wanted
     *        the resources the bundle draws on this plan
     *
     * @return array{created: int, removed: int, kept: int, unknown: list<string>}
     *         unknown holds the type names absent from the `races` catalog,
     *         which cannot be posed and are reported rather than guessed at
     */
    public function reconcile(string $plan, array $wanted): array
    {
        $current = $this->current($plan);
        $labels = $this->labels($wanted);

        $seen = [];
        $create = [];
        $unknown = [];

        foreach ($wanted as $row) {
            $key = $this->key((int) $row['z'], (int) $row['x'], (int) $row['y'], $row['name']);

            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            if (isset($current[$key])) {
                continue;
            }

            if (!isset($labels[$row['name']])) {
                $unknown[$row['name']] = true;
                continue;
            }

            $create[] = $row;
        }

        /* Anything the bundle no longer draws goes, and so does a second entity
         * sharing an identity with the one we keep — the oldest stands. */
        $remove = [];
        $kept = 0;

        foreach ($current as $key => $ids) {
            if (isset($seen[$key])) {
                ++$kept;
                $remove = array_merge($remove, array_slice($ids, 1));
                continue;
            }

            $remove = array_merge($remove, $ids);
        }

        (new ResourceObjectService($this->conn))->removeEntities($remove);
        $created = $this->create($plan, $create, $labels);

        return [
            'created' => count($created),
            'removed' => count($remove),
            'kept'    => $kept,
            'unknown' => array_keys($unknown),
        ];
    }

    /**
     * The plan's resources in payload shape — the inverse of reconcile().
     *
     * It lives next to the writer on purpose: both directions have to agree on
     * how `damages` maps to the state satellite, or a bundle exported from a
     * world and imported back would not describe the same map.
     *
     * @return list<array{name: string, x: int, y: int, z: int, damages: int}>
     */
    public function asPayloadRows(string $plan): array
    {
        $rows = $this->conn->fetchAllAssociative(
            "SELECT p.race AS name, c.x, c.y, c.z,
                    CASE WHEN r.exhausted_at IS NULL THEN -1 ELSE -2 END AS damages
               FROM players p
               JOIN coords c ON c.id = p.coords_id
          LEFT JOIN resources r ON r.player_id = p.id
              WHERE p.player_type = ? AND c.plan = ?
           ORDER BY c.z, c.y, c.x, p.id",
            [$this->family, $plan]
        );

        return array_map(
            static fn(array $row): array => [
                'name'    => (string) $row['name'],
                'x'       => (int) $row['x'],
                'y'       => (int) $row['y'],
                'z'       => (int) $row['z'],
                'damages' => (int) $row['damages'],
            ],
            $rows
        );
    }

    /**
     * The resources standing on the plan, by identity.
     *
     * Read from `players.coords_id`, the entity's own origin, rather than from
     * its cells: a drifted cell must not make a resource look absent and get
     * posed a second time.
     *
     * @return array<string, list<int>> identity => entity ids, oldest first
     */
    private function current(string $plan): array
    {
        $rows = $this->conn->fetchAllAssociative(
            "SELECT p.id, p.race, c.z, c.x, c.y
               FROM players p
               JOIN coords c ON c.id = p.coords_id
              WHERE p.player_type = ? AND c.plan = ?
              ORDER BY p.id",
            [$this->family, $plan]
        );

        $current = [];
        foreach ($rows as $row) {
            $key = $this->key((int) $row['z'], (int) $row['x'], (int) $row['y'], (string) $row['race']);
            $current[$key][] = (int) $row['id'];
        }

        return $current;
    }

    /**
     * Labels of the types the payload names, from the single catalog.
     *
     * @param list<array{name: string, x: int, y: int, z: int, damages?: int}> $wanted
     *
     * @return array<string, string> type name => display label
     */
    private function labels(array $wanted): array
    {
        $names = array_values(array_unique(array_column($wanted, 'name')));

        if ($names === []) {
            return [];
        }

        $rows = $this->conn->fetchAllAssociative(
            'SELECT name, label FROM races WHERE name IN (' . implode(',', array_fill(0, count($names), '?')) . ')',
            $names
        );

        $labels = [];
        foreach ($rows as $row) {
            $name = (string) $row['name'];
            $labels[$name] = ((string) $row['label']) !== '' ? (string) $row['label'] : $name;
        }

        return $labels;
    }

    /**
     * @param list<array{name: string, x: int, y: int, z: int, damages?: int}> $create
     * @param array<string, string>                                            $labels
     *
     * @return list<int> the ids posed
     */
    private function create(string $plan, array $create, array $labels): array
    {
        if ($create === []) {
            return [];
        }

        $objects = [];
        foreach ($create as $row) {
            $coordsId = (int) View::get_coords_id(
                (object) ['x' => (int) $row['x'], 'y' => (int) $row['y'], 'z' => (int) $row['z'], 'plan' => $plan]
            );

            $objects[] = [
                'race'     => $row['name'],
                'coordsId' => $coordsId,
                'name'     => $labels[$row['name']],
                'avatar'   => $this->spriteDir . $row['name'] . '.png',
            ];
        }

        $ids = (new EntityPlacementService($this->conn))->createMany($this->family, $objects);

        /* An id can be recycled from a resource removed earlier in this very
         * import; its cached identity would outlive it. */
        foreach ($ids as $id) {
            BuildingService::purgeEntityCaches($id);
        }

        $exhausted = [];
        foreach ($create as $index => $row) {
            if ((int) ($row['damages'] ?? -1) === -2) {
                $exhausted[] = $ids[$index];
            }
        }

        (new ResourceStateService($this->conn))->exhaust($exhausted);

        return $ids;
    }

    private function key(int $z, int $x, int $y, string $type): string
    {
        return $z . '|' . $x . '|' . $y . '|' . $type;
    }
}
