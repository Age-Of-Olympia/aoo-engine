<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The "DLA glissante" (`dlag`) option is replaced by the manual next-turn
 * reschedule (api/player/set_next_turn.php + TurnScheduleService). The
 * option no longer has any effect, so the leftover preference rows are
 * purged.
 */
final class Version20260715120000_RemoveDlagOption extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove obsolete dlag option rows (replaced by manual next-turn reschedule)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM players_options WHERE name = 'dlag'");
    }

    public function down(Schema $schema): void
    {
        // Deleted rows were per-player preferences; they cannot be restored.
    }
}
