<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * The `players` row every posed object shares, and the cells that follow it.
 *
 * Three callers wrote this INSERT by hand: the building pose, the scenery pose
 * and the resources migration. Only three things differ between them — the
 * entity type, the sprite and the label — so the row belongs here, together
 * with the id allocation that has to agree with it.
 *
 * Ids come from the CALLER's connection, never from the global `db()`: a pose
 * running inside an open transaction must see the rows it has just written,
 * or every entity of a batch is handed the same id.
 */
final class EntityPlacementService
{
    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * Place one entity and lay the cells its cut-out asks for.
     *
     * @return int the new entity's players.id, inside its type's range
     */
    public function create(
        string $playerType,
        string $race,
        int $coordsId,
        string $name,
        string $avatar,
        ?EntityTypeFootprintService $footprints = null
    ): int {
        $ids = $this->createMany(
            $playerType,
            [['race' => $race, 'coordsId' => $coordsId, 'name' => $name, 'avatar' => $avatar]],
            $footprints
        );

        return $ids[0];
    }

    /**
     * Place a whole set of entities of one type, allocating ids once.
     *
     * The single-row path goes through here too: an import poses thousands of
     * objects, and one MAX per object is the difference between a second and
     * a minute.
     *
     * @param list<array{race: string, coordsId: int, name: string, avatar: string}> $objects
     *
     * @return list<int> the new players.id values, in the order given
     */
    public function createMany(
        string $playerType,
        array $objects,
        ?EntityTypeFootprintService $footprints = null
    ): array {
        if ($objects === []) {
            return [];
        }

        $id = $this->nextId($playerType);
        $displayId = $this->nextDisplayId($playerType);
        $now = time();
        $ids = [];

        foreach ($objects as $object) {
            $this->conn->executeStatement(
                'INSERT INTO players
                    (id, player_type, display_id, name, race, avatar, portrait,
                     coords_id, slot, nextTurnTime, registerTime, text)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
                [
                    $id,
                    $playerType,
                    $displayId,
                    $object['name'],
                    $object['race'],
                    $object['avatar'],
                    $object['avatar'],
                    $object['coordsId'],
                    EntityLocationService::SLOT_INSTALLED,
                    $now,
                    '',
                ]
            );

            $ids[] = $id;
            ++$id;
            ++$displayId;
        }

        /* Cells last, and one entity at a time: syncCells reads the row back
         * to find the origin and the cut-out it has to lay around it. */
        $cells = new EntityCellService($this->conn);
        foreach ($ids as $entityId) {
            $cells->syncCells($entityId, $footprints);
        }

        return $ids;
    }

    /**
     * Ranges as `config/constants.php` defines them.
     *
     * Mirrored because that file is loaded by the web entry point only, and a
     * pose also runs from the console; the constant wins whenever it is there,
     * so the two cannot drift apart in a running site.
     *
     * @var array<string, array{start: int, end: int}>
     */
    private const ID_RANGES = [
        'building' => ['start' => 20000000, 'end' => 29999999],
        'unique'   => ['start' => 30000000, 'end' => 39999999],
        'scenery'  => ['start' => 40000000, 'end' => 49999999],
        'resource' => ['start' => 50000000, 'end' => 59999999],
        'plant'    => ['start' => 60000000, 'end' => 69999999],
        'item'     => ['start' => 70000000, 'end' => 79999999],
    ];

    /** First free id in the type's range, seen from this connection. */
    private function nextId(string $playerType): int
    {
        $ranges = defined('ENTITY_ID_RANGES') ? ENTITY_ID_RANGES : self::ID_RANGES;

        $range = $ranges[$playerType]
            ?? throw new \InvalidArgumentException("Type d'entité inconnu : '{$playerType}'.");

        $max = $this->conn->fetchOne(
            'SELECT MAX(id) FROM players WHERE id BETWEEN ? AND ?',
            [$range['start'], $range['end']]
        );

        return $max === null || $max === false ? (int) $range['start'] : (int) $max + 1;
    }

    /** Display ids stay sequential within a type, holes included. */
    private function nextDisplayId(string $playerType): int
    {
        $max = $this->conn->fetchOne(
            'SELECT MAX(display_id) FROM players WHERE player_type = ?',
            [$playerType]
        );

        return $max === null || $max === false ? 1 : (int) $max + 1;
    }
}
