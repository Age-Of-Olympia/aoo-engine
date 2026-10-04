<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The area each player's board shows, written when the board is drawn. A
 * change on a cell flags (stale) and purges exactly the boards whose area
 * holds it, whatever the viewer's Perception; the HUD polls the flag.
 */
final class Version20261005090000_BoardViews extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Zone affichée par la carte de chaque joueur (board_views)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS board_views (
            player_id INT NOT NULL,
            plan VARCHAR(255) NOT NULL,
            z INT NOT NULL,
            x_min INT NOT NULL,
            x_max INT NOT NULL,
            y_min INT NOT NULL,
            y_max INT NOT NULL,
            stale TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (player_id),
            KEY idx_board_views_plan_z (plan, z),
            CONSTRAINT fk_board_views_player FOREIGN KEY (player_id) REFERENCES players (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS board_views');
    }
}
