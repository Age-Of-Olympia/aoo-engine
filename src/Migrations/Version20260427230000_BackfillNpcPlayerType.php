<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Backfill `players.player_type` for NPC rows misclassified as 'real'.
 *
 * Two writers historically left the column at its default ('real') for
 * negative-id rows:
 *   - TutorialMapInstance::copyNPCs() — fixed in the same MR; produced
 *     the bulk of misclassified rows (Gaïa duplicates, one per tutorial
 *     session).
 *   - Any pre-discriminator INSERT that predates the player_type column
 *     and was never touched by the original add_player_type backfill.
 *
 * Idempotent: scoped on `id < 0 AND player_type = 'real'`. Re-running
 * after the fix is in place is a no-op because new copies are written
 * with player_type='npc' directly.
 *
 * Rows with id<0 and player_type IN ('npc','tutorial') are left alone:
 *   - 'npc' is already correct;
 *   - 'tutorial' should never appear with id<0 (tutorial players have
 *     positive IDs ≥ 10000000) but if it ever did, the right action is
 *     to investigate, not to silently re-stamp.
 */
final class Version20260427230000_BackfillNpcPlayerType extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Backfill players.player_type='npc' for negative-id rows still tagged 'real'";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            UPDATE players
            SET player_type = 'npc'
            WHERE id < 0
              AND player_type = 'real'
        ");
    }

    public function down(Schema $schema): void
    {
        // Intentional no-op. Reverting would re-introduce the misclassification
        // bug this migration exists to repair, and the original 'real' tagging
        // was never a deliberate state — only a default that leaked through.
    }
}
