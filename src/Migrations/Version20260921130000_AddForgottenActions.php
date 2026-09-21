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

    public function up(Schema $schema): void
    {
        foreach (self::ACTIONS_DATA as $action) {
            $columns = implode(', ', array_keys($action));
            $placeholders = implode(', ', array_fill(0, count($action), '?'));
            $this->addSql("INSERT INTO actions ($columns) VALUES ($placeholders)", array_values($action));
        }

        // Children hang off their parent by NAME: ids differ between databases.
        foreach (self::ACTION_CONDITIONS as $c) {
            $this->addSql(
                'INSERT INTO action_conditions (conditionType, parameters, execution_order, blocking, action_id)
                 SELECT ?, ?, ?, ?, id FROM actions WHERE name = ?',
                [$c['conditionType'], $c['parameters'], $c['execution_order'], $c['blocking'], $c['action']]
            );
        }

        foreach (self::ACTION_OUTCOMES as $o) {
            $this->addSql(
                'INSERT INTO action_outcomes (apply_to, name, on_success, action_id)
                 SELECT ?, ?, ?, id FROM actions WHERE name = ?',
                [$o['apply_to'], $o['name'], $o['on_success'], $o['action']]
            );
        }

        foreach (self::OUTCOME_INSTRUCTIONS as $i) {
            $this->addSql(
                'INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
                 SELECT ?, ?, ?, id FROM action_outcomes WHERE name = ?',
                [$i['type'], $i['parameters'], $i['orderIndex'], $i['outcome']]
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::OUTCOME_INSTRUCTIONS as $i) {
            $this->addSql(
                'DELETE i FROM outcome_instructions i JOIN action_outcomes o ON o.id = i.outcome_id
                 WHERE o.name = ? AND i.type = ? AND i.orderIndex = ?',
                [$i['outcome'], $i['type'], $i['orderIndex']]
            );
        }

        $names = array_column(self::ACTIONS_DATA, 'name');
        $in = implode(', ', array_fill(0, count($names), '?'));
        $this->addSql("DELETE o FROM action_outcomes o JOIN actions a ON a.id = o.action_id WHERE a.name IN ($in)", $names);

        $this->addSql("DELETE c FROM action_conditions c JOIN actions a ON a.id = c.action_id WHERE a.name IN ($in)", $names);
        $this->addSql("DELETE FROM actions WHERE name IN ($in)", $names);
    }
}
