<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Removes the corruption and enchantment systems from the data: the
 * corrupt/… and enchant/… actions with everything that hangs on them, the
 * `enchant` instructions and ForbidOnEquipedObjectStatus conditions left
 * elsewhere, and the corruption effects (corruption_break_chance set) with
 * what their bearers carry.
 *
 * Selected by name pattern, type or column, never by id: prod has actions
 * dev lacks. Data only: effects.corruption_break_chance,
 * effect_corruption_materials and items.enchanted stay until a later
 * release drops them, so the code still deployed during this migration keeps
 * reading them.
 */
final class Version20261108120000_CorruptionAndEnchantmentRemoved extends AbstractMigration
{
    private const ACTIONS = "(name LIKE 'corrupt/%' OR name LIKE 'enchant/%')";

    public function getDescription(): string
    {
        return 'Suppression des systèmes de corruption et d\'enchantement (données)';
    }

    public function up(Schema $schema): void
    {
        $corruptions = $this->connection->fetchFirstColumn(
            'SELECT name FROM effects WHERE corruption_break_chance IS NOT NULL'
        );

        // Instructions of the other actions that enchant or apply a corruption
        $strayInstructions = [];
        foreach ($this->connection->fetchAllAssociative(
            "SELECT oi.id, oi.type, oi.parameters FROM outcome_instructions oi
               JOIN action_outcomes o ON o.id = oi.outcome_id
               JOIN actions a ON a.id = o.action_id
              WHERE a.name NOT LIKE 'corrupt/%' AND a.name NOT LIKE 'enchant/%'
                AND oi.type IN ('enchant', 'applystatus')"
        ) as $row) {
            $effect = json_decode((string) $row['parameters'], true)['effect'] ?? null;
            if ($row['type'] === 'enchant' || in_array($effect, $corruptions, true)) {
                $strayInstructions[] = (int) $row['id'];
            }
        }

        // Outcomes those deletions leave without any instruction
        $emptied = [];
        if ($strayInstructions !== []) {
            $ids = implode(',', $strayInstructions);
            $emptied = array_map('intval', $this->connection->fetchFirstColumn(
                "SELECT DISTINCT oi.outcome_id FROM outcome_instructions oi
                  WHERE oi.id IN ({$ids})
                    AND NOT EXISTS (SELECT 1 FROM outcome_instructions x
                                     WHERE x.outcome_id = oi.outcome_id AND x.id NOT IN ({$ids}))"
            ));
            $this->addSql("DELETE FROM outcome_instructions WHERE id IN ({$ids})");
        }
        if ($emptied !== []) {
            $this->addSql('DELETE FROM action_outcomes WHERE id IN (' . implode(',', $emptied) . ')');
        }

        $removed = 'SELECT id FROM actions WHERE ' . self::ACTIONS;
        $this->addSql("DELETE oi FROM outcome_instructions oi JOIN action_outcomes o ON o.id = oi.outcome_id
                        WHERE o.action_id IN ({$removed})");
        $this->addSql("DELETE FROM action_outcomes WHERE action_id IN ({$removed})");
        $this->addSql("DELETE FROM action_conditions WHERE action_id IN ({$removed}) OR conditionType = 'ForbidOnEquipedObjectStatus'");
        foreach (['race_spells', 'race_starter_actions', 'players_actions'] as $table) {
            $this->addSql("DELETE FROM {$table} WHERE " . self::ACTIONS);
        }
        // race_actions follows by cascade
        $this->addSql('DELETE FROM actions WHERE ' . self::ACTIONS);

        if ($corruptions !== []) {
            $in = implode(',', array_fill(0, count($corruptions), '?'));
            $this->addSql("DELETE FROM players_effects WHERE name IN ({$in})", $corruptions);
            $this->addSql("DELETE FROM item_effects WHERE effect IN ({$in})", $corruptions);
            $this->addSql("DELETE FROM effect_controls WHERE name IN ({$in})", $corruptions);
            // effect_corruption_materials and their own controls follow by cascade
            $this->addSql("DELETE FROM effects WHERE name IN ({$in})", $corruptions);
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The corruption and enchantment systems are removed.');
    }
}
