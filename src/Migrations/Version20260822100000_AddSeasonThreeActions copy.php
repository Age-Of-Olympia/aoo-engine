<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout de nouvelles actions pour la saison 3
 */
final class Version20260822100000_AddSeasonThreeActions extends AbstractMigration
{
    private const ACTIONS_DATA = [
        [
            'name'         => 'encaisser',
            'icon'         => 'ra-muscle-fat',
            'type'         => 'buff',
            'display_name' => 'Encaisser',
            'text'         => 'Encaisse(1)',
            'level'        => 1,
            'category'     => 'survival-buff',
            'cost'         => '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">6 PM</span>',
        ],
        [
            'name'         => 'tir_puissant',
            'icon'         => 'ra-heavy-fall',
            'type'         => 'technique',
            'display_name' => 'Tir puissant',
            'text'         => 'Touche automatique sans dégâts. Poussée sur la case opposée.',
            'level'        => 2,
            'category'     => 'distance-curse',
            'cost'         => '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">2 PM</span>, <span style="color: #27ae60;">1 Mvt</span>',
        ],
        [
            'name'         => 'harponnage',
            'icon'         => 'ra-harpoon-trident',
            'type'         => 'technique',
            'display_name' => 'Harponnage',
            'text'         => 'Poussée en direction du lanceur.',
            'level'        => 3,
            'category'     => 'distance-off',
            'cost'         => '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">8 PM</span>, <span style="color: #27ae60;">1 Mvt</span>',
        ],
        [
            'name'         => 'parade',
            'icon'         => 'ra-sword',
            'type'         => 'technique',
            'display_name' => 'Parade',
            'text'         => 'Pare la prochaine attaque de corps-à-corps si vous êtes équipé d\'une arme de corps-à-corps.',
            'level'        => 1,
            'category'     => 'survival-buff',
            'cost'         => '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">8 PM</span>',
        ],
        [
            'name'         => 'pas_de_cote',
            'icon'         => 'ra-player-dodge',
            'type'         => 'technique',
            'display_name' => 'Pas de côté',
            'text'         => 'Esquive le prochain tir en vous déplaçant sur une case adjacente.',
            'level'        => 1,
            'category'     => 'survival-buff',
            'cost'         => '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">8 PM</span>',
        ],
        [
            'name'         => 'dissipation',
            'icon'         => 'ra-lava',
            'type'         => 'technique',
            'display_name' => 'Dissipation',
            'text'         => 'Dissipe le prochain sort lancé sur vous.',
            'level'        => 1,
            'category'     => 'survival-buff',
            'cost'         => '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">8 PM</span>',
        ],
        [
            'name'         => 'dedoublement',
            'icon'         => 'ra-double-team',
            'type'         => 'technique',
            'display_name' => 'Dédoublement',
            'text'         => 'Crée un double illusoire qui va encaisser la prochaine attaque à votre place.',
            'level'        => 3,
            'category'     => 'survival-buff',
            'cost'         => '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">10 PM</span>',
        ],
    ];

