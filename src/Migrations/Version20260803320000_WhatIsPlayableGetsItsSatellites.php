<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Satellites stop being a character privilege: anything whose TYPE declares
 * itself playable gets its `turns` and `progression` rows too.
 *
 * No row moves today — no building type carries `races.playable` yet, so this
 * backfills nothing. It matters the moment one does: the flag alone then makes
 * a building take turns and earn its own experience, without the entity being
 * reparented under `Character`.
 */
final class Version20260803320000_WhatIsPlayableGetsItsSatellites extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'turns and progression: a playable type gets its satellites, character or not';
    }

    public function up(Schema $schema): void
    {
        /* Same predicate as App\Service\PlaysTurns, word for word: a character
         * plays by nature — hidden system races carry playable = 0 and still
         * take turns — and so does anything its type declares playable. */
        $this->addSql(
            "INSERT IGNORE INTO turns
                 (player_id, next_turn_time, last_action_time, next_turn_rescheduled, anti_berserk_time)
             SELECT p.id,
                    COALESCE(p.nextTurnTime, 0), COALESCE(p.lastActionTime, 0),
                    COALESCE(p.nextTurnRescheduled, 0), COALESCE(p.antiBerserkTime, 0)
               FROM players p
               LEFT JOIN races r ON r.name = p.race
              WHERE p.player_type IN ('real', 'tutorial', 'npc')
                 OR p.player_type IS NULL
                 OR r.playable = 1"
        );

        $this->addSql(
            "INSERT IGNORE INTO progression (player_id, xp, `rank`, bonus_points, pi)
             SELECT p.id,
                    COALESCE(p.xp, 0), COALESCE(p.`rank`, 1),
                    COALESCE(p.bonus_points, 0), COALESCE(p.pi, 0)
               FROM players p
               LEFT JOIN races r ON r.name = p.race
              WHERE p.player_type IN ('real', 'tutorial', 'npc')
                 OR p.player_type IS NULL
                 OR r.playable = 1"
        );
    }

    public function down(Schema $schema): void
    {
        /* The rows are indistinguishable from those the previous migrations
         * seeded; dropping the tables is what undoes them. */
    }
}
