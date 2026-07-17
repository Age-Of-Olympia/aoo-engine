<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Items Phase 3 (docs/design-items-instances.md §3.3) — the map bridge:
 *
 * - unique_objects.item_instance_id: a UniqueObject can WRAP an item
 *   instance — a dropped named sword or a placed artifact is a map
 *   entity (position, observable, attackable) whose identity is the
 *   instance. Ground stacks of fungibles stay map_items.
 * - structure race 'objet' (kind structure, hidden, pv 25): the default
 *   base-stats row of a wrapped item on the map.
 * - action 'ramasser': TargetType ['structure'], adjacent, free —
 *   takes the wrapped instance back into the actor's inventory through
 *   the TakeItem instruction (the identity survives the round trip).
 *   Catalog-only, comme ses sœurs.
 */
final class Version20260717160000_UniqueObjectBridge extends AbstractMigration
{
    public function getDescription(): string
    {
        return "unique_objects.item_instance_id + race 'objet' + action ramasser";
    }

    public function up(Schema $schema): void
    {
        $hasColumn = (bool) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unique_objects' AND COLUMN_NAME = 'item_instance_id'"
        );
        if (!$hasColumn) {
            $this->addSql(
                'ALTER TABLE unique_objects
                 ADD item_instance_id INT DEFAULT NULL,
                 ADD CONSTRAINT fk_unique_objects_instance FOREIGN KEY (item_instance_id) REFERENCES item_instances (id)'
            );
        }

        $this->addSql(
            "INSERT IGNORE INTO races
                (code, name, label, description, playable, hidden, kind, bgColor, color, faction, plan, pv)
             VALUES
                ('OBJET', 'objet', 'Objet',
                 'Un objet posé là — ramassable, ou destructible.',
                 0, 1, 'structure', '#9a8866', 'black', '', '', 25)"
        );

        $exists = $this->connection->fetchOne("SELECT id FROM actions WHERE name = 'ramasser'");
        if ($exists !== false) {
            return;
        }

        $this->addSql(
            "INSERT INTO actions (name, icon, type, display_name, text, level)
             VALUES ('ramasser', 'ra-hand', 'buff', 'Ramasser',
                     'Prend l''objet posé sur la case voisine.', 1)"
        );
        foreach ([
            ['TargetType', ['allowed' => ['structure']], 0],
            ['RequiresDistance', ['max' => 1], 1],
        ] as [$type, $params, $order]) {
            $this->addSql(
                "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking)
                 SELECT ?, ?, id, ?, 1 FROM actions WHERE name = 'ramasser'",
                [$type, json_encode($params), $order]
            );
        }
        $this->addSql(
            "INSERT INTO action_outcomes (apply_to, name, on_success, action_id)
             SELECT 'target', 'take_item', 1, id FROM actions WHERE name = 'ramasser'"
        );
        $this->addSql(
            "INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
             SELECT 'takeitem', '{}', 0, o.id
             FROM action_outcomes o JOIN actions a ON a.id = o.action_id
             WHERE a.name = 'ramasser' AND o.name = 'take_item'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "DELETE oi FROM outcome_instructions oi
             JOIN action_outcomes o ON o.id = oi.outcome_id
             JOIN actions a ON a.id = o.action_id WHERE a.name = 'ramasser'"
        );
        $this->addSql("DELETE o FROM action_outcomes o JOIN actions a ON a.id = o.action_id WHERE a.name = 'ramasser'");
        $this->addSql("DELETE FROM action_conditions WHERE action_id IN (SELECT id FROM actions WHERE name = 'ramasser')");
        $this->addSql("DELETE FROM actions WHERE name = 'ramasser'");
        $this->addSql('ALTER TABLE unique_objects DROP FOREIGN KEY fk_unique_objects_instance, DROP COLUMN item_instance_id');
        $this->addSql("DELETE FROM races WHERE name = 'objet' AND playable = 0");
    }
}
