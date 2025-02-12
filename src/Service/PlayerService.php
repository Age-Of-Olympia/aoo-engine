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
        $fieldsStr = implode(',', $fields);
        $sql = "SELECT $fieldsStr FROM players WHERE id = ?";
        $result = $this->db->exe($sql, [$playerId]);
        return $result->fetch_assoc() ?? [];
    }

    public function getTotalPlayersCount(): int
    {
        $sql = "SELECT COUNT(*) as count FROM players";
        $result = $this->db->exe($sql);
        return (int) $result->fetch_object()->count;
    }

    public function getActivePlayersCount(int $days = 7): int
    {
        $sql = "SELECT COUNT(*) as count FROM players WHERE lastLoginTime > DATE_SUB(NOW(), INTERVAL ? DAY)";
        $result = $this->db->exe($sql, [$days]);
        return (int) $result->fetch_object()->count; 
    }

    public function getNewPlayersCount(int $days = 7): int
    {
        $sql = "SELECT COUNT(*) as count FROM players WHERE registerTime > DATE_SUB(NOW(), INTERVAL ? DAY)";
        $result = $this->db->exe($sql, [$days]);
        return (int) $result->fetch_object()->count;
    }

    public function getNotificationUsersCount(): int
    {
        $sql = "SELECT COUNT(*) as count FROM players WHERE email_bonus = 1";
        $result = $this->db->exe($sql);
        return (int) $result->fetch_object()->count;
    }

    public function getPlainEmailCount(): int
    {
        $sql = "SELECT COUNT(*) as count FROM players WHERE plain_mail IS NOT NULL AND plain_mail != ''";
        $result = $this->db->exe($sql);
        return (int) $result->fetch_object()->count;
    }

    public function getAllPlayers(): array
    {
        $sql = "SELECT id, name, plain_mail, lastLoginTime, 
                       email_bonus, notify_season, notify_quest, 
                       notify_turn, notify_missive 
                FROM players 
                ORDER BY lastLoginTime DESC";
        $result = $this->db->exe($sql);
        $players = [];
        while ($player = $result->fetch_object()) {
            $players[] = $player;
        }
        return $players;
    }

    public function updatePlayerNotifications(int $playerId, array $notifications): bool
    {
        $sql = "UPDATE players SET 
                email_bonus = ?, 
                notify_season = ?,
                notify_quest = ?,
                notify_turn = ?,
                notify_missive = ?
                WHERE id = ?";
        
        $params = [
            $notifications['email_bonus'] ?? 0,
            $notifications['notify_season'] ?? 0,
            $notifications['notify_quest'] ?? 0,
            $notifications['notify_turn'] ?? 0,
            $notifications['notify_missive'] ?? 0,
            $playerId
        ];

        return $this->db->exe($sql, $params) !== false;
    }
}
