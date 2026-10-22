<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Adds Donner to the shared action catalogue as an action known by everyone. */
final class Version20261022110000_DonnerAction extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Donner as an innate, contact-only character action';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO actions (name, icon, type, display_name, text, level, category, innate)
             SELECT 'donner', 'ra-hand', 'give', 'Donner', 'Propose un échange d’objets à un personnage au contact.', 1, 'utility', 1
             WHERE NOT EXISTS (SELECT 1 FROM actions WHERE name = 'donner')"
        );
        $this->addSql("UPDATE actions SET innate = 1 WHERE name = 'donner'");
        $this->addSql(
            "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context)
             SELECT 'TargetType', ?, id, 0, 1, 0 FROM actions
             WHERE name = 'donner'
               AND NOT EXISTS (SELECT 1 FROM action_conditions c WHERE c.action_id = actions.id AND c.conditionType = 'TargetType')",
            [json_encode(['allowed' => ['character']])]
        );
        $this->addSql(
            "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context)
             SELECT 'RequiresDistance', ?, id, 1, 1, 1 FROM actions
             WHERE name = 'donner'
               AND NOT EXISTS (SELECT 1 FROM action_conditions c WHERE c.action_id = actions.id AND c.conditionType = 'RequiresDistance')",
            [json_encode(['max' => 1])]
        );
        $this->addSql(
            "INSERT INTO action_outcomes (apply_to, name, on_success, action_id)
             SELECT 'target', 'exchange', 1, id FROM actions
             WHERE name = 'donner'
               AND NOT EXISTS (SELECT 1 FROM action_outcomes o WHERE o.action_id = actions.id AND o.name = 'exchange')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE c FROM action_conditions c JOIN actions a ON a.id = c.action_id WHERE a.name = 'donner'");
        $this->addSql("DELETE o FROM action_outcomes o JOIN actions a ON a.id = o.action_id WHERE a.name = 'donner'");
        $this->addSql("DELETE FROM actions WHERE name = 'donner'");
    }
}
