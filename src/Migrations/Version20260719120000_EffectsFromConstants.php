<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Move the effect catalog from config/constants.php into the DB.
 *
 * Eight constants carried it, each a partial view of the same catalog:
 * EFFECTS_RA_FONT (master list + icon, also the existence validator),
 * EFFECTS_HIDDEN (ephemeral combat stances), EFFECTS_TXT (label +
 * description), ELE_DEBUFFS / ELE_BUFFS (carac modifier), ELE_CONTROLS
 * (elemental cancellation — ELE_IS_CONTROLED was its hand-maintained
 * inverse, now computed; the historical cycle was strictly 1:1 but the
 * model is a LIST, an effect can cancel several others), ITEM_CORRUPTIONS
 * + ITEM_CORRUPT_BREAKCHANCES (materials a corruption can break). They
 * merge into `effects` plus the 1:N `effect_controls` and
 * `effect_corruption_materials`.
 *
 * The player-state side (players_effects) already lives in the DB and is
 * untouched; its `name` values reference effects.name.
 *
 * Also fixes a latent key mismatch: EFFECTS_RA_FONT said
 * 'corruption_du_plantes' while ITEM_CORRUPTIONS and the action data said
 * 'corruption_des_plantes' — the validator therefore refused the only
 * spelling the rest of the game used. The catalog is seeded under
 * 'corruption_des_plantes' and existing rows are renamed to match.
 *
 * Idempotent (IF NOT EXISTS + no-op ON DUPLICATE KEY): re-running never
 * clobbers admin-edited rows.
 */
