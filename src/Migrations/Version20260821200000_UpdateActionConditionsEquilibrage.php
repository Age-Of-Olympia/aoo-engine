<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Mise à jour des action_conditions.
 */
final class Version20260821200000_UpdateActionConditionsEquilibrage extends AbstractMigration
{
    // --- CONSTANTES POUR PUISSANCE NATURE (UPDATE) ---
    private const PUISSANCE_NATURE_ID = 271;
    private const PUISSANCE_NATURE_NEW_PARAMS = '{ "a": 1, "pm":12 }';
    private const PUISSANCE_NATURE_OLD_PARAMS = '{ "a": 1, "pm":8 }';

    // --- CONSTANTES POUR SAUT ATTAQUE (INSERT) ---
    private const SAUT_ATTAQUE_CONDITIONS = [
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"min":2}',
            'action_id'       => 79,
            'execution_order' => 0,
            'blocking'        => 1,
            'display_context' => 0,
        ],
        [
            'conditionType'   => 'RequiresWeaponType',
            'parameters'      => '{"type": ["melee"]}',
            'action_id'       => 79,
            'execution_order' => 1,
            'blocking'        => 1,
            'display_context' => 0,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a":1, "pm":15, "mvt":1}',
            'action_id'       => 79,
            'execution_order' => 5,
            'blocking'        => 1,
            'display_context' => 0,
        ],
        [
            'conditionType'   => 'MeleeCompute',
            'parameters'      => '{"actorRollType":"cc", "targetRollType": "cc/agi"}',
            'action_id'       => 79,
            'execution_order' => 10,
            'blocking'        => 1,
            'display_context' => 0,
        ],
    ];

    public function getDescription(): string
    {
        return 'Mise à jour des action_conditions.';
    }

    public function up(Schema $schema): void
    {
        // 1. Mise à jour de puissance_nature (ID 271)
        $this->addSql(
            'UPDATE action_conditions SET parameters = ? WHERE id = ?',
            [self::PUISSANCE_NATURE_NEW_PARAMS, self::PUISSANCE_NATURE_ID]
        );

        // 2. Ajout des conditions de saut_attaque (ID 79)
        foreach (self::SAUT_ATTAQUE_CONDITIONS as $condition) {
            $this->addSql(
                'INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context) VALUES (?, ?, ?, ?, ?, ?)',
                array_values($condition)
            );
        }
    }

    public function down(Schema $schema): void
    {
        // 1. Rollback de puissance_nature (ID 271)
        $this->addSql(
            'UPDATE action_conditions SET parameters = ? WHERE id = ?',
            [self::PUISSANCE_NATURE_OLD_PARAMS, self::PUISSANCE_NATURE_ID]
        );

        // 2. Rollback des conditions de saut_attaque (ID 79)
        foreach (self::SAUT_ATTAQUE_CONDITIONS as $condition) {
            $this->addSql(
                'DELETE FROM action_conditions WHERE action_id = ? AND conditionType = ?',
                [$condition['action_id'], $condition['conditionType']]
            );
        }
    }
}