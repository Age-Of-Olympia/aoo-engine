<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les réglages de la réputation quittent le code pour la base, et se règlent
 * depuis l'écran d'admin Le monde › Réputation.
 *
 * - admin_settings : gains par post et par cookie, palier de récompense
 *   forum, délai des cookies, cookies dans les missives.
 * - reputation_titles : les titres et leur seuil en PR ; celui de seuil 0
 *   est le titre de base, toujours présent.
 * - reputation_reward_tiers : le tirage des récompenses forum. Quatre paliers
 *   fixes, car l'image d'un palier est <dossier>/<tier>.png ; le PR versé et
 *   le poids se règlent.
 *
 * Semées avec les valeurs codées en dur jusqu'ici. Un réglage existant n'est
 * pas écrasé.
 */
final class Version20261029100000_ReputationIsConfigurable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'réglages de la réputation en base (gains, palier, cookies, titres, tirage des récompenses)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS admin_settings (
                name VARCHAR(64) NOT NULL PRIMARY KEY,
                value VARCHAR(255) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->addSql("
            INSERT IGNORE INTO admin_settings (name, value) VALUES
                ('reputation_per_post', '1'),
                ('reputation_per_cookie', '0.3'),
                ('reputation_per_cookie_same_faction', '0.1'),
                ('reputation_reward_every', '5'),
                ('forum_cookie_max_days', '14'),
                ('forum_missive_cookies', '1')
        ");

        $this->addSql("
            CREATE TABLE IF NOT EXISTS reputation_titles (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                threshold INT NOT NULL,
                title VARCHAR(64) NOT NULL,
                UNIQUE KEY uniq_reputation_titles_threshold (threshold)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->addSql("
            INSERT IGNORE INTO reputation_titles (threshold, title) VALUES
                (0, 'Inconnu'),
                (25, 'Connu'),
                (100, 'Populaire'),
                (250, 'Héroïque'),
                (500, 'Légendaire'),
                (1250, 'Mythologique'),
                (2500, 'Divin')
        ");

        $this->addSql("
            CREATE TABLE IF NOT EXISTS reputation_reward_tiers (
                tier TINYINT NOT NULL PRIMARY KEY,
                pr INT NOT NULL,
                weight DECIMAL(6,2) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->addSql("
            INSERT IGNORE INTO reputation_reward_tiers (tier, pr, weight) VALUES
                (1, 1, 4),
                (2, 2, 2),
                (3, 3, 1.33),
                (4, 4, 1)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS reputation_reward_tiers');
        $this->addSql('DROP TABLE IF EXISTS reputation_titles');
        $this->addSql("
            DELETE FROM admin_settings WHERE name IN (
                'reputation_per_post', 'reputation_per_cookie', 'reputation_per_cookie_same_faction',
                'reputation_reward_every', 'forum_cookie_max_days', 'forum_missive_cookies'
            )
        ");
    }
}
