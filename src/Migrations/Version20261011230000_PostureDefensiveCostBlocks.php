<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * posture_defensive: its cost condition was non-blocking, so a player short
 * of A or PM still cast it, failed, and was charged what was left. A
 * blocking cost refuses instead, like every other action. TargetType gets
 * the order 0 its siblings have.
 */
final class Version20261011230000_PostureDefensiveCostBlocks extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'posture_defensive : coût bloquant (plus de points perdus sans effet), TargetType en ordre 0';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE action_conditions c
               JOIN actions a ON a.id = c.action_id
                SET c.blocking = 1
              WHERE a.name = 'posture_defensive' AND c.conditionType = 'RequiresTraitValue'"
        );
        $this->addSql(
            "UPDATE action_conditions c
               JOIN actions a ON a.id = c.action_id
                SET c.execution_order = 0
              WHERE a.name = 'posture_defensive' AND c.conditionType = 'TargetType'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE action_conditions c
               JOIN actions a ON a.id = c.action_id
                SET c.blocking = 0
              WHERE a.name = 'posture_defensive' AND c.conditionType = 'RequiresTraitValue'"
        );
        $this->addSql(
            "UPDATE action_conditions c
               JOIN actions a ON a.id = c.action_id
                SET c.execution_order = NULL
              WHERE a.name = 'posture_defensive' AND c.conditionType = 'TargetType'"
        );
    }
}
