<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le catalogue et les instances doivent pouvoir se joindre.
 *
 * Trois familles de collations cohabitent : `utf8mb4_general_ci` du côté des
 * DONNÉES (coords, players, toutes les couches map_*), `utf8mb4_uca1400_ai_ci`
 * et `utf8mb4_unicode_ci` du côté des CATALOGUES créés par Doctrine. Résultat
 * mesuré, cinq jointures lèvent « Illegal mix of collations » :
 *
 *   map_resources.name   ↔ races.name
 *   map_foregrounds.name ↔ races.name
 *   resource_types.name  ↔ races.name
 *   players_actions.name ↔ actions.name
 *   coords.plan          ↔ races.plan
 *
 * C'est ce qui oblige aujourd'hui à joindre en PHP ou à semer des CONVERT.
 *
 * On aligne le CATALOGUE sur les données, et non l'inverse : quelques dizaines
 * de lignes contre ~40 000, et aucun risque sur les couches de carte.
 *
 * Volontairement colonne par colonne, PAS `CONVERT TO CHARACTER SET` sur la
 * table : `races.label` porte des libellés français accentués dont
 * l'insensibilité aux accents doit rester. Seules les clés de jointure —
 * des codes ASCII (« arbre1 », « nain », « olympia ») — changent, ce qui ne
 * modifie aucune comparaison existante.
 *
 * Les index uniques portés par ces colonnes (UNIQ_RACES_NAME, UNIQ_…_code,
 * PRIMARY de resource_types) sont reconstruits par le MODIFY ; aucune clé
 * étrangère ne les référence, toutes pointent des id.
 */
final class Version20260727110000_JoinKeyCollations extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'le catalogue se joint aux instances : collations des clés de jointure alignées sur utf8mb4_general_ci';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE races MODIFY COLUMN code VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL");
        $this->addSql("ALTER TABLE races MODIFY COLUMN name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL");
        $this->addSql("ALTER TABLE races MODIFY COLUMN plan VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE resource_types MODIFY COLUMN name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL");
        $this->addSql("ALTER TABLE actions MODIFY COLUMN name VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE races MODIFY COLUMN code VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL");
        $this->addSql("ALTER TABLE races MODIFY COLUMN name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL");
        $this->addSql("ALTER TABLE races MODIFY COLUMN plan VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE resource_types MODIFY COLUMN name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
        $this->addSql("ALTER TABLE actions MODIFY COLUMN name VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci DEFAULT NULL");
    }
}
