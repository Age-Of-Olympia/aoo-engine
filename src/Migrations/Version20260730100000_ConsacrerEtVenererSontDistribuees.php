<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The two altar gestures reach the players who can use them.
 *
 * They were created dormant so the switchover had somewhere to land. Held by
 * nobody, though, their buttons never appear: an action is offered because a
 * player holds it.
 *
 * Given wherever `prier` is given — the same population, the same reason: it
 * is the god-facing set. New characters get them from their race, and those
 * already playing get them here.
 */
final class Version20260730100000_ConsacrerEtVenererSontDistribuees extends AbstractMigration
{
    private const ACTIONS = ['consacrer', 'venerer'];

    public function getDescription(): string
    {
        return 'consacrer and venerer are granted wherever prier is';
    }

    public function up(Schema $schema): void
    {
        foreach (self::ACTIONS as $action) {
            /* Same races as `prier`, and its position plus one so the order
               stays readable. */
            $this->addSql(
                "INSERT INTO race_starter_actions (race_id, name, position)
                 SELECT s.race_id, ?, MAX(s.position) + 1
                   FROM race_starter_actions s
                  WHERE s.name = 'prier'
                    AND NOT EXISTS (
                        SELECT 1 FROM race_starter_actions x
                         WHERE x.race_id = s.race_id AND x.name = ?
                    )
                  GROUP BY s.race_id",
                [$action, $action]
            );

            $this->addSql(
                "INSERT INTO players_actions (player_id, name, type)
                 SELECT pa.player_id, ?, ''
                   FROM players_actions pa
                  WHERE pa.name = 'prier'
                    AND NOT EXISTS (
                        SELECT 1 FROM players_actions x
                         WHERE x.player_id = pa.player_id AND x.name = ?
                    )
                  GROUP BY pa.player_id",
                [$action, $action]
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::ACTIONS as $action) {
            $this->addSql("DELETE FROM race_starter_actions WHERE name = ?", [$action]);
            $this->addSql("DELETE FROM players_actions WHERE name = ?", [$action]);
        }
    }
}
