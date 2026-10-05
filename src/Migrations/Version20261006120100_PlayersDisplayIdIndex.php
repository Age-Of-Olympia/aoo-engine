<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * getNextDisplayId() reads MAX(display_id) per player_type on every entity
 * created: without this index, a full scan of players (~45 ms on a 300k-cell
 * map import, per building). The new index starts with player_type, so it
 * replaces idx_player_type.
 */
final class Version20261006120100_PlayersDisplayIdIndex extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index (player_type, display_id) sur players, remplace idx_player_type';
    }

    public function up(Schema $schema): void
    {
        if (!$this->hasIndex('idx_players_type_display')) {
            $this->addSql('ALTER TABLE players ADD INDEX idx_players_type_display (player_type, display_id)');
        }
        if ($this->hasIndex('idx_player_type')) {
            $this->addSql('ALTER TABLE players DROP INDEX idx_player_type');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$this->hasIndex('idx_player_type')) {
            $this->addSql('ALTER TABLE players ADD INDEX idx_player_type (player_type)');
        }
        if ($this->hasIndex('idx_players_type_display')) {
            $this->addSql('ALTER TABLE players DROP INDEX idx_players_type_display');
        }
    }

    private function hasIndex(string $name): bool
    {
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
              WHERE table_schema = DATABASE() AND table_name = 'players' AND index_name = ?",
            [$name]
        ) > 0;
    }
}
