<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Backfill players_actions.type='sort' for already-owned defensive spells
 * (buff/heal classes). They were stored with the default empty type because
 * addAction only stamped 'sort' for spell/technique, so the owned-spells page
 * never listed them and they didn't count toward NUMBER_MAX_COMP (#264).
 *
 * Done in PHP (fetch the names, then an IN-update) to sidestep the
 * players_actions ↔ actions name collation mismatch a JOIN would hit.
 */
final class Version20260628140000_BackfillOwnedDefensiveSpellType extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mark already-owned buff/heal actions as type=sort in players_actions (#264)';
    }

    public function up(Schema $schema): void
    {
        $names = $this->connection->fetchFirstColumn(
            "SELECT name FROM actions WHERE type IN ('buff', 'heal')"
        );
        if ($names === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $this->connection->executeStatement(
            "UPDATE players_actions SET type = 'sort'
             WHERE name IN ({$placeholders}) AND (type IS NULL OR type = '') AND name <> 'attaquer'",
            $names
        );
    }

    public function down(Schema $schema): void
    {
        $names = $this->connection->fetchFirstColumn(
            "SELECT name FROM actions WHERE type IN ('buff', 'heal')"
        );
        if ($names === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $this->connection->executeStatement(
            "UPDATE players_actions SET type = '' WHERE name IN ({$placeholders}) AND type = 'sort'",
            $names
        );
    }
}
