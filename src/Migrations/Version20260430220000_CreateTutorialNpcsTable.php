<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add `tutorial_npcs` and seed the v1.0.0 entries (Gaïa guide + dummy
 * enemy) for environments that already ran Version20251127000000
 * before the table existed.
 *
 * Also retires the legacy hardcoded Gaïa players row at id=-999999.
 * From this migration on, NPCs are spawned data-driven from
 * tutorial_npcs by TutorialMapInstance (template) and
 * TutorialResourceManager (dynamic).
 *
 * Idempotent:
 *   - CREATE TABLE IF NOT EXISTS
 *   - INSERT IGNORE on the seed rows (a hand-curated row via the
 *     admin UI for the same role+version is not clobbered)
 *   - DELETE of -999999 only fires when the row matches the original
 *     fingerprint (race='dieu' AND name='Gaïa').
 */
final class Version20260430220000_CreateTutorialNpcsTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Create tutorial_npcs and seed v1.0.0 (Gaïa + dummy); retire legacy -999999 Gaïa row";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS tutorial_npcs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                version VARCHAR(20) NOT NULL DEFAULT '1.0.0',
                role VARCHAR(50) NOT NULL COMMENT 'Free-text label: guide, enemy, …',
                spawn_mode ENUM('template', 'dynamic') NOT NULL,
                x INT NOT NULL DEFAULT 0 COMMENT 'Absolute X (template) or offset from player (dynamic)',
                y INT NOT NULL DEFAULT 0 COMMENT 'Absolute Y (template) or offset from player (dynamic)',
                name VARCHAR(255) NOT NULL,
                race VARCHAR(50) NOT NULL,
                avatar VARCHAR(500) NOT NULL,
                portrait VARCHAR(500) NOT NULL,
                faction VARCHAR(50) DEFAULT '',
                text TEXT,
                energie INT NOT NULL DEFAULT 100,
                spawn_at_step_id INT NULL COMMENT 'Dynamic NPCs only: step that triggers the spawn. NULL = at session start',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_version_active (version, is_active),
                KEY idx_spawn_mode (spawn_mode),
                FOREIGN KEY (spawn_at_step_id) REFERENCES tutorial_steps(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Tutorial NPC roster + placement (replaces hardcoded Gaïa + dummy)'
        ");

        // Seed v1.0.0 rows. INSERT IGNORE on (version, role, spawn_mode)
        // would need a unique key; instead use NOT EXISTS guards so a
        // hand-curated row via admin UI survives a re-migration.
        $this->addSql("
            INSERT INTO tutorial_npcs
                (version, role, spawn_mode, x, y, name, race, avatar, portrait, faction, text, energie, is_active)
            SELECT '1.0.0', 'guide', 'template', 1, 0,
                   'Gaïa', 'dieu',
                   'img/avatars/dieu/25.png', 'img/portraits/dieu/1.jpeg',
                   '',
                   'Gaïa, déesse de la Terre, guide les nouveaux joueurs dans leur apprentissage.',
                   100, 1
            WHERE NOT EXISTS (
                SELECT 1 FROM tutorial_npcs
                WHERE version = '1.0.0' AND role = 'guide' AND spawn_mode = 'template'
            )
        ");
        $this->addSql("
            INSERT INTO tutorial_npcs
                (version, role, spawn_mode, x, y, name, race, avatar, portrait, faction, text, energie, is_active)
            SELECT '1.0.0', 'enemy', 'dynamic', 2, 1,
                   'Âme d''entraînement', 'ame',
                   'img/avatars/ame/default.webp', 'img/portraits/ame/1.jpeg',
                   '',
                   'Âme d''entraînement pour le tutoriel',
                   100, 1
            WHERE NOT EXISTS (
                SELECT 1 FROM tutorial_npcs
                WHERE version = '1.0.0' AND role = 'enemy' AND spawn_mode = 'dynamic'
            )
        ");

        // Retire the legacy template Gaïa row in players. From now on,
        // TutorialMapInstance spawns Gaïa per-session from tutorial_npcs;
        // the global -999999 placeholder row is no longer needed.
        // Scoped on the original fingerprint so a hand-customized
        // -999999 row (someone overrode race/name) is left alone.
        $this->addSql("
            DELETE FROM players
            WHERE id = -999999
              AND name = 'Gaïa'
              AND race = 'dieu'
        ");
    }

    public function down(Schema $schema): void
    {
        // Restore the legacy Gaïa row at the original template position
        // if it isn't there. Spawn coords lookup mirrors the original
        // INSERT in Version20251127000000.
        $this->addSql("
            INSERT IGNORE INTO players
                (id, player_type, display_id, name, coords_id, race, xp, pi, energie,
                 psw, mail, plain_mail, avatar, portrait, text)
            SELECT -999999, 'npc', 999999, 'Gaïa', c.id, 'dieu', 0, 0, 100,
                   '', '', '',
                   'img/avatars/dieu/25.png', 'img/portraits/dieu/1.jpeg',
                   'Gaïa, déesse de la Terre, guide les nouveaux joueurs dans leur apprentissage.'
            FROM coords c
            WHERE c.plan = 'tutorial' AND c.z = 0 AND c.x = 1 AND c.y = 0
            LIMIT 1
        ");

        $this->addSql("DROP TABLE IF EXISTS tutorial_npcs");
    }
}
