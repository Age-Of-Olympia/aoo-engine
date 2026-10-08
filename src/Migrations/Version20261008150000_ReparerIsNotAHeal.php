<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * reparer leaves the heal type for its own (repair) and pays a dose of the
 * type's materials recipe; reparer_or is its twin in gold mode, so the card
 * shows two buttons and each hides on its own. XP stays at the heal rate.
 *
 * Runs with the code: the deployed code knows neither the repair type nor
 * the RepairBill condition.
 */
final class Version20261008150000_ReparerIsNotAHeal extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'reparer passe au type repair, reparer_or en copie : à lancer avec le code (l\'ancien code ne connaît ni le type repair ni RepairBill)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE actions SET type = 'repair' WHERE name = 'reparer' AND type = 'heal'");
        $this->addSql("UPDATE actions SET display_name = 'Réparer (matériaux)' WHERE name = 'reparer' AND display_name = 'Réparer'");
        $this->addSql(
            "UPDATE outcome_instructions oi
               JOIN action_outcomes o ON o.id = oi.outcome_id
               JOIN actions a ON a.id = o.action_id
                SET oi.type = 'repair', oi.parameters = '{}'
              WHERE a.name = 'reparer' AND oi.type = 'healing'"
        );
        $this->addSql(
            "UPDATE action_conditions c JOIN actions a ON a.id = c.action_id
                SET c.parameters = '{\"mode\":\"materials\"}'
              WHERE a.name = 'reparer' AND c.conditionType = 'RequiresRepairableTarget'"
        );
        $this->addSql(
            "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context)
             SELECT 'RepairBill', '{\"mode\":\"materials\"}', a.id, 2, 1, 0 FROM actions a
              WHERE a.name = 'reparer'
                AND NOT EXISTS (SELECT 1 FROM action_conditions c WHERE c.action_id = a.id AND c.conditionType = 'RepairBill')"
        );

        // The gold twin: same row, same conditions and outcomes, gold mode.
        $this->addSql(
            "INSERT INTO actions (name, icon, type, display_name, text, level, race, category, cost, prerequisites, icon_color)
             SELECT 'reparer_or', icon, type, 'Réparer (or)', text, level, race, category, cost, prerequisites, icon_color
               FROM actions WHERE name = 'reparer'
                AND NOT EXISTS (SELECT 1 FROM actions WHERE name = 'reparer_or')"
        );
        $this->addSql(
            "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context)
             SELECT c.conditionType,
                    IF(c.conditionType IN ('RepairBill', 'RequiresRepairableTarget'), '{\"mode\":\"gold\"}', c.parameters),
                    t.id, c.execution_order, c.blocking, c.display_context
               FROM action_conditions c
               JOIN actions a ON a.id = c.action_id AND a.name = 'reparer'
               JOIN actions t ON t.name = 'reparer_or'
              WHERE NOT EXISTS (SELECT 1 FROM action_conditions x WHERE x.action_id = t.id)"
        );
        $this->addSql(
            "INSERT INTO action_outcomes (apply_to, name, on_success, action_id)
             SELECT o.apply_to, o.name, o.on_success, t.id
               FROM action_outcomes o
               JOIN actions a ON a.id = o.action_id AND a.name = 'reparer'
               JOIN actions t ON t.name = 'reparer_or'
              WHERE NOT EXISTS (SELECT 1 FROM action_outcomes x WHERE x.action_id = t.id)"
        );
        $this->addSql(
            "INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
             SELECT oi.type, oi.parameters, oi.orderIndex, tw.id
               FROM outcome_instructions oi
               JOIN action_outcomes o ON o.id = oi.outcome_id
               JOIN actions a ON a.id = o.action_id AND a.name = 'reparer'
               JOIN actions t ON t.name = 'reparer_or'
               JOIN action_outcomes tw ON tw.action_id = t.id AND tw.name <=> o.name
              WHERE NOT EXISTS (SELECT 1 FROM outcome_instructions x WHERE x.outcome_id = tw.id)"
        );

        // Whoever was given reparer gets the gold twin too.
        $this->addSql(
            "INSERT IGNORE INTO players_actions (player_id, name, type)
             SELECT player_id, 'reparer_or', type FROM players_actions WHERE name = 'reparer'"
        );
        $this->addSql(
            "INSERT INTO race_starter_actions (race_id, name, position)
             SELECT s.race_id, 'reparer_or', s.position
               FROM race_starter_actions s
              WHERE s.name = 'reparer'
                AND NOT EXISTS (SELECT 1 FROM race_starter_actions x WHERE x.race_id = s.race_id AND x.name = 'reparer_or')"
        );
        $this->addSql(
            "INSERT IGNORE INTO race_actions (race_id, action_id)
             SELECT ra.race_id, t.id
               FROM race_actions ra
               JOIN actions a ON a.id = ra.action_id AND a.name = 'reparer'
               JOIN actions t ON t.name = 'reparer_or'"
        );

        $this->addSql(
            "INSERT INTO action_type_xp (type_key, mode, params)
             SELECT 'repair', 'fixed', '{\"actorSuccess\":3,\"actorFail\":0,\"targetSuccess\":0,\"targetFail\":0}'
             WHERE NOT EXISTS (SELECT 1 FROM action_type_xp WHERE type_key = 'repair')"
        );
        $this->addSql(
            "INSERT INTO action_type_logs (type_key, actor_template, target_template)
             SELECT 'repair', '{actor} a réparé {target}.', '{target} a été réparé par {actor}.'
             WHERE NOT EXISTS (SELECT 1 FROM action_type_logs WHERE type_key = 'repair')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM players_actions WHERE name = 'reparer_or'");
        $this->addSql("DELETE FROM race_starter_actions WHERE name = 'reparer_or'");
        $this->addSql("DELETE ra FROM race_actions ra JOIN actions a ON a.id = ra.action_id WHERE a.name = 'reparer_or'");
        $this->addSql(
            "DELETE oi FROM outcome_instructions oi JOIN action_outcomes o ON o.id = oi.outcome_id
               JOIN actions a ON a.id = o.action_id WHERE a.name = 'reparer_or'"
        );
        $this->addSql("DELETE o FROM action_outcomes o JOIN actions a ON a.id = o.action_id WHERE a.name = 'reparer_or'");
        $this->addSql("DELETE c FROM action_conditions c JOIN actions a ON a.id = c.action_id WHERE a.name = 'reparer_or'");
        $this->addSql("DELETE FROM actions WHERE name = 'reparer_or'");

        $this->addSql("DELETE FROM action_type_logs WHERE type_key = 'repair'");
        $this->addSql("DELETE FROM action_type_xp WHERE type_key = 'repair'");
        $this->addSql(
            "DELETE c FROM action_conditions c JOIN actions a ON a.id = c.action_id
              WHERE a.name = 'reparer' AND c.conditionType = 'RepairBill'"
        );
        $this->addSql(
            "UPDATE action_conditions c JOIN actions a ON a.id = c.action_id
                SET c.parameters = '{}'
              WHERE a.name = 'reparer' AND c.conditionType = 'RequiresRepairableTarget'"
        );
        $this->addSql(
            "UPDATE outcome_instructions oi
               JOIN action_outcomes o ON o.id = oi.outcome_id
               JOIN actions a ON a.id = o.action_id
                SET oi.type = 'healing', oi.parameters = '{\"actorHealingTrait\":\"f\"}'
              WHERE a.name = 'reparer' AND oi.type = 'repair'"
        );
        $this->addSql("UPDATE actions SET display_name = 'Réparer' WHERE name = 'reparer' AND display_name = 'Réparer (matériaux)'");
        $this->addSql("UPDATE actions SET type = 'heal' WHERE name = 'reparer' AND type = 'repair'");
    }
}
