<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A precondition says what its failure costs.
 *
 * A shot with a blocked line left anyway: it failed like a dodge, the action
 * was paid for, and the message "votre tir s'écrase sur X" read as an attack
 * against the obstacle. Testers asked for the opposite — a gesture the
 * character sees to be impossible is not attempted, and costs nothing.
 *
 * The flag lives on the PRECONDITION, not on the parent condition. A
 * condition's `blocking` states what it is for its whole existence: a
 * DistanceCompute is not blocking, or a missed shot would be free. The
 * refusal therefore belongs to the row that pronounced it — and the code no
 * longer flips that flag mid-execution, which an unlucky flush would have
 * made permanent.
 *
 * Three preconditions refuse: Obstacle (the line is blocked), NoBerserk (the
 * anti-Berserk window is running) and AntiSpell (equipment forbids magic).
 * Dodge is not one: a dodge is a paid failure.
 */
final class Version20260830120000_APreconditionSaysWhatItsFailureCosts extends AbstractMigration
{
    /** Preconditions whose failure REFUSES the action instead of failing it. */
    private const REFUSING = ['Obstacle', 'NoBerserk', 'AntiSpell'];

    public function getDescription(): string
    {
        return 'action_condition_preconditions.blocking : un échec de précondition refuse l\'action (obstacle, anti-Berserk, anti-magie) au lieu de la faire échouer à ses frais';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            ALTER TABLE action_condition_preconditions
            ADD COLUMN IF NOT EXISTS blocking TINYINT(1) NOT NULL DEFAULT 0
        ');

        $this->addSql(
            'UPDATE action_condition_preconditions SET blocking = 1 WHERE precondition_type IN (?)',
            [self::REFUSING],
            [\Doctrine\DBAL\ArrayParameterType::STRING]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE action_condition_preconditions DROP COLUMN IF EXISTS blocking');
    }
}
