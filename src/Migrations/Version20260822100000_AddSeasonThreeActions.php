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
        // --- ENCAISSER ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"max":0}',
            'action'       => 'encaisser',
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 6}',
            'action'       => 'encaisser',
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        // --- TIR PUISSANT ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"min":2}',
            'action'       => 'tir_puissant',
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresWeaponType',
            'parameters'      => '{"type": ["jet"]}',
            'action'       => 'tir_puissant',
            'execution_order' => 1,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresAmmo',
            'parameters'      => '{}',
            'action'       => 'tir_puissant',
            'execution_order' => 4,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 2, "mvt":1}',
            'action'       => 'tir_puissant',
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        // --- HARPONNAGE ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"min":2}',
            'action'       => 'harponnage',
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresWeaponType',
            'parameters'      => '{"type": ["jet"]}',
            'action'       => 'harponnage',
            'execution_order' => 1,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresAmmo',
            'parameters'      => '{}',
            'action'       => 'harponnage',
            'execution_order' => 4,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": [["voie_eau",5],["none",8]], "mvt":1}',
            'action'       => 'harponnage',
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'DistanceCompute',
            'parameters'      => '{"actorRollType":"ct", "targetRollType": "cc/agi"}',
            'action'       => 'harponnage',
            'execution_order' => 10,
            'blocking'        => 0,
        ],
        // --- PARADE ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"max":0}',
            'action'       => 'parade',
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'ForbidIfHasEffect',
            'parameters'      => '{"actorEffect": "parade"}',
            'action'       => 'parade',
            'execution_order' => 4,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 8}',
            'action'       => 'parade',
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        // --- PAS DE COTE ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"max":0}',
            'action'       => 'pas_de_cote',
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'ForbidIfHasEffect',
            'parameters'      => '{"actorEffect": "pas_de_cote"}',
            'action'       => 'pas_de_cote',
            'execution_order' => 4,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 8}',
            'action'       => 'pas_de_cote',
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        // --- DISSIPATION ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"max":0}',
            'action'       => 'dissipation',
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'ForbidIfHasEffect',
            'parameters'      => '{"actorEffect": "dissipation"}',
            'action'       => 'dissipation',
            'execution_order' => 4,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 8}',
            'action'       => 'dissipation',
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        // --- DEDOUBLEMENT ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"max":0}',
            'action'       => 'dedoublement',
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'ForbidIfHasEffect',
            'parameters'      => '{"actorEffect": "dedoublement"}',
            'action'       => 'dedoublement',
            'execution_order' => 4,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 10}',
            'action'       => 'dedoublement',
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        // --- POSTURE DEFENSIVE ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"max":0}',
            'action'       => 'posture_defensive',
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 4}',
            'action'       => 'posture_defensive',
            'execution_order' => 9,
            'blocking'        => 0,
        ],
        // --- JET BRUTAL ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"min":2}',
            'action'       => 'jet_brutal',
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresWeaponType',
            'parameters'      => '{"type": ["jet"]}',
            'action'       => 'jet_brutal',
            'execution_order' => 1,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresAmmo',
            'parameters'      => '{}',
            'action'       => 'jet_brutal',
            'execution_order' => 4,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": [["voie_eau",5],["none",8]]}',
            'action'       => 'jet_brutal',
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        // --- COUP DE GRACE ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"max":1}',
            'action'       => 'coup_grace',
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresWeaponType',
            'parameters'      => '{"type": ["melee"]}',
            'action'       => 'coup_grace',
            'execution_order' => 1,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": [["maitre_lame",4],["none",6]]}',
            'action'       => 'coup_grace',
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'MeleeCompute',
            'parameters'      => '{"actorRollType":"cc", "targetRollType": "cc/agi"}',
            'action'       => 'coup_grace',
            'execution_order' => 10,
            'blocking'        => 0,
        ],
        // --- OPPORTUNISME ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"min":2}',
            'action'       => 'opportunisme',
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresWeaponType',
            'parameters'      => '{"type": ["tir","jet"]}',
            'action'       => 'opportunisme',
            'execution_order' => 1,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresAmmo',
            'parameters'      => '{}',
            'action'       => 'opportunisme',
            'execution_order' => 4,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": [["voie_eau",5],["none",8]]}',
            'action'       => 'opportunisme',
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'DistanceCompute',
            'parameters'      => '{"actorRollType":"ct", "targetRollType": "cc/agi"}',
            'action'       => 'opportunisme',
            'execution_order' => 10,
            'blocking'        => 0,
        ],
        // --- MINE ESPRIT ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"min":2}',
            'action'       => 'mine_esprit',
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 8}',
            'action'       => 'mine_esprit',
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'SpellCompute',
            'parameters'      => '{"actorRollType":"fm", "targetRollType": "fm"}',
            'action'       => 'mine_esprit',
            'execution_order' => 10,
            'blocking'        => 0,
        ],
        // --- ARCANE MALADROITE ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"max":1}',
            'action'       => 'arcane_maladroite',
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 6}',
            'action'       => 'arcane_maladroite',
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'SpellCompute',
            'parameters'      => '{"actorRollType":"fm", "targetRollType": "fm", "actorRollBonus" : -6}',
            'action'       => 'arcane_maladroite',
            'execution_order' => 10,
            'blocking'        => 0,
        ],
        // --- AIGUILLES ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"min":2}',
            'action'       => 'aiguilles',
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 8}',
            'action'       => 'aiguilles',
            'execution_order' => 9,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'SpellCompute',
            'parameters'      => '{"actorRollType":"fm", "targetRollType": "fm"}',
            'action'       => 'aiguilles',
            'execution_order' => 10,
            'blocking'        => 0,
        ],
    ];

    private const ACTION_CONDITION_UPDATES = [
        [
            'action'      => 'aide',
            'conditionType'  => 'RequiresTraitValue',
            'old_parameters' => '{"a":1, "pm":10}',
            'new_parameters' => '{"a": 1, "pm": [["voie_eau",7],["none",10]]}',
        ],
        [
            'action'      => 'coup_ajuste',
            'conditionType'  => 'RequiresTraitValue',
            'old_parameters' => '{"a":1, "pm":2}',
            'new_parameters' => '{"a":1, "pm":[["maitre_lame",1],["none",2]]}',
        ],
        [
            'action'      => 'coup_epaule',
            'conditionType'  => 'RequiresTraitValue',
            'old_parameters' => '{"a":1, "pm":2}',
            'new_parameters' => '{"a":1, "pm":[["maitre_lame",1],["none",2]]}',
        ],
        [
            'action'      => 'saut_attaque',
            'conditionType'  => 'RequiresTraitValue',
            'old_parameters' => '{"a":1, "pm":6}',
            'new_parameters' => '{"a":1, "pm":[["maitre_lame",4],["none",6]]}',
        ],
        [
            'action'      => 'recuperation',
            'conditionType'  => 'RequiresTraitValue',
            'old_parameters' => '{"a":1, "pm":2}',
            'new_parameters' => '{"a":1, "pm":[["maitre_lame",1],["none",2]]}',
        ],
        [
            'action'      => 'restauration',
            'conditionType'  => 'RequiresTraitValue',
            'old_parameters' => '{"a":1, "pm":8}',
            'new_parameters' => '{"a":1, "pm":[["maitre_lame",6],["none",8]]}',
        ],
        [
            'action'      => 'attaque_drainante',
            'conditionType'  => 'RequiresTraitValue',
            'old_parameters' => '{"a":1, "pm":2}',
            'new_parameters' => '{"a":1, "pm":[["maitre_lame",1],["none",2]]}',
        ],
        [
            'action'      => 'frappe_tempe',
            'conditionType'  => 'RequiresTraitValue',
            'old_parameters' => '{"a":1, "pm":15, "mvt":1}',
            'new_parameters' => '{"a":1, "pm":[["maitre_lame",11],["none",15]], "mvt":1}',
        ],
    ];

    private const ACTION_OUTCOMES = [
        // --- ENCAISSER ---
        [
            'apply_to'   => 'self',
            'name'       => 'buff_encaisse',
            'on_success' => 1,
            'action'  => 'encaisser',
        ],
        // --- TIR PUISSANT ---
        [
            'apply_to'   => 'target',
            'name'       => 'dtechnique_tir_puissant',
            'on_success' => 1,
            'action'  => 'tir_puissant',
        ],
        // --- HARPONNAGE ---
        [
            'apply_to'   => 'target',
            'name'       => 'dtechnique_harponnage',
            'on_success' => 1,
            'action'  => 'harponnage',
        ],
        // --- PARADE ---
        [
            'apply_to'   => 'self',
            'name'       => 'buff_parade',
            'on_success' => 1,
            'action'  => 'parade',
        ],
        // --- PAS DE COTE ---
        [
            'apply_to'   => 'self',
            'name'       => 'buff_pas_de_cote',
            'on_success' => 1,
            'action'  => 'pas_de_cote',
        ],
        // --- DISSIPATION ---
        [
            'apply_to'   => 'self',
            'name'       => 'buff_dissipation',
            'on_success' => 1,
            'action'  => 'dissipation',
        ],
        // --- DEDOUBLEMENT ---
        [
            'apply_to'   => 'self',
            'name'       => 'buff_dedoublement',
            'on_success' => 1,
            'action'  => 'dedoublement',
        ],
        // --- POSTURE DEFENSIVE ---
        [
            'apply_to'   => 'self',
            'name'       => 'buff_posture_defensive',
            'on_success' => 1,
            'action'  => 'posture_defensive',
        ],
        // --- JET BRUTAL ---
        [
            'apply_to'   => 'target',
            'name'       => 'dtechnique_jet_brutal',
            'on_success' => 1,
            'action'  => 'jet_brutal',
        ],
        // --- COUP DE GRACE ---
        [
            'apply_to'   => 'target',
            'name'       => 'mtechnique_coup_grace',
            'on_success' => 1,
            'action'  => 'coup_grace',
        ],
        // --- OPPORTUNISME ---
        [
            'apply_to'   => 'target',
            'name'       => 'dtechnique_opportunisme',
            'on_success' => 1,
            'action'  => 'opportunisme',
        ],
        // --- MINE ESPRIT ---
        [
            'apply_to'   => 'target',
            'name'       => 'spell_mine_esprit',
            'on_success' => 1,
            'action'  => 'mine_esprit',
        ],
        // --- ARCANE MALADROITE ---
        [
            'apply_to'   => 'target',
            'name'       => 'spell_arcane_maladroite',
            'on_success' => 1,
            'action'  => 'arcane_maladroite',
        ],
        // --- AIGUILLES ---
        [
            'apply_to'   => 'target',
            'name'       => 'spell_aiguilles',
            'on_success' => 1,
            'action'  => 'aiguilles',
        ],
    ];

    private const OUTCOME_INSTRUCTIONS = [
        // --- SAUT D'ATTAQUE ---
        [
            'type'       => 'lifeloss',
            'parameters' => '{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "saut": true }',
            'orderIndex' => 1,
            'outcome' => 'mtechnique_sautattaque',
        ],
        [
            'type'       => 'teleport',
            'parameters' => '{ "coords": "target" }',
            'orderIndex' => 3,
            'outcome' => 'mtechnique_sautattaque',
        ],
        // --- ENCAISSER ---
        [
            'type'       => 'applystatus',
            'parameters' => '{ "encaisse": true, "stackable": false, "value": 1, "player": "actor", "duration": 1}',
            'orderIndex' => 10,
            'outcome' => 'buff_encaisse',
        ],
        // --- TIR PUISSANT ---
        [
            'type'       => 'teleport',
            'parameters' => '{ "coords": "dist-opposite" }',
            'orderIndex' => 2,
            'outcome' => 'dtechnique_tir_puissant',
        ],
        [
            'type'       => 'applystatus',
            'parameters' => '{ "stabilite": true, "stackable": true, "value": 4, "player": "target", "duration": 1}',
            'orderIndex' => 10,
            'outcome' => 'dtechnique_tir_puissant',
        ],
        // --- HARPONNAGE ---
        [
            'type'       => 'teleport',
            'parameters' => '{ "coords": "harpoon" }',
            'orderIndex' => 2,
            'outcome' => 'dtechnique_harponnage',
        ],
        [
            'type'       => 'lifeloss',
            'parameters' => '{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "distance": true }',
            'orderIndex' => 3,
            'outcome' => 'dtechnique_harponnage',
        ],
        [
            'type'       => 'applystatus',
            'parameters' => '{ "stabilite": true, "stackable": true, "value": 4, "player": "target", "duration": 1}',
            'orderIndex' => 10,
            'outcome' => 'dtechnique_harponnage',
        ],
        // --- PARADE ---
        [
            'type'       => 'applystatus',
            'parameters' => '{"effect":"parade","apply":true,"player":"actor","duration":0}',
            'orderIndex' => 0,
            'outcome' => 'buff_parade',
        ],
        // --- PAS DE COTE ---
        [
            'type'       => 'applystatus',
            'parameters' => '{"effect":"pas_de_cote","apply":true,"player":"actor","duration":0}',
            'orderIndex' => 0,
            'outcome' => 'buff_pas_de_cote',
        ],
        // --- DISSIPATION ---
        [
            'type'       => 'applystatus',
            'parameters' => '{"effect":"dissipation","apply":true,"player":"actor","duration":0}',
            'orderIndex' => 0,
            'outcome' => 'buff_dissipation',
        ],
        // --- DEDOUBLEMENT ---
        [
            'type'       => 'applystatus',
            'parameters' => '{"effect":"dedoublement","apply":true,"player":"actor","duration":0}',
            'orderIndex' => 0,
            'outcome' => 'buff_dedoublement',
        ],
        // --- POSTURE DEFENSIVE ---
        [
            'type'       => 'applystatus',
            'parameters' => '{"effect": "protection", "apply": true, "stackable": false, "value": 2, "player": "actor", "duration": 1}',
            'orderIndex' => 0,
            'outcome' => 'buff_posture_defensive',
        ],
        // --- JET BRUTAL ---
        [
            'type'       => 'lifeloss',
            'parameters' => '{ "actorDamagesTrait": "f", "targetDamagesTrait": "e" }',
            'orderIndex' => 3,
            'outcome' => 'dtechnique_jet_brutal',
        ],
        // --- COUP GRACE ---
        [
            'type'       => 'lifeloss',
            'parameters' => '{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "bonusTargetTraitDamages": ["pv",25] }',
            'orderIndex' => 3,
            'outcome' => 'mtechnique_coup_grace',
        ],
        // --- OPPORTUNISME ---
        [
            'type'       => 'lifeloss',
            'parameters' => '{ "actorDamagesTrait": "f", "targetDamagesTrait": "e", "distance": true, "bonusTargetTraitDamages": ["malus",5] }',
            'orderIndex' => 3,
            'outcome' => 'dtechnique_opportunisme',
        ],
        // --- MINE ESPRIT ---
        [
            'type'       => 'lifeloss',
            'parameters' => '{ "actorDamagesTrait": "pui", "targetDamagesTrait": "res", "bonusTargetTraitDamages": ["pm",5] }',
            'orderIndex' => 3,
            'outcome' => 'spell_mine_esprit',
        ],
        // --- ARCANE MALADROITE ---
        [
            'type'       => 'lifeloss',
            'parameters' => '{ "actorDamagesTrait": "pui", "targetDamagesTrait": "res", "bonusDamagesTrait": 3 }',
            'orderIndex' => 3,
            'outcome' => 'spell_arcane_maladroite',
        ],
        // --- AIGUILLES ---
        [
            'type'       => 'lifeloss',
            'parameters' => '{ "actorDamagesTrait": "pui", "targetDamagesTrait": "res", "bonusDamagesTrait": 6 }',
            'orderIndex' => 3,
            'outcome' => 'spell_aiguilles',
        ],
    ];

    public function getDescription(): string
    {
        return 'Ajout de nouvelles actions, mise à jour des conditions existantes et de leurs propriétés techniques (conditions, outcomes, instructions).';
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

        foreach (self::ACTION_CONDITION_UPDATES as $u) {
            $this->addSql(
                'UPDATE action_conditions c JOIN actions a ON a.id = c.action_id
                 SET c.parameters = ? WHERE a.name = ? AND c.conditionType = ?',
                [$u['new_parameters'], $u['action'], $u['conditionType']]
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

        foreach (self::ACTION_CONDITION_UPDATES as $u) {
            $this->addSql(
                'UPDATE action_conditions c JOIN actions a ON a.id = c.action_id
                 SET c.parameters = ? WHERE a.name = ? AND c.conditionType = ?',
                [$u['old_parameters'], $u['action'], $u['conditionType']]
            );
        }

        $this->addSql("DELETE c FROM action_conditions c JOIN actions a ON a.id = c.action_id WHERE a.name IN ($in)", $names);
        $this->addSql("DELETE FROM actions WHERE name IN ($in)", $names);
    }
}
