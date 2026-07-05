<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed the condition-keyed preconditions — the data-driven home for what the
 * *Compute conditions array_push into their preConditions in code. The mapping
 * is a snapshot of the current behaviour (verified by instantiating each handler
 * and reflecting preConditions): every compute condition runs Dodge then
 * NoBerserk; the distance/technique families add Obstacle; the spell/buff
 * families add AntiSpell.
 *
 * Once these rows exist, the resolver reproduces the same preconditions in the
 * same order; the array_push in the condition classes is removed in the next
 * step.
 *
 * Idempotent: clears the seeded rows first.
 */
final class Version20260622170000_SeedConditionPreconditions extends AbstractMigration
{
    /** parent condition type => ordered precondition condition types */
    private const MAP = [
        'Compute' => ['Dodge', 'NoBerserk'],
        'ComputePure' => ['Dodge', 'NoBerserk'],
        'MeleeCompute' => ['Dodge', 'NoBerserk'],
        'MeleePureCompute' => ['Dodge', 'NoBerserk'],
        'DistanceCompute' => ['Dodge', 'NoBerserk', 'Obstacle'],
        'DistancePureCompute' => ['Dodge', 'NoBerserk', 'Obstacle'],
        'TechniqueCompute' => ['Dodge', 'NoBerserk', 'Obstacle'],
        'TechniquePureCompute' => ['Dodge', 'NoBerserk', 'Obstacle'],
        'SpellCompute' => ['Dodge', 'NoBerserk', 'Obstacle', 'AntiSpell'],
        'SpellPureCompute' => ['Dodge', 'NoBerserk', 'Obstacle', 'AntiSpell'],
        'BuffCompute' => ['Dodge', 'NoBerserk', 'AntiSpell'],
    ];

    public function getDescription(): string
    {
        return 'Seed condition-keyed preconditions (Dodge/NoBerserk/Obstacle/AntiSpell)';
    }

    public function up(Schema $schema): void
    {
        $parents = array_keys(self::MAP);
        $placeholders = implode(', ', array_fill(0, count($parents), '?'));
        $this->addSql(
            "DELETE FROM action_condition_preconditions WHERE parent_condition_type IN ($placeholders)",
            $parents
        );

        foreach (self::MAP as $parent => $preconditions) {
            foreach ($preconditions as $order => $precondition) {
                $this->addSql(
                    'INSERT INTO action_condition_preconditions (parent_condition_type, precondition_type, parameters, order_index) '
                    . 'VALUES (?, ?, NULL, ?)',
                    [$parent, $precondition, $order]
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        $parents = array_keys(self::MAP);
        $placeholders = implode(', ', array_fill(0, count($parents), '?'));
        $this->addSql(
            "DELETE FROM action_condition_preconditions WHERE parent_condition_type IN ($placeholders)",
            $parents
        );
    }
}
