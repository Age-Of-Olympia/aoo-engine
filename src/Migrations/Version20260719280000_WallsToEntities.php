<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Conversion des map_walls OBSTACLES/DÉCOR en entités bâtiment
 * (docs/design-walls-to-entities.md, décisions du 2026-07-19) : un seul
 * système pour tout ce qui se détruit — attaquer, disparition (vanish),
 * sprites blessés, ligne de tir.
 *
 * Restent map_walls : les RESSOURCES (types WALLS_PV négatifs, quel que
 * soit leur damages — la fouille et le regrow sont leur système), les
 * autels (interactifs), les plans de tutoriel (murs clonés par session)
 * et les types « unique_* » (relèvent des objets uniques).
 *
 * Règles clefs :
 * - l'image est COPIÉE telle quelle (img/walls/{nom}.png — le mur
 *   s'affichait avec, c'est une donnée) : AUCUN accès disque ici, une
 *   migration déployée tourne sans img/ (leçon « Mu ») ;
 * - un mur « X_broken » devient la race X blessée à ≤ 50 % (le sprite
 *   brisé suit la règle de refreshWoundSprite), avatar _broken copié ;
 * - un mur entamé (damages > 0) garde sa blessure en players_bonus ;
 * - chaque ligne convertie est ARCHIVÉE dans map_walls_archive avec
 *   l'id d'entité créé — rollback froid par le down().
 *
 * Idempotente : les lignes converties sont supprimées de map_walls, un
 * rejeu ne trouve plus rien à faire. Post-déploiement facultatif :
 * console « building repair-avatars » (promotion de visuels dédiés).
 */
final class Version20260719280000_WallsToEntities extends AbstractMigration
{
    /** Types RESSOURCE (WALLS_PV < 0 au moment du gel) — jamais convertis. */
    private const RESOURCE_NAMES = [
        'arbre1', 'arbre2', 'arbre3', 'arbre4', 'arbre5', 'arbre6',
        'arbre_petrifie1', 'arbre_petrifie2', 'arbre_petrifie3',
        'arbre_petrifie4', 'arbre_petrifie5', 'arbre_petrifie6',
        'cendre', 'cuir', 'cuivre', 'etain', 'fer', 'nickel', 'salpetre',
        'tourbe', 'mana', 'bronze',
        'herbe1', 'herbe2', 'herbe3', 'jungle1', 'jungle2', 'jungle3',
        'pierre1', 'pierre2', 'pierre3',
        'pierre_noire1', 'pierre_noire2', 'pierre_noire3',
        'rocher_desert1', 'rocher_desert2', 'rocher_desert3',
    ];

    /** PV par type (WALLS_PV > 0 gelé ici — la migration ne lit pas la config). */
    private const PV_MAP = [
        'mur_pierre' => 150, 'mur_pierre_bleue' => 150, 'mur_noir' => 120,
        'mur_bois' => 100, 'mur_bois_petrifie' => 120, 'mur_vegetal' => 120,
        'mur_fer' => 180, 'mur_crepusculaire' => 120, 'mur_blanc' => 180,
        'muret' => 40, 'barricade' => 40,
        'coffre_metal' => 1, 'coffre_bois' => 1, 'coffre_bois_petrifie' => 1,
        'pierre_precieuse' => 500,
        'piedestal' => 15, 'piedestal_pierre' => 10,
        'table_bois' => 5, 'tonneau' => 5, 'torche_sol' => 10, 'trone' => 25,
        'tombe2' => 10, 'tombe' => 30, 'tombe_detruite' => 10, 'sarcophage' => 50,
        'statue_monstrueuse' => 10, 'statue_ailee' => 10, 'statue_heroique' => 10,
        'statue_gisant' => 10, 'statue_forestiere' => 10, 'statue_colosses' => 10,
        'statue_garde' => 10, 'statue_servant' => 10, 'statue_noble' => 10,
        'statue_kraken' => 30,
        'roue_a_aubes' => 10, 'lanternesurpied_geant' => 10,
        'monolithe_flamboyant' => 10, 'flamme_bleue' => 10,
        'totem_crane' => 10, 'totem_sauvage' => 10, 'totem_magique' => 10,
        'pilier_nain' => 10, 'pilier' => 10,
        'cocotier1' => 1, 'cocotier2' => 1, 'cocotier3' => 1,
    ];

    private const DEFAULT_PV = 10;
    private const DEFAULT_BG_COLOR = '#8a8a8a';
    private const ENTITY_ID_FLOOR = 20000000;
    private const ENTITY_ID_CEILING = 29999999;