    private const ACTION_CONDITIONS = [
        // --- ENCAISSER (ID 125) ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"max":0}',
            'action_id'       => 125,
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 6}',
            'action_id'       => 125,
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        // --- TIR PUISSANT (ID 126) ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"min":2}',
            'action_id'       => 126,
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresWeaponType',
            'parameters'      => '{"type": ["jet"]}',
            'action_id'       => 126,
            'execution_order' => 1,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresAmmo',
            'parameters'      => '{}',
            'action_id'       => 126,
            'execution_order' => 4,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 2, "mvt":1}',
            'action_id'       => 126,
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        // --- HARPONNAGE (ID 127) ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"min":2}',
            'action_id'       => 127,
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresWeaponType',
            'parameters'      => '{"type": ["jet"]}',
            'action_id'       => 127,
            'execution_order' => 1,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresAmmo',
            'parameters'      => '{}',
            'action_id'       => 127,
            'execution_order' => 4,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 8, "mvt":1}',
            'action_id'       => 127,
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'DistanceCompute',
            'parameters'      => '{"actorRollType":"ct", "targetRollType": "cc/agi"}',
            'action_id'       => 127,
            'execution_order' => 10,
            'blocking'        => 0,
        ],
        // --- PARADE (ID 128) ---
        [
            'conditionType'   => 'ForbidIfHasEffect',
            'parameters'      => '{"actorEffect": "parade"}',
            'action_id'       => 128,
            'execution_order' => 4,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 8}',
            'action_id'       => 128,
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        // --- PAS DE COTE (ID 129) ---
        [
            'conditionType'   => 'ForbidIfHasEffect',
            'parameters'      => '{"actorEffect": "pas_de_cote"}',
            'action_id'       => 129,
            'execution_order' => 4,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 8}',
            'action_id'       => 129,
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        // --- DISSIPATION (ID 130) ---
        [
            'conditionType'   => 'ForbidIfHasEffect',
            'parameters'      => '{"actorEffect": "dissipation"}',
            'action_id'       => 130,
            'execution_order' => 4,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 8}',
            'action_id'       => 130,
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        // --- DEDOUBLEMENT (ID 131) ---
        [
            'conditionType'   => 'ForbidIfHasEffect',
            'parameters'      => '{"actorEffect": "dedoublement"}',
            'action_id'       => 131,
            'execution_order' => 4,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 10}',
            'action_id'       => 131,
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        
    ];

    private const ACTION_OUTCOMES = [
        // --- ENCAISSER (ID 125) ---
        [
            'apply_to'   => 'target',
            'name'       => 'buff_encaisse',
            'on_success' => 1,
            'action_id'  => 125,
        ],
        // --- TIR PUISSANT (ID 126) ---
        [
            'apply_to'   => 'target',
            'name'       => 'dtechnique_tir_puissant',
            'on_success' => 1,
            'action_id'  => 126,
        ],
        // --- HARPONNAGE (ID 127) ---
        [
            'apply_to'   => 'target',
            'name'       => 'dtechnique_harponnage',
            'on_success' => 1,
            'action_id'  => 127,
        ],
        // --- PARADE (ID 128) ---
        [
            'apply_to'   => 'target',
            'name'       => 'buff_parade',
            'on_success' => 1,
            'action_id'  => 128,
        ],
        // --- PAS DE COTE (ID 129) ---
        [
            'apply_to'   => 'target',
            'name'       => 'buff_parade',
            'on_success' => 1,
            'action_id'  => 129,
        ],
        // --- DISSIPATION (ID 130) ---
        [
            'apply_to'   => 'target',
            'name'       => 'buff_parade',
            'on_success' => 1,
            'action_id'  => 130,
        ],
        // --- DEDOUBLEMENT (ID 131) ---
        [
            'apply_to'   => 'target',
            'name'       => 'buff_parade',
            'on_success' => 1,
            'action_id'  => 131,
        ],
        
    ];

    private const OUTCOME_INSTRUCTIONS = [
        // --- ENCAISSER (Outcome ID 139) ---
        [
            'type'       => 'applystatus',
            'parameters' => '{ "encaisse": true, "stackable": false, "value": 1, "player": "target", "duration": 1}',
            'orderIndex' => 10,
            'outcome_id' => 139,
        ],
        // --- TIR PUISSANT (Outcome ID 140) ---
        [
            'type'       => 'teleport',
            'parameters' => '{ "coords": "def-opposite" }',
            'orderIndex' => 2,
            'outcome_id' => 140,
        ],
        [
            'type'       => 'applystatus',
            'parameters' => '{ "stabilite": true, "stackable": true, "value": 4, "player": "target", "duration": 1}',
            'orderIndex' => 10,
            'outcome_id' => 140,
        ],
        // --- HARPONNAGE (Outcome ID 141) ---
        [
            'type'       => 'teleport',
            'parameters' => '{ "coords": "harpoon" }',
            'orderIndex' => 2,
            'outcome_id' => 141,
        ],
        [
            'type'       => 'lifeloss',
            'parameters' => '{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "distance": true }',
            'orderIndex' => 3,
            'outcome_id' => 141,
        ],
        [
            'type'       => 'applystatus',
            'parameters' => '{ "stabilite": true, "stackable": true, "value": 4, "player": "target", "duration": 1}',
            'orderIndex' => 10,
            'outcome_id' => 141,
        ],
        // --- PARADE (Outcome ID 142) ---
        [
            'type'       => 'applystatus',
            'parameters' => '{"effect":"parade","apply":true,"player":"actor","duration":0}',
            'orderIndex' => 0,
            'outcome_id' => 142,
        ],
        // --- PAS DE COTE (Outcome ID 143) ---
        [
            'type'       => 'applystatus',
            'parameters' => '{"effect":"pas_de_cote","apply":true,"player":"actor","duration":0}',
            'orderIndex' => 0,
            'outcome_id' => 143,
        ],
        // --- DISSIPATION (Outcome ID 144) ---
        [
            'type'       => 'applystatus',
            'parameters' => '{"effect":"dissipation","apply":true,"player":"actor","duration":0}',
            'orderIndex' => 0,
            'outcome_id' => 144,
        ],
        // --- DEDOUBLEMENT (Outcome ID 145) ---
        [
            'type'       => 'applystatus',
            'parameters' => '{"effect":"dedoublement","apply":true,"player":"actor","duration":0}',
            'orderIndex' => 0,
            'outcome_id' => 145,
        ],
        
    ];

    public function getDescription(): string
    {
        return 'Ajout des actions Encaisser et Tir puissant, ainsi que leurs propriétés techniques (conditions, outcomes, instructions).';
    }

    public function up(Schema $schema): void
    {
        // 1. Ajout des actions
        foreach (self::ACTIONS_DATA as $action) {
            $columns = implode(', ', array_keys($action));
            $placeholders = implode(', ', array_fill(0, count($action), '?'));
            $this->addSql(
                "INSERT INTO actions ($columns) VALUES ($placeholders)",
                array_values($action)
            );
        }

        // 2. Ajout des conditions d'action
        foreach (self::ACTION_CONDITIONS as $condition) {
            $cols = implode(', ', array_keys($condition));
            $vals = implode(', ', array_fill(0, count($condition), '?'));
            $this->addSql(
                "INSERT INTO action_conditions ($cols) VALUES ($vals)",
                array_values($condition)
            );
        }

        // 3. Ajout des résultats d'action (outcomes)
        foreach (self::ACTION_OUTCOMES as $outcome) {
            $cols = implode(', ', array_keys($outcome));
            $vals = implode(', ', array_fill(0, count($outcome), '?'));
            $this->addSql(
                "INSERT INTO action_outcomes ($cols) VALUES ($vals)",
                array_values($outcome)
            );
        }

        // 4. Ajout des instructions
        foreach (self::OUTCOME_INSTRUCTIONS as $instruction) {
            $cols = implode(', ', array_keys($instruction));
            $vals = implode(', ', array_fill(0, count($instruction), '?'));
            $this->addSql(
                "INSERT INTO outcome_instructions ($cols) VALUES ($vals)",
                array_values($instruction)
            );
        }
    }

    public function down(Schema $schema): void
    {
        // On effectue le rollback dans l'ordre inverse des insertions

        // 4. Suppression des instructions (liées aux nouvelles actions)
        $this->addSql('DELETE FROM outcome_instructions WHERE outcome_id IN (139, 140, 141)');

        // 3. Suppression des outcomes (liés aux nouvelles actions)
        $this->addSql('DELETE FROM action_outcomes WHERE action_id IN (125, 126, 127)');

        // 2. Suppression des conditions (liées aux nouvelles actions)
        $this->addSql('DELETE FROM action_conditions WHERE action_id IN (125, 126, 127)');

        // 1. Suppression des actions
        foreach (self::ACTIONS_DATA as $action) {
            $this->addSql(
                'DELETE FROM actions WHERE name = ?',
                [$action['name']]
            );
        }
    }
}