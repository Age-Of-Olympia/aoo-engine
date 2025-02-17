<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\Race;
use Db;

class PlayerService
{
    private Db $db;

    private function getPlayerField(int $playerId, string $field): mixed
    {
        $fields = $this->getPlayerFields($playerId, [$field]);
        return $fields[$field] ?? null;
    }

    public function __construct()
    {
        $this->db = new Db();
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
}
