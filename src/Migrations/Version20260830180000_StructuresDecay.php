<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Player-built constructions decay — see docs/design-decay-structures.md.
 *
 * Three things ship here, and nothing calls them yet:
 *
 * - `entity_decay`, whose very MEMBERSHIP is the rule's criterion. A row is
 *   written when a player builds; what Tiled placed has none and is never
 *   read. `owner_id` could not serve, since `BuildingService::place()` sets
 *   it on the admin's path too — it means "somebody owns this", not "a
 *   player built this".
 *
 * - `decay_from`, the single clock. It says "decay is owed from this
 *   instant", so the grace lives inside it: `touch()` pushes it to
 *   now + grace, the pass advances it by whole turns. A separate last-used
 *   stamp would let the catch-up bill the turns during which the building
 *   was in USE — six months of use becoming six months of decay the moment
 *   the grace lapsed.
 *
 * - the two dials, global in `admin_settings` and overridable per type on
 *   `races` (NULL = follow the global). The figures are placeholders on
 *   purpose: they are meant to be chosen once decay can be watched in play,
 *   and the admin screen corrects them without a deployment.
 *
 * Structure types also move to `spd` 16, the players' value, so a structure
 * turn lasts 18 h like everyone else's. They sat at 0 and 2, which would
 * have given them 34 h and 32 h turns for no reason but nobody having
 * looked. `spd` is read only by the turn duration and the caracs display,
 * so nothing else moves.
 */
final class Version20260830180000_StructuresDecay extends AbstractMigration
{
    /** Placeholders — the real figures are an admin decision, taken later. */
    private const DEFAULT_RATE = 1;
    private const DEFAULT_GRACE_TURNS = 3;

    /** The players' speed: an 18 h turn through TurnScheduleService. */
    private const STRUCTURE_SPD = 16;

    public function getDescription(): string
    {
        return 'décrépitude des constructions : table entity_decay, molettes globales et surcharges par type, et les types de structure passent à spd 16 (tour de 18 h)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS entity_decay (
                player_id  INT NOT NULL,
                decay_from INT NOT NULL,
                PRIMARY KEY (player_id),
                KEY idx_due (decay_from),
                CONSTRAINT fk_entity_decay_player FOREIGN KEY (player_id)
                    REFERENCES players (id) ON DELETE CASCADE
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        /* NULL, not 0: zero is a rate meaning "loses nothing", while the
           absence of a figure means "follow the global dial". */
        if (!$this->columnExists('races', 'decay_rate')) {
            $this->addSql('ALTER TABLE races ADD decay_rate INT DEFAULT NULL');
        }
        if (!$this->columnExists('races', 'decay_grace')) {
            $this->addSql('ALTER TABLE races ADD decay_grace INT DEFAULT NULL');
        }

        $this->addSql(
            "INSERT INTO admin_settings (name, value) VALUES ('decay_rate_default', ?)
             ON DUPLICATE KEY UPDATE value = value",
            [(string) self::DEFAULT_RATE]
        );
        $this->addSql(
            "INSERT INTO admin_settings (name, value) VALUES ('decay_grace_turns', ?)
             ON DUPLICATE KEY UPDATE value = value",
            [(string) self::DEFAULT_GRACE_TURNS]
        );

        $this->addSql(
            'UPDATE races SET spd = ? WHERE kind = ? AND spd <> ?',
            [self::STRUCTURE_SPD, 'structure', self::STRUCTURE_SPD]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS entity_decay');
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS decay_rate');
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS decay_grace');
        $this->addSql("DELETE FROM admin_settings WHERE name IN ('decay_rate_default', 'decay_grace_turns')");
        /* The previous speeds are not restored: they were 0 and 2 by
           omission, not by decision, and putting them back would hand a
           34 h turn to a structure the game now expects to beat at 18. */
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }
}
