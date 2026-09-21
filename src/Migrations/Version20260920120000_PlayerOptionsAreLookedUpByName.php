<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920120000_PlayerOptionsAreLookedUpByName extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'players_options(name): the board and the observation panel list the invisible players by option name on every request';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE players_options ADD INDEX IF NOT EXISTS idx_players_options_name (name)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE players_options DROP INDEX IF EXISTS idx_players_options_name');
    }
}
