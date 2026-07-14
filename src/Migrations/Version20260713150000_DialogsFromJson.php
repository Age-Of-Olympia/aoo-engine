<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Table `dialogs` : les dialogues de jeu (datas/[public|private]/dialogs/*.json)
 * migrent en base, même trajectoire que les races.
 *
 * Schéma calqué sur `tutorial_dialogs` (formats compatibles, vérifié) sans la
 * colonne `version` : les dialogues de jeu ne sont pas versionnés — le
 * tutoriel garde sa propre table. `name` est le code du fichier (marchand,
 * register, gaia…), `dialog_data` porte les nœuds (la clé "dialog" du
 * fichier), les autres champs du fichier deviennent des colonnes.
 *
 * Création de table UNIQUEMENT — pas de seed : le déploiement exécute les
 * migrations depuis le checkout git, où datas/ (gitignoré) n'existe pas
 * (leçon du seed races, cf. admin/race-seed.php). Le seed se lance depuis
 * l'admin (admin/dialog-seed.php, DialogSeedService), où datas/ existe.
 * En attendant le seed, DialogService replie sur les fichiers JSON.
 *
 * Idempotente (IF NOT EXISTS), compatible --no-all-or-nothing.
 */
final class Version20260713150000_DialogsFromJson extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table dialogs (dialogues de jeu en base, seed legacy via admin/dialog-seed.php)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS dialogs (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(100) NOT NULL,
                npc_name VARCHAR(100) NOT NULL DEFAULT 'TARGET_NAME',
                type VARCHAR(20) NOT NULL DEFAULT 'pnj',
                custom VARCHAR(255) NOT NULL DEFAULT '',
                dialog_data LONGTEXT NOT NULL CHECK (json_valid(dialog_data)),
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_dialog_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS dialogs');
    }
}
