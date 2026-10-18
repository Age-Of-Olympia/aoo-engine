<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drops what the season 3 migrations kept for their deploy window, now that
 * the code which read it is gone everywhere:
 *
 * - races.m, items.m: replaced by pui/res (Version20260721100000, Version20260722200000);
 * - races.animateurId: moved to factions (Version20261011120000);
 * - races.repairable: replaced by repair recipes per type (Version20261008140000);
 * - effects.is_map_marker: replaced by map_marks (Version20260914120000);
 * - effects.buff_carac / debuff_carac: folded into carac_mods (Version20260919150000);
 * - players_reduction_passives: created by db/updates, never used;
 * - view map_walls: compat view of the map_resources rename (Version20260720130000);
 * - players_actions / race_starter_actions rows of ouvrir, fermer, ecrire:
 *   innate since Version20261018120000.
 *
 * races.opens_the_way is unmapped in this release and dropped in the next one.
 * Idempotent; down() restores the columns empty, not their values.
 */
final class Version20261018160000_SeasonTwoLeftovers extends AbstractMigration
{
    private const INNATE = "'ouvrir', 'fermer', 'ecrire'";

    public function getDescription(): string
    {
        return 'ménage saison 3 : colonnes et vue gardées pour la fenêtre de déploiement, lignes des actions acquises d\'office';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS m, DROP COLUMN IF EXISTS animateurId, DROP COLUMN IF EXISTS repairable');
        $this->addSql('ALTER TABLE items DROP COLUMN IF EXISTS m');
        $this->addSql('ALTER TABLE effects DROP COLUMN IF EXISTS is_map_marker, DROP COLUMN IF EXISTS buff_carac, DROP COLUMN IF EXISTS debuff_carac');
        $this->addSql('DROP TABLE IF EXISTS players_reduction_passives');
        $this->addSql('DROP VIEW IF EXISTS map_walls');
        $this->addSql('DELETE FROM players_actions WHERE name IN (' . self::INNATE . ')');
        $this->addSql('DELETE FROM race_starter_actions WHERE name IN (' . self::INNATE . ')');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races ADD COLUMN IF NOT EXISTS m INT NOT NULL DEFAULT 0, ADD COLUMN IF NOT EXISTS animateurId INT NULL DEFAULT NULL, ADD COLUMN IF NOT EXISTS repairable TINYINT(1) NULL DEFAULT NULL');
        $this->addSql('ALTER TABLE items ADD COLUMN IF NOT EXISTS m INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE effects ADD COLUMN IF NOT EXISTS is_map_marker TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN IF NOT EXISTS buff_carac VARCHAR(10) DEFAULT NULL, ADD COLUMN IF NOT EXISTS debuff_carac VARCHAR(10) DEFAULT NULL');
        $this->addSql('CREATE OR REPLACE SQL SECURITY INVOKER VIEW map_walls AS SELECT * FROM map_resources');
    }
}
