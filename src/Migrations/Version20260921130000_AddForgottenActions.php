<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout d'actions oubliées et quelques updates
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
        [
            'name'         => 'pic_magique',
            'icon'         => 'ra-fast-ship',
            'type'         => 'spell',
            'display_name' => 'Pic magique',
            'text'         => '+3 Dmg',
            'level'        => 1,
            'category'     => 'spell-off',
            'icon_color'   => 'rouge',
        ],
    ];

    private const ACTION_CONDITIONS = [
        // --- MIGRAINE ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"min":2}',
            'action'          => 'migraine',
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 4}',
            'action'          => 'migraine',
            'execution_order' => 5,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'SpellCompute',
            'parameters'      => '{"actorRollType":"fm", "targetRollType": "fm"}',
            'action'          => 'migraine',
            'execution_order' => 7,
            'blocking'        => 0,
        ],
        // --- DUEL MENTAL ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"min":2}',
            'action'          => 'duel_mental',
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 4}',
            'action'          => 'duel_mental',
            'execution_order' => 5,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'SpellPureCompute',
            'parameters'      => '{"actorRollType":"fm", "targetRollType": "fm"}',
            'action'          => 'duel_mental',
            'execution_order' => 7,
            'blocking'        => 0,
        ],
        // --- PIC MAGIQUE ---
        [
            'conditionType'   => 'RequiresDistance',
            'parameters'      => '{"min":2}',
            'action'          => 'pic_magique',
            'execution_order' => 0,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'RequiresTraitValue',
            'parameters'      => '{"a": 1, "pm": 4}',
            'action'          => 'pic_magique',
            'execution_order' => 5,
            'blocking'        => 1,
        ],
        [
            'conditionType'   => 'SpellCompute',
            'parameters'      => '{"actorRollType":"fm", "targetRollType": "fm"}',
            'action'          => 'pic_magique',
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
            'action'     => 'migraine',
        ],
        // --- DUEL MENTAL ---
        [
            'apply_to'   => 'target',
            'name'       => 'mal_duelmental',
            'on_success' => 1,
            'action'     => 'duel_mental',
        ],
        // --- PIC MAGIQUE ---
        [
            'apply_to'   => 'target',
            'name'       => 'spell_pic_magique',
            'on_success' => 1,
            'action'     => 'pic_magique',
        ],
    ];

    private const OUTCOME_INSTRUCTIONS = [
        // --- MIGRAINE ---
        [
            'type'       => 'manaloss',
            'parameters' => '{ "lossType": "carac", "value":"pui", "typeDivisor":1 }',
            'orderIndex' => 1,
            'outcome'    => 'mal_migraine',
        ],
        // --- DUEL MENTAL ---
        [
            'type'       => 'manaloss',
            'parameters' => '{ "lossType": "difference" }',
            'orderIndex' => 1,
            'outcome'    => 'mal_duelmental',
        ],
        // --- PIC MAGIQUE ---
        [
            'type'       => 'lifeloss',
            'parameters' => '{"actorDamagesTrait": "pui", "targetDamagesTrait": "res", "bonusDamagesTrait": 3}',
            'orderIndex' => 1,
            'outcome'    => 'spell_pic_magique',
        ],
    ];

    private const OLD_MINE_ESPRIT_TEXT = '+X Dmg. X vaut le nombre de PM manquants de la cible divisé par 5.';
    private const NEW_MINE_ESPRIT_TEXT = 'Inflige +1 Dmg par tranche de 5 PM manquant de la cible.';

    private const OLD_ENCAISSE_TEXT = 'Encaisse(1)';
    private const NEW_ENCAISSE_TEXT = 'Encaisse(0)';

    private const OLD_PARADE_TEXT = 'Pare la prochaine attaque de corps-à-corps si vous êtes équipé d\'une arme de corps-à-corps.';
    private const NEW_PARADE_TEXT = 'Pare la prochaine attaque de corps-à-corps si vous êtes équipé d\'une arme de corps-à-corps. (effet invisible sur la carte de personnage)';

    private const OLD_DISSIPATION_TEXT = 'Dissipe le prochain sort lancé sur vous.';
    private const NEW_DISSIPATION_TEXT = 'Dissipe le prochain sort lancé sur vous (effet invisible sur la carte de personnage)';

    private const OLD_PAS_DE_COTE_TEXT = 'Esquive le prochain tir en vous déplaçant sur une case adjacente.';
    private const NEW_PAS_DE_COTE_TEXT = 'Esquive le prochain tir en vous déplaçant sur une case adjacente. (effet invisible sur la carte de personnage)';

    private const OLD_VOIE_EAU_TEXT = '-2 aux coûts en PM des attaques/techniques basées sur la CT, min 1';
    private const NEW_VOIE_EAU_TEXT = '-3 aux coûts en PM des Techniques à coût supérieur ou égal à 8 PM avec des armes de Jet';

    private const OLD_MAITRE_ARCHER_TEXT = 'Gagne +1Dmg sur les tirs avec arme à munition tous les 7 Mvt max';
    private const NEW_MAITRE_ARCHER_TEXT = 'Gagne +1 Dmg sur les tirs avec arme à munition tous les 7 Mvt max';

    private const OLD_SAUT_ATTAQUE_TEXT = 'Saute sur la cible et l\'attaque au contact.';
    private const NEW_SAUT_ATTAQUE_TEXT = 'Saute sur la cible, puis l\'attaque au contact. Plus la distance augmente, moins l\'attaque touchera facilement, mais plus elle fera de dégâts.';

    private const OLD_RECUPERATION_RUNIQUE_TEXT = 'Ajoute Pui/4 PM par action dépensée lors d\'un Repos';
    private const NEW_RECUPERATION_RUNIQUE_TEXT = 'Ajoute Pui/3 PM par action dépensée lors d\'un Repos';
    
    // Valeurs pour les compétences
    private const OLD_RECUPERATION_RUNIQUE_VALUE = '0.25';
    private const NEW_RECUPERATION_RUNIQUE_VALUE = '0.3334';

    private const OLD_RETRAIT_TEXT = 'Réduit de 4 le seuil du jet de FM de distance';
    private const NEW_RETRAIT_TEXT = 'Réduit de 6 le seuil du jet de FM de distance';
    
    // Valeurs pour les compétences
    private const OLD_RETRAIT_VALUE = '4.00';
    private const NEW_RETRAIT_VALUE = '6.00';

    // Constantes ajoutées pour l'update de outcome_instructions
    private const OLD_ENCAISSE_PARAMETERS = '{"encaisse": true, "stackable": false, "value": 1, "duration": 1}';
    private const NEW_ENCAISSE_PARAMETERS = '{"encaisse": true, "stackable": false, "value": 1, "duration": 0}';

    public function getDescription(): string
    {
        return 'Ajout d\'actions oubliées, modification d\'instructions, correction des textes passifs et mises à jour de leurs valeurs';
    }

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

        // Mises à jour de textes pour la table "actions"
        $this->addSql('UPDATE actions SET text = ? WHERE name = ?', [self::NEW_MINE_ESPRIT_TEXT, 'mine_esprit']);
        $this->addSql('UPDATE actions SET text = ? WHERE name = ?', [self::NEW_ENCAISSE_TEXT, 'encaisse']);
        $this->addSql('UPDATE actions SET text = ? WHERE name = ?', [self::NEW_PARADE_TEXT, 'parade']);
        $this->addSql('UPDATE actions SET text = ? WHERE name = ?', [self::NEW_DISSIPATION_TEXT, 'dissipation']);
        $this->addSql('UPDATE actions SET text = ? WHERE name = ?', [self::NEW_PAS_DE_COTE_TEXT, 'pas_de_cote']);
        $this->addSql('UPDATE actions SET text = ? WHERE name = ?', [self::NEW_SAUT_ATTAQUE_TEXT, 'saut_attaque']);

        // Mises à jour de textes et de valeurs pour la table "action_passives"
        $this->addSql('UPDATE action_passives SET text = ? WHERE name = ?', [self::NEW_VOIE_EAU_TEXT, 'voie_eau']);
        $this->addSql('UPDATE action_passives SET text = ? WHERE name = ?', [self::NEW_MAITRE_ARCHER_TEXT, 'maitre_archer']);
        
        $this->addSql('UPDATE action_passives SET text = ?, value = ? WHERE name = ?', [self::NEW_RECUPERATION_RUNIQUE_TEXT, self::NEW_RECUPERATION_RUNIQUE_VALUE, 'recuperation_runique']);
        $this->addSql('UPDATE action_passives SET text = ?, value = ? WHERE name = ?', [self::NEW_RETRAIT_TEXT, self::NEW_RETRAIT_VALUE, 'retrait']);

        // Mise à jour de la table "outcome_instructions"
        $this->addSql('UPDATE outcome_instructions SET parameters = ? WHERE parameters = ?', [self::NEW_ENCAISSE_PARAMETERS, self::OLD_ENCAISSE_PARAMETERS]);
    }

    public function down(Schema $schema): void
    {
        // Revert des textes pour la table "actions"
        $this->addSql('UPDATE actions SET text = ? WHERE name = ?', [self::OLD_MINE_ESPRIT_TEXT, 'mine_esprit']);
        $this->addSql('UPDATE actions SET text = ? WHERE name = ?', [self::OLD_ENCAISSE_TEXT, 'encaisse']);
        $this->addSql('UPDATE actions SET text = ? WHERE name = ?', [self::OLD_PARADE_TEXT, 'parade']);
        $this->addSql('UPDATE actions SET text = ? WHERE name = ?', [self::OLD_DISSIPATION_TEXT, 'dissipation']);
        $this->addSql('UPDATE actions SET text = ? WHERE name = ?', [self::OLD_PAS_DE_COTE_TEXT, 'pas_de_cote']);
        $this->addSql('UPDATE actions SET text = ? WHERE name = ?', [self::OLD_SAUT_ATTAQUE_TEXT, 'saut_attaque']);

        // Revert des textes et des valeurs pour la table "action_passives"
        $this->addSql('UPDATE action_passives SET text = ? WHERE name = ?', [self::OLD_VOIE_EAU_TEXT, 'voie_eau']);
        $this->addSql('UPDATE action_passives SET text = ? WHERE name = ?', [self::OLD_MAITRE_ARCHER_TEXT, 'maitre_archer']);
        
        $this->addSql('UPDATE action_passives SET text = ?, value = ? WHERE name = ?', [self::OLD_RECUPERATION_RUNIQUE_TEXT, self::OLD_RECUPERATION_RUNIQUE_VALUE, 'recuperation_runique']);
        $this->addSql('UPDATE action_passives SET text = ?, value = ? WHERE name = ?', [self::OLD_RETRAIT_TEXT, self::OLD_RETRAIT_VALUE, 'retrait']);

        // Revert de la table "outcome_instructions"
        $this->addSql('UPDATE outcome_instructions SET parameters = ? WHERE parameters = ?', [self::OLD_ENCAISSE_PARAMETERS, self::NEW_ENCAISSE_PARAMETERS]);

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