final class Version20260719120000_EffectsFromConstants extends AbstractMigration
{
    /**
     * Snapshot of the merged constants (the source is deleted with this
     * migration). Omitted keys default to: label = ucfirst(name),
     * description = '', hidden/marker = false, caracs = null, controls
     * = aucun (chaque entrée alimente la liste effect_controls).
     *
     * @var array<string, array<string, mixed>>
     */
    private const EFFECTS = [
        'adrenaline' => ['icon' => 'ra-horn-call', 'label' => 'Adrénaline', 'description' => "Empêche d'intéragir avec un Marchand."],

        // Éléments : chaque debuff retire 1 point de la carac visée tant
        // que l'effet dure ; le cycle `controls` annule l'élément dominé
        // (eau éteint feu, feu fond diamant…).
        'feu' => ['icon' => 'ra-small-fire', 'debuff' => 'e', 'controls' => 'diamant'],
        'eau' => ['icon' => 'ra-water-drop', 'debuff' => 'mvt', 'controls' => 'feu', 'description' => 'Diminue les Mouvements de 1.'],
        'ronce' => ['icon' => 'ra-vine-whip', 'debuff' => 'agi', 'controls' => 'boue', 'description' => "Diminue l'Agilité de 1."],
        'boue' => ['icon' => 'ra-shoe-prints', 'debuff' => 'f', 'controls' => 'eau', 'description' => 'Diminue la Force de 1.'],
        'diamant' => ['icon' => 'ra-sapphire', 'debuff' => 'res', 'controls' => 'ronce', 'description' => 'Diminue la Résistance de 1.'],
        'styx' => ['icon' => 'ra-water-drop', 'debuff' => 'mvt'],
        'sang' => ['icon' => 'ra-gloop', 'debuff' => 'fm', 'description' => 'Diminue Force Mentale de 1.'],
        'lave' => ['icon' => 'ra-fire-bomb', 'debuff' => 'a', 'description' => 'Diminue les Actions de 1.'],

        'regeneration' => ['icon' => 'ra-health-increase', 'label' => 'Regénération', 'description' => 'Effet du sort Regénération.'],
        'poison' => ['icon' => 'ra-bone-bite'],
        'poison_magique' => ['icon' => 'ra-bone-bite', 'label' => 'Poison Magique', 'description' => 'Empêche la récupération magique au prochain tour.'],

        // Postures de combat : posées par une action, consommées à l'usage
        // ou purgées au nouveau tour, jamais affichées (ex-EFFECTS_HIDDEN).
        'parade' => ['icon' => 'ra-sword', 'hidden' => true],
        'pas_de_cote' => ['icon' => 'ra-player-dodge', 'label' => 'Pas de côté', 'hidden' => true],
        'cle_de_bras' => ['icon' => 'ra-bear-trap', 'label' => 'Clé de bras', 'hidden' => true],
        'leurre' => ['icon' => 'ra-lava', 'hidden' => true],
        'dedoublement' => ['icon' => 'ra-double-team', 'label' => 'Dédoublement', 'hidden' => true],

        'armure_rayonnante' => ['icon' => 'ra-sunbeams', 'label' => 'Armure rayonnante'],
        'berserker' => ['icon' => 'ra-monster-skull'],
        'endiamante' => ['icon' => 'ra-diamond', 'label' => 'Endiamanté'],
        'golconda' => ['icon' => 'ra-aware'],
        'martyr' => ['icon' => 'ra-player-shot'],

        'corruption_du_metal' => ['icon' => 'ra-biohazard', 'label' => 'Corruption du métal', 'description' => 'Augmente le risque que le matériel contenant du métal (Bronze, Nickel) se casse.', 'materials' => ['bronze', 'nickel'], 'break_chance' => 0],
        'corruption_du_bronze' => ['icon' => 'ra-biohazard', 'label' => 'Corruption du Bronze', 'description' => 'Augmente le risque que le matériel contenant du Bronze se casse.', 'materials' => ['bronze'], 'break_chance' => 0],
        'corruption_du_bois' => ['icon' => 'ra-biohazard', 'label' => 'Corruption du Bois', 'description' => 'Augmente le risque que le matériel contenant du Bois (ou du Bois Pétrifié) se casse.', 'materials' => ['bois', 'bois_petrifie'], 'break_chance' => 0],
        'corruption_des_plantes' => ['icon' => 'ra-biohazard', 'label' => 'Corruption des plantes', 'description' => 'Augmente le risque que le matériel contenant des plantes (Adonis) se casse.', 'materials' => ['adonis', 'cafe', 'astral', 'houblon', 'lichen_sacre', 'lotus_noir', 'menthe', 'pavot'], 'break_chance' => 0],
        'corruption_du_cuir' => ['icon' => 'ra-biohazard', 'label' => 'Corruption du Cuir', 'description' => 'Augmente le risque que le matériel contenant du Cuir se casse.', 'materials' => ['cuir'], 'break_chance' => 0],

        'vol' => ['icon' => 'ra-feather-wing', 'description' => 'Permet de se déplacer dans les airs.'],

        'acuite_visuelle' => ['icon' => 'ra-eyeball', 'label' => 'Acuité visuelle', 'buff' => 'p'],
        'agressivite' => ['icon' => 'ra-dinosaur', 'label' => 'Agressivité'],
        'armure' => ['icon' => 'ra-vest'],
        'dexterite' => ['icon' => 'ra-plain-dagger', 'label' => 'Dextérité'],
        'discretion' => ['icon' => 'ra-player', 'label' => 'Discrétion'],
        'encaisse' => ['icon' => 'ra-muscle-fat', 'label' => 'Encaisse'],
        'leger' => ['icon' => 'ra-player', 'label' => 'Léger'],
        'protection' => ['icon' => 'ra-shield'],
        'stabilite' => ['icon' => 'ra-boot-stomp', 'label' => 'Stabilité'],
        'renforcement' => ['icon' => 'ra-lion'],

        'aveuglement' => ['icon' => 'ra-bleeding-eye', 'debuff' => 'p'],
        'brulure' => ['icon' => 'ra-fire', 'label' => 'Brûlure'],
        'faiblesse' => ['icon' => 'ra-player-pain'],
        'fragilite' => ['icon' => 'ra-broken-bottle', 'label' => 'Fragilité'],
        'imposture' => ['icon' => 'ra-player-teleport'],
        'maladresse' => ['icon' => 'ra-cut-palm'],
        'ralentissement' => ['icon' => 'ra-snail'],
        'vulnerabilite' => ['icon' => 'ra-broken-shield', 'label' => 'Vulnérabilité'],
        'instabilite' => ['icon' => 'ra-falling', 'label' => 'Instabilité'],

        // Marqueurs de carte (traces de pas directionnelles) : rangés dans
        // le catalogue parce qu'ils transitent par players_effects, mais
        // exclus des listes de gameplay (is_map_marker).
        'trace_pas' => ['icon' => 'ra-footprint', 'marker' => true],
        'trace_pas_ne' => ['icon' => 'ra-footprint', 'marker' => true],
        'trace_pas_n' => ['icon' => 'ra-footprint', 'marker' => true],
        'trace_pas_no' => ['icon' => 'ra-footprint', 'marker' => true],
        'trace_pas_e' => ['icon' => 'ra-footprint', 'marker' => true],
        'trace_pas_o' => ['icon' => 'ra-footprint', 'marker' => true],
        'trace_pas_se' => ['icon' => 'ra-footprint', 'marker' => true],
        'trace_pas_s' => ['icon' => 'ra-footprint', 'marker' => true],
        'trace_pas_so' => ['icon' => 'ra-footprint', 'marker' => true],
    ];

