<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Attache la condition BuildSite (validation bloquante de la case
 * choisie — voir BuildSiteCondition) à toutes les actions de
 * construction : un refus de case n'engage aucun coût.
 */
final class Version20260717210000_BuildSiteCondition extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Attache BuildSite aux actions construire_%';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking)
             SELECT 'BuildSite', '{}', a.id, 2, 1
             FROM actions a
             WHERE a.name LIKE 'construire\\_%'
               AND NOT EXISTS (
                   SELECT 1 FROM action_conditions c
                   WHERE c.action_id = a.id AND c.conditionType = 'BuildSite'
               )"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM action_conditions WHERE conditionType = 'BuildSite'");
    }
}
