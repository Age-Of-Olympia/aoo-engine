<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * One manual next-turn reschedule per turn cycle: the flag is raised by
 * api/player/set_next_turn.php and cleared when the turn refreshes
 * (NewTurnView), so a player cannot chain reschedules to push their turn
 * beyond the following one.
 */
final class Version20260715130000_AddNextTurnRescheduled extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add players.nextTurnRescheduled (one manual reschedule per turn cycle)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE players
            ADD COLUMN IF NOT EXISTS nextTurnRescheduled TINYINT(1) NOT NULL DEFAULT 0'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE players DROP COLUMN IF EXISTS nextTurnRescheduled');
    }
}