    public function getDescription(): string
    {
        return 'Convertit les map_walls obstacles/décor en entités bâtiment (les ressources restent map_walls)';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        $conn->executeStatement(
            'CREATE TABLE IF NOT EXISTS map_walls_archive (
                wall_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                player_id INT NULL,
                coords_id INT NOT NULL,
                damages INT NOT NULL,
                entity_id INT NOT NULL,
                converted_at DATETIME NOT NULL,
                PRIMARY KEY (wall_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        $resourcePlaceholders = implode(',', array_fill(0, count(self::RESOURCE_NAMES), '?'));
        $walls = $conn->fetchAllAssociative(
            "SELECT w.id, w.name, w.player_id, w.coords_id, w.damages, c.plan
             FROM map_walls w
             JOIN coords c ON c.id = w.coords_id
             WHERE w.damages >= 0
               AND w.name NOT LIKE 'altar%'
               AND w.name NOT LIKE 'autel%'
               AND w.name NOT LIKE 'unique\\_%'
               AND c.plan != 'tutorial'
               AND c.plan NOT LIKE 'tut\\_%'
               AND w.name NOT IN ({$resourcePlaceholders})
               AND REPLACE(w.name, '_broken', '') NOT IN ({$resourcePlaceholders})",
            array_merge(self::RESOURCE_NAMES, self::RESOURCE_NAMES)
        );

        if ($walls === []) {
            return;
        }

        $nextId = max(
            self::ENTITY_ID_FLOOR,
            1 + (int) $conn->fetchOne(
                'SELECT COALESCE(MAX(id), 0) FROM players WHERE id BETWEEN ? AND ?',
                [self::ENTITY_ID_FLOOR, self::ENTITY_ID_CEILING]
            )
        );
        $nextDisplayId = 1 + (int) $conn->fetchOne(
            "SELECT COALESCE(MAX(display_id), 0) FROM players WHERE player_type = 'building'"
        );

        $knownRaces = array_flip($conn->fetchFirstColumn('SELECT name FROM races'));

        foreach ($walls as $wall) {
            $base = preg_replace('/_broken$/', '', (string) $wall['name']);
            $maxPv = self::PV_MAP[$base] ?? self::DEFAULT_PV;
            $label = ucfirst(str_replace('_', ' ', $base));

            if (!isset($knownRaces[$base])) {
                $conn->executeStatement(
                    "INSERT IGNORE INTO races
                        (code, name, label, description, playable, hidden, kind, structure_nature,
                         bleeds, wound_color, blocks_passage, blocks_projectiles, bgColor, color, faction, plan, pv)
                     VALUES (?, ?, ?, '', 0, 1, 'structure', 'obstacle', '', '#cd7f32', 1, 1, ?, 'black', '', '', ?)",
                    [strtoupper($base), $base, $label, self::DEFAULT_BG_COLOR, $maxPv]
                );
                $knownRaces[$base] = true;
            } else {
                $racePv = (int) $conn->fetchOne('SELECT pv FROM races WHERE name = ?', [$base]);
                if ($racePv > 0) {
                    $maxPv = $racePv;
                }
            }

            // Blessure : un _broken est ≤ 50 % (sprite brisé cohérent) ; un
            // mur entamé garde ses dégâts, plafonnés sous la mort.
            $wound = 0;
            if (str_ends_with((string) $wall['name'], '_broken')) {
                $wound = (int) ceil($maxPv / 2);
            } elseif ((int) $wall['damages'] > 0) {
                $wound = min((int) $wall['damages'], $maxPv - 1);
            }

            $id = $nextId++;
            $displayId = $nextDisplayId++;

            $conn->executeStatement(
                "INSERT INTO players
                    (id, player_type, display_id, name, race, avatar, portrait, coords_id, nextTurnTime, registerTime)
                 VALUES (?, 'building', ?, ?, ?, ?, ?, ?, 0, ?)",
                [
                    $id,
                    $displayId,
                    $label,
                    $base,
                    // L'IMAGE DU MUR, copiée telle quelle — donnée, pas résolue
                    'img/walls/' . $wall['name'] . '.png',
                    'img/walls/' . $wall['name'] . '.png',
                    (int) $wall['coords_id'],
                    time(),
                ]
            );
            $conn->executeStatement(
                "INSERT INTO buildings (player_id, owner_id, faction, build_state)
                 VALUES (?, ?, '', 'built')",
                [$id, $wall['player_id'] !== null ? (int) $wall['player_id'] : null]
            );
            if ($wound > 0) {
                $conn->executeStatement(
                    "INSERT INTO players_bonus (player_id, name, n) VALUES (?, 'pv', ?)",
                    [$id, -$wound]
                );
            }

            $conn->executeStatement(
                'INSERT INTO map_walls_archive (wall_id, name, player_id, coords_id, damages, entity_id, converted_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())',
                [
                    (int) $wall['id'],
                    (string) $wall['name'],
                    $wall['player_id'] !== null ? (int) $wall['player_id'] : null,
                    (int) $wall['coords_id'],
                    (int) $wall['damages'],
                    $id,
                ]
            );
            $conn->executeStatement('DELETE FROM map_walls WHERE id = ?', [(int) $wall['id']]);
        }
    }

    public function down(Schema $schema): void
    {
        $conn = $this->connection;

        foreach ($conn->fetchAllAssociative('SELECT * FROM map_walls_archive') as $row) {
            $entityId = (int) $row['entity_id'];
            $conn->executeStatement('DELETE FROM players_bonus WHERE player_id = ?', [$entityId]);
            $conn->executeStatement('DELETE FROM buildings WHERE player_id = ?', [$entityId]);
            $conn->executeStatement('DELETE FROM players_logs WHERE player_id = ? OR target_id = ?', [$entityId, $entityId]);
            $conn->executeStatement('DELETE FROM players WHERE id = ?', [$entityId]);
            $conn->executeStatement(
                'INSERT INTO map_walls (id, name, player_id, coords_id, damages) VALUES (?, ?, ?, ?, ?)',
                [(int) $row['wall_id'], (string) $row['name'], $row['player_id'], (int) $row['coords_id'], (int) $row['damages']]
            );
            $conn->executeStatement('DELETE FROM map_walls_archive WHERE wall_id = ?', [(int) $row['wall_id']]);
        }
    }
}
