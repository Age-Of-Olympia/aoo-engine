<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Replace action_outcomes.apply_to_self (boolean) with apply_to
 * ('self'|'target'|'both') so an outcome can declare it applies to either the
 * caster or a target — configurable in the workbench instead of hardcoded.
 *
 * This retires ActionTargeting's "a buff is always self" override: the derived
 * scope now trusts apply_to alone. Two data fixes ship with the backfill:
 *  - success outcomes of spell-support actions (buffs like coup_precis AND the
 *    spell-support heals like regeneration) become 'both' — support spells are
 *    castable on yourself or an ally, but were stored target-only and rendered
 *    self-only by the old override;
 *  - buff_pasleger becomes 'self': every other stealth-buff outcome is
 *    apply_to_self = 1, its 0 was a seeding inconsistency the old override
 *    masked.
 *
 * The flag is targeting/display metadata only (the executor passes actor and
 * target to every instruction regardless), so nothing changes at resolution
 * time. Skipped when init_noupdates.sql already performed the same migration
 * on a fresh install.
 */
final class Version20260705120000_OutcomeApplyToTriState extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Replace action_outcomes.apply_to_self with tri-state apply_to ('self'|'target'|'both')";
    }

    public function isTransactional(): bool
    {
        // DDL auto-commits on MySQL/MariaDB, so wrapping it in a transaction
        // leaves nothing to commit at the end ("no active transaction").
        return false;
    }

    public function up(Schema $schema): void
    {
        $columns = $this->connection->createSchemaManager()->listTableColumns('action_outcomes');
        $this->skipIf(
            isset($columns['apply_to']) && !isset($columns['apply_to_self']),
            'apply_to already migrated (fresh install via init_noupdates.sql)'
        );

        $this->addSql("ALTER TABLE action_outcomes ADD COLUMN IF NOT EXISTS apply_to VARCHAR(10) NOT NULL DEFAULT 'target' AFTER apply_to_self");
        $this->addSql("UPDATE action_outcomes SET apply_to = IF(apply_to_self = 1, 'self', 'target')");
        $this->addSql(
            "UPDATE action_outcomes o JOIN actions a ON a.id = o.action_id
             SET o.apply_to = 'both'
             WHERE a.category = 'spell-support' AND o.on_success = 1"
        );
        $this->addSql("UPDATE action_outcomes SET apply_to = 'self' WHERE name = 'buff_pasleger'");
        $this->addSql("ALTER TABLE action_outcomes DROP COLUMN IF EXISTS apply_to_self");
    }

    public function down(Schema $schema): void
    {
        // 'both' collapses to 0 (target): the boolean cannot express it, and
        // target-side is what the pre-migration data carried for those rows.
        $this->addSql("ALTER TABLE action_outcomes ADD COLUMN IF NOT EXISTS apply_to_self TINYINT(1) NOT NULL DEFAULT 0");
        $this->addSql("UPDATE action_outcomes SET apply_to_self = IF(apply_to = 'self', 1, 0)");
        $this->addSql("UPDATE action_outcomes SET apply_to_self = 0 WHERE name = 'buff_pasleger'");
        $this->addSql("ALTER TABLE action_outcomes DROP COLUMN IF EXISTS apply_to");
    }
}
