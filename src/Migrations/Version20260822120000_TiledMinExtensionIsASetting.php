<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La version minimale de l'extension Tiled devient un réglage.
 *
 * L'extension annonce sa version (en-tête X-AoO-Tiled-Version) et les
 * endpoints api/admin/map/* refusent en dessous d'une barre. Cette barre
 * vit dans admin_settings et se règle depuis le tableau de bord : relever
 * l'exigence après une release de l'extension ne demande pas de
 * déploiement.
 *
 * Semée à 0.4.0, la première version qui annonce la sienne — tout ce qui
 * précède est muet, donc refusé. Un réglage existant n'est pas écrasé.
 */
final class Version20260822120000_TiledMinExtensionIsASetting extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'réglage tiled_min_extension (version minimale de l\'extension Tiled acceptée par l\'instance)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS admin_settings (
                name VARCHAR(64) NOT NULL PRIMARY KEY,
                value VARCHAR(255) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->addSql("INSERT IGNORE INTO admin_settings (name, value) VALUES ('tiled_min_extension', '0.4.0')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM admin_settings WHERE name = 'tiled_min_extension'");
    }
}
