<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A faction keeps its JOURNAL — the players_logs idea, for the house:
 * who took what from which chest, who turned which lock, who took a
 * building's commands. Written by the services at the gesture, read
 * on the faction page by its members. The message is stored whole,
 * names resolved at write time, like a player's log line.
 */
final class Version20260805170000_AFactionKeepsItsJournal extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'faction_logs — le journal de la faction, comme les logs joueur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS faction_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                faction_id INT NOT NULL,
                player_id INT NULL,
                message TEXT NOT NULL,
                time INT NOT NULL,
                KEY idx_faction_logs_read (faction_id, time),
                CONSTRAINT fk_faction_logs_faction FOREIGN KEY (faction_id)
                    REFERENCES factions (id) ON DELETE CASCADE,
                CONSTRAINT fk_faction_logs_player FOREIGN KEY (player_id)
                    REFERENCES players (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS faction_logs');
    }
}
