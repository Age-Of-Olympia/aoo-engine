<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed the global "enfers" precondition — the data-driven home for the
 * PlanCondition that BaseCondition::checkPreconditions used to array_unshift on
 * every condition check. An empty type_key means it applies to every action, so
 * the resolver runs it once before each action's own conditions (including for
 * actions with no conditions of their own, which the code path silently missed).
 *
 * Blocking, params {"plan":"enfers"} — identical to the old default. PlanCondition
 * still whitelists 'prier' internally.
 *
 * Idempotent: clears any existing global Plan row first.
 */
final class Version20260622150000_SeedGlobalPlanPrecondition extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Seed the global Plan/enfers precondition (was hardcoded in BaseCondition)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM action_type_preconditions WHERE type_key = '' AND condition_type = 'Plan'");
        $this->addSql(
            'INSERT INTO action_type_preconditions (type_key, condition_type, parameters, order_index, blocking) '
            . "VALUES ('', 'Plan', :params, 0, 1)",
            ['params' => '{"plan":"enfers"}']
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM action_type_preconditions WHERE type_key = '' AND condition_type = 'Plan'");
    }
}
