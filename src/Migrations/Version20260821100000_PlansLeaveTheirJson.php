<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les plans quittent leurs fichiers JSON.
 *
 * Chaque plan était configuré par datas/private/plans/<plan>.json — un
 * répertoire hors dépôt que le déploiement ne touche jamais : chaque nouveau
 * champ se patchait à la main sur chaque serveur. La configuration vit
 * désormais en base : une ligne `plans` par plan (identifiée par `slug`, la
 * valeur que `coords.plan` porte déjà), et ses niveaux dans `plan_z_levels`.
 *
 * Le schéma seulement : le SEED lit les JSON de l'environnement, qui
 * n'existent pas dans le checkout git où tournent les migrations (la leçon
 * des races). Il se rejoue depuis l'admin (Cartes → Seed des plans) ou
 * `php scripts/seed-plans.php`.
 *
 * Champs volontairement absents, morts avec le voyage inter-plans (exits,
 * enters — supprimé en 066f7b6c) ou jamais lus (id, num_z_levels). `biomes`
 * reste porté en JSON brut : c'est la graine de `race_harvest`, plus jamais
 * une lecture de jeu.
 *
 * `slug` en utf8mb4_general_ci comme les autres clés de jointure
 * (Version20260727110000_JoinKeyCollations) : il se compare à coords.plan.
 */
final class Version20260821100000_PlansLeaveTheirJson extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'plans et plan_z_levels : la configuration des plans vit en base, plus dans datas/private/plans';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS plans (
                id INT AUTO_INCREMENT NOT NULL,
                slug VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                name VARCHAR(255) NOT NULL,
                short_name VARCHAR(255) DEFAULT NULL,
                x INT DEFAULT NULL,
                y INT DEFAULT NULL,
                player_visibility TINYINT(1) NOT NULL DEFAULT 1,
                visible_by_default TINYINT(1) NOT NULL DEFAULT 0,
                pnj INT DEFAULT NULL,
                size INT DEFAULT NULL,
                bg VARCHAR(255) DEFAULT NULL,
                mask VARCHAR(255) DEFAULT NULL,
                scrolling_mask DOUBLE DEFAULT NULL,
                vertical_scrolling TINYINT(1) NOT NULL DEFAULT 0,
                shade_step DOUBLE DEFAULT NULL,
                shade_max INT DEFAULT NULL,
                shade_color VARCHAR(7) DEFAULT NULL,
                visible_bounds_min_x INT DEFAULT NULL,
                visible_bounds_max_x INT DEFAULT NULL,
                visible_bounds_min_y INT DEFAULT NULL,
                visible_bounds_max_y INT DEFAULT NULL,
                biomes LONGTEXT DEFAULT NULL,
                UNIQUE INDEX uniq_plans_slug (slug),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        ");

        $this->addSql("
            CREATE TABLE IF NOT EXISTS plan_z_levels (
                id INT AUTO_INCREMENT NOT NULL,
                plan_id INT NOT NULL,
                z INT NOT NULL,
                name VARCHAR(255) NOT NULL DEFAULT '',
                map_unavailable TINYINT(1) NOT NULL DEFAULT 0,
                visible_bounds_min_x INT DEFAULT NULL,
                visible_bounds_max_x INT DEFAULT NULL,
                visible_bounds_min_y INT DEFAULT NULL,
                visible_bounds_max_y INT DEFAULT NULL,
                UNIQUE INDEX uniq_plan_z (plan_id, z),
                PRIMARY KEY (id),
                CONSTRAINT fk_plan_z_levels_plan
                    FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS plan_z_levels');
        $this->addSql('DROP TABLE IF EXISTS plans');
    }
}
