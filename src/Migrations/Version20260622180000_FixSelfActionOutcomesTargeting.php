<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Mark the run and train outcomes as applying to the actor. Both `courir` and
 * `entrainement` affect the player who performs them, yet their outcomes were
 * seeded with apply_to_self = 0, which made them read as target-only (they ask
 * for a victim that does not exist). The flag is a targeting/display hint only —
 * the executor passes both actor and target regardless and each instruction
 * picks who it mutates — so this corrects classification without changing how
 * the actions resolve at runtime.
 *
 * Idempotent: keyed by outcome name.
 */
final class Version20260622180000_FixSelfActionOutcomesTargeting extends AbstractMigration
{
    private const SELF_OUTCOMES = ['run_effect', 'train_effect'];

    public function getDescription(): string
    {
        return 'Mark run/train outcomes as apply_to_self (self-targeting actions)';
    }

    public function up(Schema $schema): void
    {
        $placeholders = implode(', ', array_fill(0, count(self::SELF_OUTCOMES), '?'));
        $this->addSql(
            "UPDATE action_outcomes SET apply_to_self = 1 WHERE name IN ($placeholders)",
            self::SELF_OUTCOMES
        );
    }

    public function down(Schema $schema): void
    {
        $placeholders = implode(', ', array_fill(0, count(self::SELF_OUTCOMES), '?'));
        $this->addSql(
            "UPDATE action_outcomes SET apply_to_self = 0 WHERE name IN ($placeholders)",
            self::SELF_OUTCOMES
        );
    }
}
