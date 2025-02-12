<?php

namespace App\Service;

use Db;
use DateTime;

class EmailNotificationService
{
    private Db $db;
    private PlayerService $playerService;
    
    public function __construct()
    {
        $this->db = new Db();
        $this->playerService = new PlayerService();
    }

    public function getPlayerNotifications(int $playerId): ?object
    {
        $sql = "SELECT * FROM notifications WHERE player_id = ?";
        $result = $this->db->exe($sql, [$playerId]);
        return $result->fetch_object() ?? null;
    }

    public function updatePlayerNotifications(int $playerId, array $notifications): bool
    {
        // Check if player already has notification settings
        $sql = "SELECT id FROM notifications WHERE player_id = ?";
        $result = $this->db->exe($sql, [$playerId]);
        
        if ($result->fetch_object()) {
            // Update existing settings
            $sql = "UPDATE notifications SET 
                    email_bonus = ?,
                    notify_season = ?,
                    notify_quest = ?,
                    notify_turn = ?,
                    notify_missive = ?
                    WHERE player_id = ?";
        } else {
            // Insert new settings
            $sql = "INSERT INTO notifications 
                    (player_id, email_bonus, notify_season, notify_quest, notify_turn, notify_missive) 
                    VALUES (?, ?, ?, ?, ?, ?)";
        }
        
        return $this->db->exe($sql, [
            $notifications['email_bonus'] ?? 0,
            $notifications['notify_season'] ?? 0,
            $notifications['notify_quest'] ?? 0,
            $notifications['notify_turn'] ?? 0,
            $notifications['notify_missive'] ?? 0,
            $playerId
        ]) !== false;
    }

    public function notifyNewSeason(string $seasonName, string $description): void
    {
        $sql = "SELECT p.id, p.plain_mail 
                FROM players p 
                JOIN notifications n ON p.id = n.player_id 
                WHERE n.email_bonus = 1 AND n.notify_season = 1";
        $result = $this->db->exe($sql);
        
        while ($row = $result->fetch_object()) {
            if ($row->plain_mail) {
                $this->sendEmail(
                    $row->plain_mail,
                    "New Season in Age of Olympia: " . $seasonName,
                    "A new season has begun in Age of Olympia!\n\n" .
                    $seasonName . "\n\n" .
                    $description . "\n\n" .
                    "Login now to start your new adventure!"
                );
            }
        }
    }

    public function notifyNewQuest(string $questName, string $description): void
    {
        $sql = "SELECT p.id, p.plain_mail 
                FROM players p 
                JOIN notifications n ON p.id = n.player_id 
                WHERE n.email_bonus = 1 AND n.notify_quest = 1";
        $result = $this->db->exe($sql);
        
        while ($row = $result->fetch_object()) {
            if ($row->plain_mail) {
                $this->sendEmail(
                    $row->plain_mail,
                    "New Quest in Age of Olympia: " . $questName,
                    "A new quest has been unveiled in Age of Olympia!\n\n" .
                    $questName . "\n\n" .
                    $description . "\n\n" .
                    "Login now to explore this new adventure!"
                );
            }
        }
    }

    public function notifyNewTurn(int $playerId): void
    {
        $sql = "SELECT p.plain_mail 
                FROM players p 
                JOIN notifications n ON p.id = n.player_id 
                WHERE p.id = ? AND n.email_bonus = 1 AND n.notify_turn = 1";
        $result = $this->db->exe($sql, [$playerId]);
        $player = $result->fetch_object();
        
        if ($player && $player->plain_mail) {
            $this->sendEmail(
                $player->plain_mail,
                "New Turn in Age of Olympia",
                "Your new turn is ready in Age of Olympia!\n\n" .
                "Login now to continue your journey!"
            );
        }
    }

    public function notifyNewMissive(int $playerId, string $title): void
    {
        $sql = "SELECT p.plain_mail 
                FROM players p 
                JOIN notifications n ON p.id = n.player_id 
                WHERE p.id = ? AND n.email_bonus = 1 AND n.notify_missive = 1";
        $result = $this->db->exe($sql, [$playerId]);
        $player = $result->fetch_object();
        
        if ($player && $player->plain_mail) {
            $this->sendEmail(
                $player->plain_mail,
                "New Message in Age of Olympia: " . $title,
                "You have received a new message in Age of Olympia!\n\n" .
                "Title: " . $title . "\n\n" .
                "Login now to read your message!"
            );
        }
    }

    public function notifyInactivePlayers(int $daysInactive = 30): void
    {
        $sql = "SELECT p.id, p.plain_mail, p.last_connection 
                FROM players p 
                JOIN notifications n ON p.id = n.player_id 
                WHERE n.email_bonus = 1 
                AND p.last_connection < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND p.last_inactivity_email < DATE_SUB(NOW(), INTERVAL 30 DAY)";
                
        $result = $this->db->exe($sql, [$daysInactive]);
        
        while ($row = $result->fetch_object()) {
            if ($row->plain_mail) {
                $lastConnection = new DateTime($row->last_connection);
                $daysSince = $lastConnection->diff(new DateTime())->days;
                
                $this->sendEmail(
                    $row->plain_mail,
                    "We Miss You in Age of Olympia!",
                    "It's been " . $daysSince . " days since your last visit to Age of Olympia!\n\n" .
                    "The gods await your return. Many adventures and challenges have emerged in your absence.\n\n" .
                    "Login now to continue your epic journey!"
                );
                
                // Update last inactivity email timestamp
                $this->db->exe(
                    "UPDATE players SET last_inactivity_email = NOW() WHERE id = ?",
                    [$row->id]
                );
            }
        }
    }

    private function sendEmail(string $to, string $subject, string $message): void
    {
        // Add proper email headers
        $headers = [
            'From: Age of Olympia <noreply@ageofolympia.com>',
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: PHP/' . phpversion()
        ];

        mail($to, $subject, $message, implode("\r\n", $headers));
    }
}