    public function getDescription(): string
    {
        return 'Move the effect catalog (EFFECTS_RA_FONT & co) into effects + effect_corruption_materials';
    }

    public function up(Schema $schema): void
    {
        $this->createTables();
        $this->renamePlantsCorruption();
        $this->seed();
    }

    private function createTables(): void
    {
        $this->addSql(
            "CREATE TABLE IF NOT EXISTS effects (
                id INT AUTO_INCREMENT NOT NULL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                label VARCHAR(100) NOT NULL DEFAULT '',
                description TEXT DEFAULT NULL,
                icon VARCHAR(50) NOT NULL DEFAULT 'ra-fairy-wand',
                hidden TINYINT(1) NOT NULL DEFAULT 0,
                buff_carac VARCHAR(10) DEFAULT NULL,
                debuff_carac VARCHAR(10) DEFAULT NULL,
                corruption_break_chance INT DEFAULT NULL,
                is_map_marker TINYINT(1) NOT NULL DEFAULT 0,
                UNIQUE KEY UNIQ_effects_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // Deux listes 1:N de même forme : les effets annulés (le cycle
        // élémentaire n'en met qu'un, le modèle en accepte plusieurs) et
        // les matériaux corruptibles.
        foreach (['effect_controls', 'effect_corruption_materials'] as $table) {
            $this->addSql(
                "CREATE TABLE IF NOT EXISTS {$table} (
                    id INT AUTO_INCREMENT NOT NULL PRIMARY KEY,
                    effect_id INT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    position INT NOT NULL DEFAULT 0,
                    UNIQUE KEY UNIQ_{$table}_effect_name (effect_id, name),
                    CONSTRAINT FK_{$table}_effect FOREIGN KEY (effect_id)
                        REFERENCES effects (id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    /**
     * Align every stored occurrence of the misspelled key on the one the
     * data already used. UPDATE IGNORE + DELETE: a player somehow carrying
     * both spellings would collide on the (player_id, name) PK.
     */
    private function renamePlantsCorruption(): void
    {
        $this->addSql(
            "UPDATE IGNORE players_effects SET name = 'corruption_des_plantes' WHERE name = 'corruption_du_plantes'"
        );
        $this->addSql("DELETE FROM players_effects WHERE name = 'corruption_du_plantes'");
        $this->addSql("UPDATE races SET bleeds = 'corruption_des_plantes' WHERE bleeds = 'corruption_du_plantes'");
        foreach (['outcome_instructions' => 'parameters', 'action_conditions' => 'parameters'] as $table => $column) {
            $this->addSql(
                "UPDATE {$table}
                 SET {$column} = REPLACE({$column}, 'corruption_du_plantes', 'corruption_des_plantes')
                 WHERE {$column} LIKE '%corruption_du_plantes%'"
            );
        }
    }

    private function seed(): void
    {
        foreach (self::EFFECTS as $name => $effect) {
            $this->addSql(
                'INSERT INTO effects
                    (name, label, description, icon, hidden, buff_carac, debuff_carac, corruption_break_chance, is_map_marker)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE name = name',
                [
                    $name,
                    $effect['label'] ?? ucfirst(strtr($name, '_', ' ')),
                    $effect['description'] ?? '',
                    $effect['icon'],
                    (int) ($effect['hidden'] ?? false),
                    $effect['buff'] ?? null,
                    $effect['debuff'] ?? null,
                    $effect['break_chance'] ?? null,
                    (int) ($effect['marker'] ?? false),
                ]
            );

            $lists = [
                'effect_controls' => array_values((array) ($effect['controls'] ?? [])),
                'effect_corruption_materials' => array_values($effect['materials'] ?? []),
            ];
            foreach ($lists as $table => $names) {
                foreach ($names as $position => $entry) {
                    $this->addSql(
                        "INSERT IGNORE INTO {$table} (effect_id, name, position)
                         SELECT id, ?, ? FROM effects WHERE name = ?",
                        [$entry, $position, $name]
                    );
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS effect_controls');
        $this->addSql('DROP TABLE IF EXISTS effect_corruption_materials');
        $this->addSql('DROP TABLE IF EXISTS effects');
    }
}
