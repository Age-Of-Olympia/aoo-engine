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

    public function notifyNewSeason(string $seasonName, string $description): void
    {
        $sql = "SELECT id FROM players WHERE email_bonus = 1";
        $result = $this->db->exe($sql);
        
        while ($row = $result->fetch_object()) {
            $email = $this->playerService->getPlainEmail($row->id);
            if ($email) {
                $this->sendEmail(
                    $email,
                    "New Season in Age of Olympia: " . $seasonName,
                    "A new season has begun in Age of Olympia!\n\n" .
                    $seasonName . "\n\n" .
                    $description . "\n\n" .
                    "Login now to start your new adventure!"
                );
            }
        }
    }

    public function notifyNewScenario(string $scenarioName, string $description): void
    {
        $sql = "SELECT id FROM players WHERE email_bonus = 1";
        $result = $this->db->exe($sql);
        
        while ($row = $result->fetch_object()) {
            $email = $this->playerService->getPlainEmail($row->id);
            if ($email) {
                $this->sendEmail(
                    $email,
                    "New Scenario in Age of Olympia: " . $scenarioName,
                    "A new scenario has been unveiled in Age of Olympia!\n\n" .
                    $scenarioName . "\n\n" .
                    $description . "\n\n" .
                    "Login now to explore this new adventure!"
                );
            }
        }
    }

    public function notifyInactivePlayers(int $daysInactive = 30): void
    {
        $sql = "SELECT id, last_connection FROM players 
                WHERE email_bonus = 1 
                AND last_connection < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND last_inactivity_email < DATE_SUB(NOW(), INTERVAL 30 DAY)";
                
        $result = $this->db->exe($sql, [$daysInactive]);
        
        while ($row = $result->fetch_object()) {
            $email = $this->playerService->getPlainEmail($row->id);
            if ($email) {
                $lastConnection = new DateTime($row->last_connection);
                $daysSince = $lastConnection->diff(new DateTime())->days;
                
                $this->sendEmail(
                    $email,
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
