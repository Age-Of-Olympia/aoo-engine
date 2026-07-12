<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reclassify construction logs from the generic "action" type to a dedicated
 * "build" type, so build events can be told apart from combat actions (and stop
 * being eligible for the action/action_other_player pairing in Log::filterRows).
 *
 * Build events are the only logs whose text contains " a construit " (emitted by
 * build.php), so that marker identifies the rows precisely. Both the live table
 * and the archive are updated.
 *
 * Data-only fix; down() restores the previous "action" type for the same rows.
 */
final class Version20260628160000_AddBuildLogType extends AbstractMigration
{
    private const MARKER = '% a construit %';

    public function getDescription(): string
    {
        return 'Reclassify construction logs from type "action" to "build" (players_logs + archives)';
    }

    public function up(Schema $schema): void
    {
        foreach (['players_logs', 'players_logs_archives'] as $table) {
            $this->addSql(
                "UPDATE $table SET type = 'build' WHERE type = 'action' AND text LIKE :marker",
                ['marker' => self::MARKER]
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['players_logs', 'players_logs_archives'] as $table) {
            $this->addSql(
                "UPDATE $table SET type = 'action' WHERE type = 'build' AND text LIKE :marker",
                ['marker' => self::MARKER]
            );
        }
    }
}
