<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * Whether `players_items_instances` can go, and what still holds it.
 *
 * Twin of {@see \App\Service\Map\MapResourcesRetirement}, for the table the
 * containment work has finished detaching: no reader, no writer, the holder
 * lives on the entity.
 *
 * Computed rather than remembered: a "drop this later" note dies in a
 * conversation. It shows on the dashboard while the table exists and vanishes
 * on its own the day it is dropped.
 *
 * Empty is not droppable: the code that stopped reading it must be deployed
 * everywhere first (migrations-before-code invariant), so the screen says
 * "ready", never "do it now".
 */
final class OwnershipLinkRetirement
{
    private const TABLE = 'players_items_instances';

    private ?Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn;
    }

    private function conn(): Connection
    {
        return $this->conn ??= EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * @return array{present: bool, rows: int, disagreeing: int, droppable: bool, blockers: list<string>}
     */
    public function status(): array
    {
        if (!$this->tableExists()) {
            return ['present' => false, 'rows' => 0, 'disagreeing' => 0, 'droppable' => false, 'blockers' => []];
        }

        $rows = (int) $this->conn()->fetchOne('SELECT COUNT(*) FROM ' . self::TABLE);

        /* A leftover row is harmless in itself — nothing reads it. It matters
         * only when it CONTRADICTS the entity, because that means something
         * still writes the old half and the two halves have drifted. */
        $disagreeing = (int) $this->conn()->fetchOne(
            "SELECT COUNT(*)
               FROM " . self::TABLE . " l
               JOIN item_instances i ON i.id = l.instance_id
               JOIN players e ON e.id = i.entity_id
              WHERE e.holder_id <> l.player_id
                 OR e.slot <> CASE
                                  WHEN l.equiped <> '' THEN l.equiped
                                  WHEN l.location <> 'inventory' THEN l.location
                                  ELSE ''
                              END"
        );

        $blockers = [];

        if ($disagreeing > 0) {
            $blockers[] = $disagreeing . ' ' . ($disagreeing > 1 ? 'lignes contredisent' : 'ligne contredit')
                . " l'entité : quelque chose écrit encore l'ancienne moitié";
        }

        return [
            'present'     => true,
            'rows'        => $rows,
            'disagreeing' => $disagreeing,
            'droppable'   => $blockers === [],
            'blockers'    => $blockers,
        ];
    }

    private function tableExists(): bool
    {
        return $this->conn()->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = ?',
            [self::TABLE]
        ) > 0;
    }
}
