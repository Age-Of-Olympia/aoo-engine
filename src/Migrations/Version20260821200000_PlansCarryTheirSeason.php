<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Un plan porte sa saison en colonne, plus dans son nom.
 *
 * La saison se lisait dans le suffixe _s2 du nom, avec deux listes
 * d'exceptions codées en dur (olympia et enfers « hors saison », le
 * tutoriel ignoré). La colonne `plans.season` remplace la convention :
 * NULL = le plan existe dans toutes les saisons, sinon le numéro.
 *
 * Le remplissage suit la convention qu'il remplace : suffixe _s2 → 2,
 * plans de toutes saisons (olympia, enfers, la famille du tutoriel) →
 * NULL, le reste → 1.
 *
 * La saison COURANTE devient un réglage global (admin_settings,
 * clé current_season, lu par SeasonService) — créé ici à 2, la saison
 * en cours, s'il n'existe pas déjà.
 */
final class Version20260821200000_PlansCarryTheirSeason extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'plans.season (NULL = toutes saisons) + réglage global current_season, en remplacement du suffixe _s2';
    }

    public function up(Schema $schema): void
    {
        $columnExists = (bool) $this->connection->fetchOne("
            SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'season'
        ");

        if (!$columnExists) {
            $this->addSql('ALTER TABLE plans ADD COLUMN season INT DEFAULT NULL AFTER slug');

            // Backfill only with the column: a re-run must never overwrite
            // seasons edited in the admin since.
            $this->addSql("UPDATE plans SET season = 1");
            $this->addSql("UPDATE plans SET season = 2 WHERE slug LIKE '%\\_s2'");
            $this->addSql("
                UPDATE plans SET season = NULL
                 WHERE slug IN ('olympia', 'enfers', 'tutorial') OR slug LIKE 'tut\\_%'
            ");
        }

        // Même résilience qu'AdminSettingsService : la table se crée au
        // premier besoin, le réglage ne s'écrase jamais.
        $this->addSql("
            CREATE TABLE IF NOT EXISTS admin_settings (
                name VARCHAR(64) NOT NULL PRIMARY KEY,
                value VARCHAR(255) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->addSql("INSERT IGNORE INTO admin_settings (name, value) VALUES ('current_season', '2')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plans DROP COLUMN IF EXISTS season');
        $this->addSql("DELETE FROM admin_settings WHERE name = 'current_season'");
    }
}
