<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La découpe d'un TYPE d'entité, déclarée une fois pour toutes.
 *
 * `entity_cells` porte les cases d'une entité POSÉE ; cette table-ci porte la
 * forme du type dont elle est un exemplaire. Les deux se lisent l'une à côté
 * de l'autre, et leurs noms le disent.
 *
 * # Pourquoi une déclaration, et pas une déduction
 *
 * On sait déduire une découpe de deux manières, et **elles se contredisent** :
 *
 * - la CARTE, quand un exemplaire complet y figure — elle montre la figure
 *   telle qu'elle est réellement posée, mais elle ignore ce qui n'a jamais
 *   été posé, et 53 familles sur 130 sont dans ce cas ;
 * - les IMAGES d'ensemble (`base/base.png` divisée par 50) — mais celle de
 *   `geant_petrifie` annonce 1×2 cases quand quatre morceaux existent et que
 *   la carte en montre une figure de 3×3 trouée. L'asset est incomplet.
 *
 * Aucune source n'a raison seule. Il faut qu'un humain tranche une fois, et
 * que sa décision se garde : c'est cette table, et la page d'administration
 * qui l'édite.
 *
 * L'ordre de lecture devient : **déclaration, puis carte, puis images**. Une
 * découpe déclarée l'emporte, y compris sur ce que la carte montre — c'est
 * tout l'intérêt de pouvoir corriger un décor mal posé.
 *
 * # La clé est le NOM, pas un identifiant de `races`
 *
 * Une découpe décrit une famille de morceaux de décor — `geant_petrifie`,
 * `unique_fort_turok` — et ces familles ne sont PAS encore des types du
 * catalogue : elles le deviendront à la conversion des décors en entités.
 * Attacher la découpe à `races.id` la rendrait indéclarable précisément pour
 * les familles qui en ont besoin : sur 24 découpes connues, 23 n'ont aucune
 * ligne dans `races`.
 *
 * Le nom est d'ailleurs la clé de jointure du reste du monde —
 * `map_foregrounds.name`, `map_resources.name`, `players.race` s'y réfèrent
 * déjà. On perd l'intégrité référentielle ; on gagne de pouvoir déclarer la
 * forme d'un décor avant qu'il ne soit une entité, ce qui est tout l'objet.
 *
 * # Le nom de la table
 *
 * `races` est un vestige : elle abrite `mur_pierre`, `coffre_bois`, `glaise1`
 * et `altar` sous `kind = structure`, et jusqu'à `batiment`, `ia`, `misc` et
 * `protocole` sous `kind = character`. Un mur n'est pas une race.
 *
 * La renommer coûte cher — 40 fichiers citent la table, 70 la colonne
 * `players.race` — et c'est un lot à part. Nommer correctement la table NEUVE
 * ne coûte rien : `entity_type_footprints` dit ce qu'elle décrit, et le jour
 * où `races` deviendra `entity_types`, elle sera déjà au bon nom.
 *
 * `race_footprint`, créée vide par le lot précédent et jamais lue, disparaît
 * dans la foulée.
 *
 * # Les rôles
 *
 * `roles` est un JSON indexé par morceau : `{"0":"block","2":"cover"}`. Un
 * morceau absent prend le rôle par défaut du type. C'est ce qui permet à la
 * base d'un décor de bloquer pendant que sa partie haute se traverse — et
 * aux 50 tonneaux couchés d'être `open` là où leur type bloque.
 */
final class Version20260728130000_EntityTypeFootprints extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'entity_type_footprints — a declared cut-out per entity type, replacing the unused race_footprint';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS entity_type_footprints (
                type_name VARCHAR(255) NOT NULL
                    COMMENT 'Nom de la famille — la clé de jointure du monde, qu''elle soit ou non déjà un type du catalogue',
                w TINYINT UNSIGNED NOT NULL DEFAULT 1,
                h TINYINT UNSIGNED NOT NULL DEFAULT 1,
                offsets TEXT NOT NULL
                    COMMENT 'JSON {morceau: [dx, dy]} relatif au premier morceau — les figures trouées sont donc exprimables',
                roles TEXT DEFAULT NULL
                    COMMENT 'JSON {morceau: role} ; absent = rôle par défaut du type',
                PRIMARY KEY (type_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
              COMMENT='Découpe multi-cases déclarée, par type d''entité'
        ");

        /* Créée vide par le lot précédent, jamais lue : elle part sans regret.
         * Sa remplaçante porte en plus les décalages, sans quoi une figure
         * trouée ne pouvait pas être décrite. */
        $this->addSql('DROP TABLE IF EXISTS race_footprint');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS race_footprint (
                race_id INT NOT NULL,
                w TINYINT UNSIGNED NOT NULL DEFAULT 1,
                h TINYINT UNSIGNED NOT NULL DEFAULT 1,
                roles TEXT DEFAULT NULL,
                PRIMARY KEY (race_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $this->addSql('DROP TABLE IF EXISTS entity_type_footprints');
    }
}
