<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout de nouvelles actions pour la saison 3 et mise à jour de conditions d'action existantes
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
            'type'         => 'buff',
            'display_name' => 'Parade',
            'text'         => 'Pare la prochaine attaque de corps-à-corps si vous êtes équipé d\'une arme de corps-à-corps.',
            'level'        => 1,
            'category'     => 'survival-buff',
            'cost'         => '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">8 PM</span>',
        ],
        [
            'name'         => 'pas_de_cote',
            'icon'         => 'ra-player-dodge',
            'type'         => 'buff',
            'display_name' => 'Pas de côté',
            'text'         => 'Esquive le prochain tir en vous déplaçant sur une case adjacente.',
            'level'        => 1,
            'category'     => 'survival-buff',
            'cost'         => '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">8 PM</span>',
        ],
        [
            'name'         => 'dissipation',
            'icon'         => 'ra-lava',
            'type'         => 'buff',
            'display_name' => 'Dissipation',
            'text'         => 'Dissipe le prochain sort lancé sur vous.',
            'level'        => 1,
            'category'     => 'survival-buff',
            'cost'         => '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">8 PM</span>',
        ],
        [
            'name'         => 'dedoublement',
            'icon'         => 'ra-double-team',
            'type'         => 'buff',
            'display_name' => 'Dédoublement',
            'text'         => 'Crée un double illusoire qui va encaisser la prochaine attaque à votre place.',
            'level'        => 3,
            'category'     => 'survival-buff',
            'cost'         => '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">10 PM</span>',
        ],
        [
            'name'         => 'posture_defensive',
            'icon'         => 'ra-castle-flag',
            'type'         => 'buff',
            'display_name' => 'Posture défensive',
            'text'         => 'Protection(x2)',
            'level'        => 2,
            'category'     => 'survival-buff',
            'cost'         => '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">4 PM</span>',
        ],
        [
            'name'         => 'jet_brutal',
            'icon'         => 'ra-splash',
            'type'         => 'technique',
            'display_name' => 'Jet brutal',
            'text'         => 'Avec une arme de jet, ignore les malus de distance aux dégâts.',
            'level'        => 3,
            'category'     => 'distance-off',
            'cost'         => '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">6 PM</span>',
        ],
        [
            'name'         => 'coup_grace',
            'icon'         => 'ra-decapitation',
            'type'         => 'technique',
            'display_name' => 'Coup de grâce',
            'text'         => 'Inflige +1 Dmg par tranche de 25 PV manquants à la cible.',
            'level'        => 3,
            'category'     => 'melee-off',
            'cost'         => '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">6 PM</span>',
        ],
        [
            'name'         => 'opportunisme',
            'icon'         => 'ra-player-shot',
            'type'         => 'technique',
            'display_name' => 'Opportunisme',
            'text'         => 'Inflige +1 Dmg par tranche de 5 Malus de la cible.',
            'level'        => 3,
            'category'     => 'distance-off',
            'cost'         => '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">8 PM</span>',
        ],
        [
            'name'         => 'mine_esprit',
            'icon'         => 'ra-broken-skull',
            'type'         => 'spell',
            'display_name' => 'Mine de l\'esprit',
            'text'         => '+X Dmg. X vaut le nombre de PM manquants de la cible divisé par 5.',
            'level'        => 3,
            'category'     => 'spell-off',
            'cost'         => '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">8 PM</span>',
        ],
        [
            'name'         => 'arcane_maladroite',
            'icon'         => 'ra-sheep',
            'type'         => 'spell',
            'display_name' => 'Arcane maladroite',
            'text'         => '-6 pour toucher, +3 Dmg',
            'level'        => 3,
            'category'     => 'spell-off',
            'cost'         => '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">6 PM</span>',
        ],
        [
            'name'         => 'aiguilles',
            'icon'         => 'ra-focused-lightning',
            'type'         => 'spell',
            'display_name' => 'Aiguilles',
            'text'         => '+6 Dmg',
            'level'        => 3,
            'category'     => 'spell-off',
            'cost'         => '<span style="color: #8e44ad;">1 A</span>, <span style="color: #2980b9;">8 PM</span>',
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
            'parameters'      => '{"a": 1, "pm": [["voie_eau",5],["none",8]], "mvt":1}',
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
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"max":0}',
            'action_id'       => 128,
            'execution_order' => 0,
            'blocking'        => 1,
        ],
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
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"max":0}',
            'action_id'       => 129,
            'execution_order' => 0,
            'blocking'        => 1,
        ],
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
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"max":0}',
            'action_id'       => 130,
            'execution_order' => 0,
            'blocking'        => 1,
        ],
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
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"max":0}',
            'action_id'       => 131,
            'execution_order' => 0,
            'blocking'        => 1,
        ],
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
        // --- POSTURE DEFENSIVE (ID 132) ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"max":0}',
            'action_id'       => 132,
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 4}',
            'action_id'       => 132,
            'execution_order' => 9,
            'blocking'        => 0,
        ],
        // --- JET BRUTAL (ID 133) ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"min":2}',
            'action_id'       => 133,
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresWeaponType',
            'parameters'      => '{"type": ["jet"]}',
            'action_id'       => 133,
            'execution_order' => 1,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresAmmo',
            'parameters'      => '{}',
            'action_id'       => 133,
            'execution_order' => 4,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": [["voie_eau",5],["none",8]]}',
            'action_id'       => 133,
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        // --- COUP DE GRACE (ID 134) ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"max":1}',
            'action_id'       => 134,
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresWeaponType',
            'parameters'      => '{"type": ["melee"]}',
            'action_id'       => 134,
            'execution_order' => 1,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": [["maitre_lame",4],["none",6]]}',
            'action_id'       => 134,
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'MeleeCompute',
            'parameters'      => '{"actorRollType":"cc", "targetRollType": "cc/agi"}',
            'action_id'       => 134,
            'execution_order' => 10,
            'blocking'        => 0,
        ],
        // --- OPPORTUNISME (ID 135) ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"min":2}',
            'action_id'       => 135,
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresWeaponType',
            'parameters'      => '{"type": ["tir","jet"]}',
            'action_id'       => 135,
            'execution_order' => 1,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresAmmo',
            'parameters'      => '{}',
            'action_id'       => 135,
            'execution_order' => 4,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": [["voie_eau",5],["none",8]]}',
            'action_id'       => 135,
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'DistanceCompute',
            'parameters'      => '{"actorRollType":"ct", "targetRollType": "cc/agi"}',
            'action_id'       => 135,
            'execution_order' => 10,
            'blocking'        => 0,
        ],
        // --- MINE ESPRIT (ID 136) ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"min":2}',
            'action_id'       => 136,
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 8}',
            'action_id'       => 136,
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'SpellCompute',
            'parameters'      => '{"actorRollType":"fm", "targetRollType": "fm"}',
            'action_id'       => 136,
            'execution_order' => 10,
            'blocking'        => 0,
        ],
        // --- ARCANE MALADROITE (ID 137) ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"max":1}',
            'action_id'       => 137,
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 6}',
            'action_id'       => 137,
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'SpellCompute',
            'parameters'      => '{"actorRollType":"fm", "targetRollType": "fm", "actorRollBonus" : -6}',
            'action_id'       => 137,
            'execution_order' => 10,
            'blocking'        => 0,
        ],
        // --- AIGUILLES (ID 138) ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"min":2}',
            'action_id'       => 138,
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 8}',
            'action_id'       => 138,
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'SpellCompute',
            'parameters'      => '{"actorRollType":"fm", "targetRollType": "fm"}',
            'action_id'       => 138,
            'execution_order' => 10,
            'blocking'        => 0,
        ],
    ];

    private const ACTION_CONDITION_UPDATES = [
        [
            'action_id'      => 58,
            'conditionType'  => 'RequiresTraitValue',
            'old_parameters' => '{"a":1, "pm":10}',
            'new_parameters' => '{"a": 1, "pm": [["voie_eau",7],["none",10]]}',
        ],
        [
            'action_id'      => 47,
            'conditionType'  => 'RequiresTraitValue',
            'old_parameters' => '{"a":1, "pm":2}',
            'new_parameters' => '{"a":1, "pm":[["maitre_lame",1],["none",2]]}',
        ],
        [
            'action_id'      => 48,
            'conditionType'  => 'RequiresTraitValue',
            'old_parameters' => '{"a":1, "pm":2}',
            'new_parameters' => '{"a":1, "pm":[["maitre_lame",1],["none",2]]}',
        ],
        [
            'action_id'      => 49,
            'conditionType'  => 'RequiresTraitValue',
            'old_parameters' => '{"a":1, "pm":6}',
            'new_parameters' => '{"a":1, "pm":[["maitre_lame",4],["none",6]]}',
        ],
        [
            'action_id'      => 50,
            'conditionType'  => 'RequiresTraitValue',
            'old_parameters' => '{"a":1, "pm":2}',
            'new_parameters' => '{"a":1, "pm":[["maitre_lame",1],["none",2]]}',
        ],
        [
            'action_id'      => 52,
            'conditionType'  => 'RequiresTraitValue',
            'old_parameters' => '{"a":1, "pm":8}',
            'new_parameters' => '{"a":1, "pm":[["maitre_lame",6],["none",8]]}',
        ],
        [
            'action_id'      => 77,
            'conditionType'  => 'RequiresTraitValue',
            'old_parameters' => '{"a":1, "pm":2}',
            'new_parameters' => '{"a":1, "pm":[["maitre_lame",1],["none",2]]}',
        ],
        [
            'action_id'      => 107,
            'conditionType'  => 'RequiresTraitValue',
            'old_parameters' => '{"a":1, "pm":4}',
            'new_parameters' => '{"a":1, "pm":[["maitre_lame",2],["none",4]]}',
        ],
        [
            'action_id'      => 109,
            'conditionType'  => 'RequiresTraitValue',
            'old_parameters' => '{"a":1, "pm":4}',
            'new_parameters' => '{"a":1, "pm":[["maitre_lame",2],["none",4]]}',
        ],
        [
            'action_id'      => 79,
            'conditionType'  => 'RequiresTraitValue',
            'old_parameters' => '{"a":1, "pm":15, "mvt":1}',
            'new_parameters' => '{"a":1, "pm":[["maitre_lame",11],["none",15]], "mvt":1}',
        ],
    ];

    private const ACTION_OUTCOMES = [
        // --- ENCAISSER (ID 125) ---
        [
            'apply_to'   => 'self',
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
            'apply_to'   => 'self',
            'name'       => 'buff_parade',
            'on_success' => 1,
            'action_id'  => 128,
        ],
        // --- PAS DE COTE (ID 129) ---
        [
            'apply_to'   => 'self',
            'name'       => 'buff_pas_de_cote',
            'on_success' => 1,
            'action_id'  => 129,
        ],
        // --- DISSIPATION (ID 130) ---
        [
            'apply_to'   => 'self',
            'name'       => 'buff_dissipation',
            'on_success' => 1,
            'action_id'  => 130,
        ],
        // --- DEDOUBLEMENT (ID 131) ---
        [
            'apply_to'   => 'self',
            'name'       => 'buff_dedoublement',
            'on_success' => 1,
            'action_id'  => 131,
        ],
        // --- POSTURE DEFENSIVE (ID 132) ---
        [
            'apply_to'   => 'self',
            'name'       => 'buff_posture_defensive',
            'on_success' => 1,
            'action_id'  => 132,
        ],
        // --- JET BRUTAL (ID 133) ---
        [
            'apply_to'   => 'target',
            'name'       => 'dtechnique_jet_brutal',
            'on_success' => 1,
            'action_id'  => 133,
        ],
        // --- COUP DE GRACE (ID 134) ---
        [
            'apply_to'   => 'target',
            'name'       => 'mtechnique_coup_grace',
            'on_success' => 1,
            'action_id'  => 134,
        ],
        // --- OPPORTUNISME (ID 135) ---
        [
            'apply_to'   => 'target',
            'name'       => 'dtechnique_opportunisme',
            'on_success' => 1,
            'action_id'  => 135,
        ],
        // --- MINE ESPRIT (ID 136) ---
        [
            'apply_to'   => 'target',
            'name'       => 'spell_mine_esprit',
            'on_success' => 1,
            'action_id'  => 136,
        ],
        // --- ARCANE MALADROITE (ID 137) ---
        [
            'apply_to'   => 'target',
            'name'       => 'spell_arcane_maladroite',
            'on_success' => 1,
            'action_id'  => 137,
        ],
        // --- AIGUILLES (ID 138) ---
        [
            'apply_to'   => 'target',
            'name'       => 'spell_aiguilles',
            'on_success' => 1,
            'action_id'  => 138,
        ],
    ];

    private const OUTCOME_INSTRUCTIONS = [
        // --- SAUT D'ATTAQUE (Outcome ID 89) ---
        [
            'type'       => 'lifeloss',
            'parameters' => '{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "saut": true }',
            'orderIndex' => 1,
            'outcome_id' => 89,
        ],
        [
            'type'       => 'teleport',
            'parameters' => '{ "coords": "target" }',
            'orderIndex' => 3,
            'outcome_id' => 89,
        ],
        // --- ENCAISSER (Outcome ID 139) ---
        [
            'type'       => 'applystatus',
            'parameters' => '{ "encaisse": true, "stackable": false, "value": 1, "player": "actor", "duration": 1}',
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
        // --- POSTURE DEFENSIVE (Outcome ID 146) ---
        [
            'type'       => 'applystatus',
            'parameters' => '{"effect": "protection", "apply": true, "stackable": false, "value": 2, "player": "actor", "duration": 1}',
            'orderIndex' => 0,
            'outcome_id' => 146,
        ],
        // --- JET BRUTAL (Outcome ID 147) ---
        [
            'type'       => 'lifeloss',
            'parameters' => '{ "actorDamagesTrait": "f", "targetDamagesTrait": "e" }',
            'orderIndex' => 3,
            'outcome_id' => 147,
        ],
        // --- COUP GRACE (Outcome ID 148) ---
        [
            'type'       => 'lifeloss',
            'parameters' => '{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "bonusTargetTraitDamages": ["pv",25] }',
            'orderIndex' => 3,
            'outcome_id' => 148,
        ],
        // --- OPPORTUNISME (Outcome ID 149) ---
        [
            'type'       => 'lifeloss',
            'parameters' => '{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "distance": true, "bonusTargetTraitDamages": ["malus",5] }',
            'orderIndex' => 3,
            'outcome_id' => 149,
        ],
        // --- MINE ESPRIT (Outcome ID 150) ---
        [
            'type'       => 'lifeloss',
            'parameters' => '{ "actorDamagesTrait": "pui", "targetDamagesTrait": "res", "bonusTargetTraitDamages": ["pm",5] }',
            'orderIndex' => 3,
            'outcome_id' => 150,
        ],
        // --- ARCANE MALADROITE (Outcome ID 151) ---
        [
            'type'       => 'lifeloss',
            'parameters' => '{ "actorDamagesTrait": "pui", "targetDamagesTrait": "res", "bonusDamagesTrait": 3 }',
            'orderIndex' => 3,
            'outcome_id' => 151,
        ],
        // --- AIGUILLES (Outcome ID 152) ---
        [
            'type'       => 'lifeloss',
            'parameters' => '{ "actorDamagesTrait": "pui", "targetDamagesTrait": "res", "bonusDamagesTrait": 6 }',
            'orderIndex' => 3,
            'outcome_id' => 152,
        ],
    ];

    public function getDescription(): string
    {
        return 'Ajout de nouvelles actions, mise à jour des conditions existantes et de leurs propriétés techniques (conditions, outcomes, instructions).';
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

        // 3. Mise à jour des conditions d'action existantes
        foreach (self::ACTION_CONDITION_UPDATES as $update) {
            $this->addSql(
                'UPDATE action_conditions SET parameters = ? WHERE action_id = ? AND conditionType = ?',
                [
                    $update['new_parameters'],
                    $update['action_id'],
                    $update['conditionType'],
                ]
            );
        }

        // 4. Ajout des résultats d'action (outcomes)
        foreach (self::ACTION_OUTCOMES as $outcome) {
            $cols = implode(', ', array_keys($outcome));
            $vals = implode(', ', array_fill(0, count($outcome), '?'));
            $this->addSql(
                "INSERT INTO action_outcomes ($cols) VALUES ($vals)",
                array_values($outcome)
            );
        }

        // 5. Ajout des instructions
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
        // Rollback dans l'ordre inverse des opérations

        // 5. Suppression des instructions (liées aux nouvelles actions)
        $this->addSql('DELETE FROM outcome_instructions WHERE outcome_id IN (139, 140, 141, 142, 143, 144, 145, 146, 147)');

        // 4. Suppression des outcomes (liés aux nouvelles actions)
        $this->addSql('DELETE FROM action_outcomes WHERE action_id IN (125, 126, 127, 128, 129, 130, 131, 132, 133)');

        // 3. Annulation des mises à jour des conditions d'action
        foreach (self::ACTION_CONDITION_UPDATES as $update) {
            $this->addSql(
                'UPDATE action_conditions SET parameters = ? WHERE action_id = ? AND conditionType = ?',
                [
                    $update['old_parameters'],
                    $update['action_id'],
                    $update['conditionType'],
                ]
            );
        }

        // 2. Suppression des conditions (liées aux nouvelles actions)
        $this->addSql('DELETE FROM action_conditions WHERE action_id IN (125, 126, 127, 128, 129, 130, 131, 132, 133)');

        // 1. Suppression des actions
        foreach (self::ACTIONS_DATA as $action) {
            $this->addSql(
                'DELETE FROM actions WHERE name = ?',
                [$action['name']]
            );
        }
    }
}