<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Items Phase 1d (docs/design-items-instances.md §5c) — the ONE data
 * conversion of the lazy-promotion policy: currently-equipped stack
 * rows become instances.
 *
 * Handles the « equiped-on-stack » wart (P1): a row (n=3,
 * equiped='main1') is legal today — the whole stack is flagged. The
 * conversion SPLITS it: one pristine equipped instance + a stack of
 * n−1. Stack-semantic emplacements (munition, trophee — the whole
 * quiver is equipped as a block) are deliberately NOT converted; they
 * keep the legacy representation, matching Player::equip()'s Phase 1c
 * behavior.
 *
 * Runs AFTER the read (1b) and write (1c) paths understand instances —
 * strangler order. Idempotent: converted rows lose their equiped flag,
 * re-running finds nothing to convert.
 */
final class Version20260717140000_ConvertEquippedRows extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert equipped players_items stack rows into item instances (split n>1)';
    }

    public function up(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT player_id, item_id, n, equiped
             FROM players_items
             WHERE equiped != '' AND equiped NOT IN ('munition', 'trophee')"
        );

        foreach ($rows as $row) {
            $this->addSql(
                'INSERT INTO item_instances (item_id, created_at) VALUES (?, ?)',
                [(int) $row['item_id'], time()]
            );
            $this->addSql(
                'INSERT INTO players_items_instances (player_id, instance_id, equiped)
                 VALUES (?, LAST_INSERT_ID(), ?)',
                [(int) $row['player_id'], (string) $row['equiped']]
            );
            // Split: the equipped unit left the stack; n−1 remain, flag cleared.
            $this->addSql(
                "UPDATE players_items SET n = n - 1, equiped = '' WHERE player_id = ? AND item_id = ?",
                [(int) $row['player_id'], (int) $row['item_id']]
            );
        }

        // Purge the emptied rows in one pass.
        $this->addSql("DELETE FROM players_items WHERE n <= 0 AND equiped = ''");
    }

    public function down(Schema $schema): void
    {
        // Pristine equipped instances go back to flagged stack rows; worn or
        // named ones cannot be represented as stacks and are left in place.
        $this->addSql(
            "INSERT INTO players_items (player_id, item_id, n, equiped)
             SELECT l.player_id, i.item_id, 1, l.equiped
             FROM players_items_instances l
             JOIN item_instances i ON i.id = l.instance_id
             WHERE l.equiped != '' AND i.destroyed = 0
               AND i.durability = i.durability_max AND i.custom_name = ''
             ON DUPLICATE KEY UPDATE n = n + 1, equiped = VALUES(equiped)"
        );
        $this->addSql(
            "DELETE l, i FROM players_items_instances l
             JOIN item_instances i ON i.id = l.instance_id
             WHERE l.equiped != '' AND i.destroyed = 0
               AND i.durability = i.durability_max AND i.custom_name = ''"
        );
    }
}
