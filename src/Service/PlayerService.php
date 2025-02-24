<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\Race;
use Db;

class PlayerService
{
    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    private function getPlayerField(int $playerId, string $field): mixed
    {
        $fields = $this->getPlayerFields($playerId, [$field]);
        return $fields[$field] ?? null;
    }

    public function getPlainEmail(int $playerId): ?string
    {
        return $this->getPlayerField($playerId, 'plain_mail');
    }

    public function getEmailBonus(int $playerId): bool
    {
        return $this->getPlayerField($playerId, 'email_bonus') ?? false;
    }

    public function getPlayerFields(int $playerId, array $fields): array
    {
        if (empty($fields)) {
            return [];
        }

        $sql = "SELECT " . implode(', ', $fields) . " FROM players WHERE id = ?";
        $res = $this->db->exe($sql, array($playerId));

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
}
