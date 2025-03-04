<?php

namespace App\Service;

use Db;

class PlayerService
{
    private Db $db;
    private int $playerId;

    public function __construct(int $playerId)
    {
        $this->playerId = $playerId;
        $this->db = new Db();
    }

    public function getPlayerFields(array $fields): array
    {
        if (empty($fields)) {
            return [];
        }

        $sql = "SELECT " . implode(', ', $fields) . " FROM players WHERE id = ?";
        $res = $this->db->exe($sql, array($this->playerId));

        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_object();
            $result = [];
            foreach ($fields as $field) {
                $result[$field] = $row->$field ?? null;
            }
            return $result;
        }

        return array_fill_keys($fields, null);
    }

    /**
     * Helper function to calculate if a login time is considered inactive
     * @param int $lastLoginTime The last login time to check
     * @return bool True if inactive, false otherwise
     */
    public function isInactive(int $lastLoginTime): bool
    {
        $current_time = time();
        $inactive_threshold = $current_time - (INACTIVE_TIME);
        return $lastLoginTime < $inactive_threshold;
    }

    public function updateLastActionTime(): void {
        $sql = '
            UPDATE
            players
            SET
            lastActionTime = '. time() .'
            WHERE
            id = ?
            ';

        $this->db->exe($sql, $this->playerId);
    }
}
