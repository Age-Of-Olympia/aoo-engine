<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout d'actions oubliées
 */
final class Version20260921130000_AddForgottenActions extends AbstractMigration
{
    private const ACTIONS_DATA = [
        [
            'name'         => 'migraine',
            'icon'         => 'ra-skull',
            'type'         => 'spell',
            'display_name' => 'Migraine',
            'text'         => 'Dommages mentaux (Pui)',
            'level'        => 2,
            'category'     => 'spell-curse',
            'icon_color'   => 'violet',
        ],
        [
            'name'         => 'duel_mental',
            'icon'         => 'ra-arcane-mask',
            'type'         => 'spell',
            'display_name' => 'Duel mental',
            'text'         => 'Jet de FM pur. Dommages mentaux (X) où X est la différence des jets de dé',
            'level'        => 3,
            'category'     => 'spell-curse',
            'icon_color'   => 'violet',
        ],
    ];

    private const ACTION_CONDITIONS = [
        // --- MIGRAINE ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"min":2}',
            'action'       => 'migraine',
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 4}',
            'action'       => 'migraine',
            'execution_order' => 5,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'SpellCompute',
            'parameters'      => '{"actorRollType":"fm", "targetRollType": "fm"}',
            'action'       => 'migraine',
            'execution_order' => 7,
            'blocking'        => 0,
        ],
        // --- DUEL MENTAL ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"min":2}',
            'action'       => 'duel_mental',
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 4}',
            'action'       => 'duel_mental',
            'execution_order' => 5,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'SpellPureCompute',
            'parameters'      => '{"actorRollType":"fm", "targetRollType": "fm"}',
            'action'       => 'duel_mental',
            'execution_order' => 7,
            'blocking'        => 0,
        ],
    ];

    private const ACTION_OUTCOMES = [
        // --- MIGRAINE ---
        [
            'apply_to'   => 'target',
            'name'       => 'mal_migraine',
            'on_success' => 1,
            'action'  => 'migraine',
        ],
        // --- DUEL MENTAL ---
        [
            'apply_to'   => 'target',
            'name'       => 'mal_duelmental',
            'on_success' => 1,
            'action'  => 'duel_mental',
        ],
    ];

    private const OUTCOME_INSTRUCTIONS = [
        // --- MIGRAINE ---
        [
            'type'       => 'manaloss',
            'parameters' => '{ "lossType": "carac", "value":"pui", "typeDivisor":1 }',
            'orderIndex' => 1,
            'outcome' => 'mal_migraine',
        ],
        // --- DUEL MENTAL ---
        [
            'type'       => 'manaloss',
            'parameters' => '{ "lossType": "difference" }',
            'orderIndex' => 1,
            'outcome' => 'mal_duelmental',
        ],
    ];

    public function getDescription(): string
    {
        return 'Ajout d\'actions oubliées dans la migration précédente';
    }

    /**
     * Idempotent: experimental got these actions by hand. An action is only
     * created when its name is absent, and its conditions, outcomes and
     * instructions only land on an action (or outcome) that has none yet,
     * so an existing, complete action is left alone.
     */
    public function up(Schema $schema): void
    {
        foreach (self::ACTIONS_DATA as $action) {
            $columns = implode(', ', array_keys($action));
            $placeholders = implode(', ', array_fill(0, count($action), '?'));
            $this->addSql(
                "INSERT INTO actions ($columns) SELECT $placeholders FROM DUAL
                  WHERE NOT EXISTS (SELECT 1 FROM actions WHERE name = ?)",
                [...array_values($action), $action['name']]
            );
        }

        // Children hang off their parent by NAME: ids differ between databases.
        foreach (self::ACTION_CONDITIONS as $c) {
            $this->addSql(
                'INSERT INTO action_conditions (conditionType, parameters, execution_order, blocking, action_id)
                 SELECT ?, ?, ?, ?, a.id FROM actions a
                  WHERE a.name = ?
                    AND NOT EXISTS (SELECT 1 FROM action_conditions c WHERE c.action_id = a.id AND c.conditionType = ?)',
                [$c['conditionType'], $c['parameters'], $c['execution_order'], $c['blocking'], $c['action'], $c['conditionType']]
            );
        }

        foreach (self::ACTION_OUTCOMES as $o) {
            $this->addSql(
                'INSERT INTO action_outcomes (apply_to, name, on_success, action_id)
                 SELECT ?, ?, ?, a.id FROM actions a
                  WHERE a.name = ?
                    AND NOT EXISTS (SELECT 1 FROM action_outcomes o WHERE o.action_id = a.id)',
                [$o['apply_to'], $o['name'], $o['on_success'], $o['action']]
            );
        }

        foreach (self::OUTCOME_INSTRUCTIONS as $i) {
            $this->addSql(
                'INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
                 SELECT ?, ?, ?, o.id FROM action_outcomes o
                  WHERE o.name = ?
                    AND NOT EXISTS (SELECT 1 FROM outcome_instructions i WHERE i.outcome_id = o.id)',
                [$i['type'], $i['parameters'], $i['orderIndex'], $i['outcome']]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $names = array_column(self::ACTIONS_DATA, 'name');
        $in = implode(', ', array_fill(0, count($names), '?'));

        // Children first, all reached through the action's name (foreign keys)
        $this->addSql(
            "DELETE i FROM outcome_instructions i
               JOIN action_outcomes o ON o.id = i.outcome_id
               JOIN actions a ON a.id = o.action_id
              WHERE a.name IN ($in)",
            $names
        );
        $this->addSql("DELETE o FROM action_outcomes o JOIN actions a ON a.id = o.action_id WHERE a.name IN ($in)", $names);
        $this->addSql("DELETE c FROM action_conditions c JOIN actions a ON a.id = c.action_id WHERE a.name IN ($in)", $names);
        $this->addSql("DELETE FROM actions WHERE name IN ($in)", $names);
    }
}
