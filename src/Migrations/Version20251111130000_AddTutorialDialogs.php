<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 0.2: Add tutorial_dialogs table for NPC conversations
 *
 * Stores all tutorial-related NPC dialogs (Gaïa, Master Builder, etc.)
 * in the database instead of JSON files.
 *
 * This allows:
 * - Easy editing through admin interface
 * - Versioning of dialogs
 * - No file system dependencies
 * - Better integration with tutorial steps
 */
final class Version20251111130000_AddTutorialDialogs extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tutorial_dialogs table for NPC conversations (database-first approach)';
    }

    public function up(Schema $schema): void
    {
        // Tutorial NPC dialogs table
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_dialogs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                dialog_id VARCHAR(100) NOT NULL COMMENT 'Dialog identifier (e.g., gaia_welcome, gaia_combat)',
                npc_name VARCHAR(100) NOT NULL COMMENT 'NPC name shown in dialog',
                version VARCHAR(20) NOT NULL DEFAULT '1.0.0' COMMENT 'Tutorial version this dialog belongs to',
                dialog_data JSON NOT NULL COMMENT 'Complete dialog tree with nodes and options',
                is_active BOOLEAN DEFAULT TRUE COMMENT 'Feature flag for enabling/disabling dialogs',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_dialog_id (dialog_id),
                INDEX idx_version (version),
                INDEX idx_active (is_active),
                UNIQUE KEY unique_dialog_version (dialog_id, version)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Add comment about structure
        $this->addSql("
            ALTER TABLE tutorial_dialogs
            COMMENT = 'Stores tutorial NPC dialog trees - eliminates need for JSON dialog files in tutorial mode'
        ");

        // Insert initial Gaïa welcome dialog as example
        $this->addSql("
            INSERT INTO tutorial_dialogs (dialog_id, npc_name, version, dialog_data, is_active) VALUES (
                'gaia_welcome',
                'Gaïa',
                '1.0.0',
                '{
                    \"id\": \"gaia_welcome\",
                    \"name\": \"Gaïa\",
                    \"type\": \"pnj\",
                    \"dialog\": [
                        {
                            \"id\": \"bonjour\",
                            \"text\": \"Bienvenue, petite âme! Je suis Gaïa, la mère de toutes choses. Je vais te guider dans tes premiers pas sur Olympia.\",
                            \"options\": [
                                {
                                    \"go\": \"tutorial\",
                                    \"text\": \"Je suis prêt(e) à apprendre!\"
                                }
                            ]
                        },
                        {
                            \"id\": \"tutorial\",
                            \"text\": \"Olympia est un monde complexe avec de nombreuses règles. Suis mes instructions et tu comprendras rapidement. Commençons!\",
                            \"options\": [
                                {
                                    \"go\": \"EXIT\",
                                    \"text\": \"[Commencer]\"
                                }
                            ]
                        }
                    ]
                }',
                TRUE
            )
        ");

        // Insert combat dialog
        $this->addSql("
            INSERT INTO tutorial_dialogs (dialog_id, npc_name, version, dialog_data, is_active) VALUES (
                'gaia_combat',
                'Gaïa',
                '1.0.0',
                '{
                    \"id\": \"gaia_combat\",
                    \"name\": \"Gaïa\",
                    \"type\": \"pnj\",
                    \"dialog\": [
                        {
                            \"id\": \"bonjour\",
                            \"text\": \"Il est temps d''apprendre le combat! J''ai créé une Âme d''entraînement pour toi. Ne t''inquiète pas, tu es invulnérable pendant le tutoriel.\",
                            \"options\": [
                                {
                                    \"go\": \"attack\",
                                    \"text\": \"Comment attaquer?\"
                                }
                            ]
                        },
                        {
                            \"id\": \"attack\",
                            \"text\": \"Clique sur l''ennemi, puis sur l''icône <span class=''ra ra-crossed-swords''></span>. Le combat utilise tes caractéristiques : CC (dés), F (dégâts), E (résistance).\",
                            \"options\": [
                                {
                                    \"go\": \"EXIT\",
                                    \"text\": \"[Commencer le combat]\"
                                }
                            ]
                        }
                    ]
                }',
                TRUE
            )
        ");

        // Insert completion dialog
        $this->addSql("
            INSERT INTO tutorial_dialogs (dialog_id, npc_name, version, dialog_data, is_active) VALUES (
                'gaia_completion',
                'Gaïa',
                '1.0.0',
                '{
                    \"id\": \"gaia_completion\",
                    \"name\": \"Gaïa\",
                    \"type\": \"pnj\",
                    \"dialog\": [
                        {
                            \"id\": \"bonjour\",
                            \"text\": \"Félicitations! Tu as terminé le tutoriel. Tu es maintenant prêt(e) à explorer Olympia. Que les dieux te protègent, petite âme!\",
                            \"options\": [
                                {
                                    \"go\": \"EXIT\",
                                    \"text\": \"[Partir vers Olympia]\"
                                }
                            ]
                        }
                    ]
                }',
                TRUE
            )
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS tutorial_dialogs');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
