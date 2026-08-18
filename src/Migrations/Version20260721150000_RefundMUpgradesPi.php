<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Refund the PI spent on 'm' upgrades before CleanMFromPlayersUpgrades
 * (20260721200000) deletes their rows: PI is a stored balance debited at
 * purchase (ProgressionService::spendPi), deleting the receipt rows does
 * not credit it back. The raw summed cost is credited, mirroring what
 * Player::remove_upgrade() does for a reassignment.
 *
 * The version number places this BEFORE the delete in a sorted batch. On
 * environments where the delete has already run, the join matches nothing
 * and this is a no-op — the lost PI there was test data.
 *
 * progression.pi is mirrored only when the table exists: in a fresh batch
 * it is created later (20260803310000) and seeds from players.pi, so the
 * credit carries over by itself.
 */
final class Version20260721150000_RefundMUpgradesPi extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Credit back the PI of 'm' upgrades before their rows are deleted";
    }

    public function up(Schema $schema): void
    {
        $refund = 'SELECT player_id, SUM(cost) AS total
                     FROM players_upgrades
                    WHERE name = \'m\'
                    GROUP BY player_id';

        $this->addSql(
            "UPDATE players p
               JOIN ({$refund}) u ON u.player_id = p.id
              SET p.pi = p.pi + u.total"
        );

        if ($schema->hasTable('progression')) {
            $this->addSql(
                "UPDATE progression g
                   JOIN ({$refund}) u ON u.player_id = g.player_id
                  SET g.pi = g.pi + u.total"
            );
        }
    }

    public function down(Schema $schema): void
    {
        // The delete migration's down() cannot restore the rows, so the
        // refunded amounts cannot be recomputed either — no-op.
    }
}
