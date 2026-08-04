<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Building takes time: the construction site.
 *
 * The TYPE declares its total work (`races.build_work`, 0 = raised in
 * one gesture — every existing type, so nothing changes until a type
 * asks for it; the atelier is the first, at 10). When construire places
 * a type that declares work, the entity is born a CHANTIER: the
 * `construction_sites` satellite carries the progress, `buildings.build_state`
 * finally gets its 'construction' writer (the state was kept for
 * exactly this), and the closure rule shuts the site. PV grow with the
 * work — a half-built wall is easier to raze than a finished one.
 *
 * The `travailler` action advances it: adjacent, one action point, and
 * the mayLock rule says who may — the owner, a faction member, and a
 * site with neither belongs to everyone. XP at the reparer rate,
 * adjustable in data.
 */
final class Version20260804120000_ConstructionTakesWork extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Le chantier : races.build_work, satellite construction_sites, action travailler";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS construction_sites (
                player_id INT NOT NULL,
                work_done INT NOT NULL DEFAULT 0,
                work_total INT NOT NULL,
                PRIMARY KEY (player_id),
                CONSTRAINT fk_construction_sites_player FOREIGN KEY (player_id)
                    REFERENCES players (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'
        );

        $this->addSql('ALTER TABLE races ADD COLUMN IF NOT EXISTS build_work INT NOT NULL DEFAULT 0');
        $this->addSql("UPDATE races SET build_work = 10 WHERE name = 'atelier' AND build_work = 0");

        $actionExists = $this->connection->fetchOne("SELECT id FROM actions WHERE name = 'travailler'");
        if ($actionExists === false) {
            $this->addSql(
                "INSERT INTO actions (name, icon, type, display_name, text, level)
                 VALUES ('travailler', 'ra-hammer', 'work', 'Travailler',
                         'Fait avancer un chantier d''une unité de travail.', 1)"
            );
            $this->addSql(
                "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context)
                 SELECT 'RequiresDistance', '{\"max\":1}', a.id, 1, 1, 1 FROM actions a WHERE a.name = 'travailler'"
            );
            $this->addSql(
                "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context)
                 SELECT 'TargetType', '{\"allowed\":[\"building\"]}', a.id, 2, 1, 0 FROM actions a WHERE a.name = 'travailler'"
            );
            $this->addSql(
                "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context)
                 SELECT 'RequiresConstructionSite', '{}', a.id, 3, 1, 1 FROM actions a WHERE a.name = 'travailler'"
            );
            $this->addSql(
                "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context)
                 SELECT 'RequiresTraitValue', '{\"a\":1}', a.id, 4, 1, 0 FROM actions a WHERE a.name = 'travailler'"
            );
            $this->addSql(
                "INSERT INTO action_outcomes (apply_to, name, on_success, action_id)
                 SELECT 'target', 'oeuvre', 1, a.id FROM actions a WHERE a.name = 'travailler'"
            );
            $this->addSql(
                "INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
                 SELECT 'advanceconstructionsite', '{\"units\":1}', 0, o.id
                 FROM action_outcomes o JOIN actions a ON a.id = o.action_id
                 WHERE a.name = 'travailler' AND o.name = 'oeuvre'"
            );
        }

        // XP at the reparer rate; the admin's per-type defaults can retune it.
        $this->addSql(
            "INSERT INTO action_type_xp (type_key, mode, params)
             SELECT 'work', 'fixed', '{\"actorSuccess\":3,\"actorFail\":0,\"targetSuccess\":0,\"targetFail\":0}'
             WHERE NOT EXISTS (SELECT 1 FROM action_type_xp WHERE type_key = 'work')"
        );
        $this->addSql(
            "INSERT INTO action_type_logs (type_key, actor_template, target_template)
             SELECT 'work', '{actor} a travaillé sur un chantier.', NULL
             WHERE NOT EXISTS (SELECT 1 FROM action_type_logs WHERE type_key = 'work')"
        );

        // Every character may labor: starter pack for future creations,
        // backfill for everyone already alive.
        $this->addSql(
            "INSERT INTO race_starter_actions (race_id, name, position)
             SELECT r.id, 'travailler', COALESCE((SELECT MAX(position) FROM race_starter_actions x WHERE x.race_id = r.id), 0) + 1
               FROM races r
              WHERE r.kind = 'character'
                AND NOT EXISTS (SELECT 1 FROM race_starter_actions s WHERE s.race_id = r.id AND s.name = 'travailler')"
        );
        $this->addSql(
            "INSERT IGNORE INTO players_actions (player_id, name, type)
             SELECT p.id, 'travailler', 'action'
               FROM players p
              WHERE p.player_type IN ('real', 'tutorial', 'npc')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM players_actions WHERE name = 'travailler'");
        $this->addSql("DELETE FROM race_starter_actions WHERE name = 'travailler'");
        $this->addSql("DELETE FROM action_type_logs WHERE type_key = 'work'");
        $this->addSql("DELETE FROM action_type_xp WHERE type_key = 'work'");
        $this->addSql(
            "DELETE oi FROM outcome_instructions oi
             JOIN action_outcomes o ON o.id = oi.outcome_id
             JOIN actions a ON a.id = o.action_id WHERE a.name = 'travailler'"
        );
        $this->addSql("DELETE o FROM action_outcomes o JOIN actions a ON a.id = o.action_id WHERE a.name = 'travailler'");
        $this->addSql("DELETE c FROM action_conditions c JOIN actions a ON a.id = c.action_id WHERE a.name = 'travailler'");
        $this->addSql("DELETE FROM actions WHERE name = 'travailler'");
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS build_work');
        $this->addSql('DROP TABLE IF EXISTS construction_sites');
    }
}
