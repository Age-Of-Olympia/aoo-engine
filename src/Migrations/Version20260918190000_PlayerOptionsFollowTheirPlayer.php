<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `players_options` follows its player: ON DELETE CASCADE instead of RESTRICT.
 *
 * An option row means nothing without its player. Since every row of
 * `players` carries `newHud` (Version20260918140000), each path that removes
 * an entity — resource or plant reconciled by an import or a Tiled push,
 * plant picked up, bourse emptied — has to remember the table first, or the
 * foreign key refuses the delete.
 *
 * Idempotent: the constraint is rebuilt only while its rule is not CASCADE.
 */
final class Version20260918190000_PlayerOptionsFollowTheirPlayer extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'players_options rows are deleted with their player';
    }

    public function up(Schema $schema): void
    {
        if ($this->deleteRule() === 'CASCADE') {
            return;
        }

        $this->addSql('ALTER TABLE players_options DROP FOREIGN KEY players_options_ibfk_1');
        $this->addSql(
            'ALTER TABLE players_options
             ADD CONSTRAINT players_options_ibfk_1 FOREIGN KEY (player_id) REFERENCES players (id) ON DELETE CASCADE'
        );
    }

    public function down(Schema $schema): void
    {
        if ($this->deleteRule() !== 'CASCADE') {
            return;
        }

        $this->addSql('ALTER TABLE players_options DROP FOREIGN KEY players_options_ibfk_1');
        $this->addSql(
            'ALTER TABLE players_options
             ADD CONSTRAINT players_options_ibfk_1 FOREIGN KEY (player_id) REFERENCES players (id)'
        );
    }

    private function deleteRule(): ?string
    {
        $rule = $this->connection->fetchOne(
            'SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            ['players_options', 'players_options_ibfk_1']
        );

        return $rule === false ? null : (string) $rule;
    }
}
