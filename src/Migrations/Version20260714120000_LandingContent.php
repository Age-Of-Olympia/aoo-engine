<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Contenu éditorial de la page d'accueil, éditable depuis l'admin :
 *
 * - `landing_sections` : blocs de texte (présentation du jeu…) — un slug
 *   par emplacement, le corps est du HTML confiance-admin ;
 * - `landing_news` : « dernières chroniques », une entrée datée par
 *   évènement, la page affiche les plus récentes ;
 * - `landing_images` : galerie d'aperçus (chemin img/, légende).
 *
 * Une section/entrée/image inactive ou absente ne se rend pas : la page
 * dégrade vers la composition actuelle. Création de tables uniquement,
 * le contenu se saisit dans l'admin (admin/landing.php).
 *
 * Idempotente (IF NOT EXISTS), compatible --no-all-or-nothing.
 */
final class Version20260714120000_LandingContent extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tables landing_sections / landing_news / landing_images (contenu éditorial de l\'accueil)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS landing_sections (
                id INT AUTO_INCREMENT NOT NULL,
                slug VARCHAR(50) NOT NULL,
                title VARCHAR(255) NOT NULL,
                body LONGTEXT NOT NULL,
                position INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_landing_section_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS landing_news (
                id INT AUTO_INCREMENT NOT NULL,
                news_date DATE NOT NULL,
                title VARCHAR(255) NOT NULL,
                text LONGTEXT NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_landing_news_date (news_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS landing_images (
                id INT AUTO_INCREMENT NOT NULL,
                path VARCHAR(255) NOT NULL,
                plate_path VARCHAR(255) NOT NULL DEFAULT '',
                caption VARCHAR(255) NOT NULL DEFAULT '',
                position INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS landing_sections');
        $this->addSql('DROP TABLE IF EXISTS landing_news');
        $this->addSql('DROP TABLE IF EXISTS landing_images');
    }
}
