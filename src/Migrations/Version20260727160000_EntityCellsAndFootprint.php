<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * L'emprise : une entité peut occuper PLUSIEURS cases.
 *
 * Aujourd'hui le monde ne sait dire qu'une chose : `players.coords_id`, une
 * case et une seule. Un fort, une pyramide, un géant occupent pourtant
 * plusieurs cases — et le contournement, c'est d'en poser autant de morceaux
 * indépendants, que rien ne relie. D'où des objets qu'on ne peut ni déplacer,
 * ni détruire, ni interroger d'un bloc, et des découpes que l'éditeur défait
 * à chaque écriture.
 *
 * # Ce lot ne fait RIEN lire
 *
 * Additif et réversible, par construction. Les deux tables naissent, la
 * première est remplie à l'IDENTIQUE de l'état actuel — une case par entité,
 * celle qu'elle occupe déjà — et pas une ligne de lecture ne change.
 * `players.coords_id` reste l'ancre : aucun des 337 sites du dépôt qui la
 * lisent n'est touché. C'est L4 qui branchera les premiers clients.
 *
 * Écrire l'emprise avant de s'en servir, c'est pouvoir la comparer à
 * l'existant case par case, et revenir en arrière sans rien perdre.
 *
 * # Les rôles
 *
 * - `anchor` — la case de référence, celle de `players.coords_id`. Son
 *   comportement vient du CATALOGUE (`races.blocks_passage`), comme
 *   aujourd'hui : c'est ce qui permet de matérialiser l'existant sans
 *   décider de rien.
 * - `block` — bloque le pas.
 * - `cover` — marchable, dessinée AU-DESSUS du joueur : la portion haute d'un
 *   décor, celle derrière laquelle on se cache.
 * - `door` — marchable, porte un point d'entrée.
 * - `open` — marchable, sans autre effet.
 *
 * Invariant : toute entité posée a exactement une ligne `anchor`, à
 * `players.coords_id`. Un test le tient.
 *
 * # Une case peut porter plusieurs entités, et c'est voulu
 *
 * La clé primaire est `(player_id, coords_id)`, pas `coords_id` : deux
 * entités peuvent occuper la même case. L'empilement sert aux animateurs et
 * aux administrateurs, et la superposition décor + déclencheur est l'usage
 * NORMAL du monde — mesuré sur les 1 746 déclencheurs `tp` de production :
 * 9 escaliers, 9 échelles, 14 portes des enfers posés par-dessus, contre
 * 2 entités. Interdire la superposition aurait cassé les téléporteurs.
 *
 * # Collations
 *
 * `plan` est dénormalisée ici pour le chemin chaud (le rendu du damier lit
 * une fenêtre en (plan, z, x, y)). Elle prend la collation de `coords.plan`,
 * `utf8mb4_general_ci`, et non celle des catalogues Doctrine
 * (`uca1400_ai_ci`) : une jointure entre les deux rend « Illegal mix of
 * collations ». La table entière est donc déclarée en `general_ci`, comme
 * `coords` et `players`.
 */
final class Version20260727160000_EntityCellsAndFootprint extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'entity_cells + race_footprint — an entity may span several tiles (nothing reads them yet)';
    }

    public function up(Schema $schema): void
    {
        /* L'emprise elle-même. Les colonnes dénormalisées (plan, z, x, y) sont
         * là pour le rendu : interroger une fenêtre sans jointure sur coords. */
        $this->addSql("
            CREATE TABLE IF NOT EXISTS entity_cells (
                player_id INT NOT NULL,
                coords_id INT NOT NULL,
                plan VARCHAR(255) NOT NULL DEFAULT '',
                z SMALLINT NOT NULL DEFAULT 0,
                x INT NOT NULL DEFAULT 0,
                y INT NOT NULL DEFAULT 0,
                piece SMALLINT NOT NULL DEFAULT 0
                    COMMENT 'index du morceau de sprite dans la découpe (0 = pièce unique)',
                role VARCHAR(16) NOT NULL DEFAULT 'anchor'
                    COMMENT 'anchor|block|cover|door|open',
                PRIMARY KEY (player_id, coords_id),
                KEY k_coords (coords_id, player_id),
                KEY k_hot (plan, z, x, y)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
              COMMENT='Cases occupées par une entité — SSOT de l''occupation (L3)'
        ");

        /* Les clés étrangères séparément : `IF NOT EXISTS` n'existe pas pour
         * une contrainte, et une migration rejouée ne doit pas échouer. */
        $this->addConstraintIfMissing(
            'entity_cells',
            'fk_entity_cells_player',
            'ALTER TABLE entity_cells
             ADD CONSTRAINT fk_entity_cells_player FOREIGN KEY (player_id)
             REFERENCES players (id) ON DELETE CASCADE'
        );
        $this->addConstraintIfMissing(
            'entity_cells',
            'fk_entity_cells_coords',
            'ALTER TABLE entity_cells
             ADD CONSTRAINT fk_entity_cells_coords FOREIGN KEY (coords_id)
             REFERENCES coords (id) ON DELETE RESTRICT'
        );

        /* La découpe par type d'entité. `roles` porte le rôle de chaque
         * morceau, en JSON indexé par `piece` — une emprise TROUÉE est donc
         * exprimable, et il en faut : cinq familles ont un coin transparent
         * (praetorium, fort_turok, pyramide, astéroïde…), si bien que la boîte
         * englobante des cases posées n'est pas la découpe du sprite. */
        $this->addSql("
            CREATE TABLE IF NOT EXISTS race_footprint (
                race_id INT NOT NULL,
                w TINYINT UNSIGNED NOT NULL DEFAULT 1,
                h TINYINT UNSIGNED NOT NULL DEFAULT 1,
                roles TEXT DEFAULT NULL
                    COMMENT 'JSON {piece: role} — absent = rôle du catalogue ; permet les emprises trouées',
                PRIMARY KEY (race_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
              COMMENT='Découpe multi-cases par type d''entité (L3)'
        ");

        $this->addConstraintIfMissing(
            'race_footprint',
            'fk_race_footprint_race',
            'ALTER TABLE race_footprint
             ADD CONSTRAINT fk_race_footprint_race FOREIGN KEY (race_id)
             REFERENCES races (id) ON DELETE CASCADE'
        );

        /* Matérialisation À L'IDENTIQUE : une case par entité posée, celle
         * qu'elle occupe déjà, en rôle d'ancre. Aucune découpe n'est devinée
         * ici — la dériver des données est un travail à part, qui demande de
         * distinguer deux exemplaires collés d'un même décor.
         *
         * INSERT IGNORE : rejouable sans doublon, et une entité sans case
         * (coords_id à 0) est simplement ignorée. */
        $this->addSql("
            INSERT IGNORE INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
            SELECT p.id, p.coords_id, co.plan, co.z, co.x, co.y, 0, 'anchor'
            FROM players p
            JOIN coords co ON co.id = p.coords_id
            WHERE p.coords_id IS NOT NULL AND p.coords_id > 0
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS entity_cells');
        $this->addSql('DROP TABLE IF EXISTS race_footprint');
    }

    /**
     * MariaDB n'accepte pas `ADD CONSTRAINT IF NOT EXISTS` : on regarde
     * d'abord, et on ne pose que si la contrainte manque.
     */
    private function addConstraintIfMissing(string $table, string $name, string $sql): void
    {
        $exists = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $name]
        );

        if ($exists === 0) {
            $this->addSql($sql);
        }
    }
}
