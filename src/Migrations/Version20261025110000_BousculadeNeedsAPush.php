<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The bousculade's push roll becomes a non-blocking condition: a target that
 * holds makes the action a failure, so it neither moves nor gains stability.
 */
final class Version20261025110000_BousculadeNeedsAPush extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bousculade : condition Push non bloquante';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context)
            SELECT 'Push', '{}', a.id, 10, 0, 0 FROM actions a
            WHERE a.name = 'bousculade'
              AND NOT EXISTS (SELECT 1 FROM action_conditions ac WHERE ac.action_id = a.id AND ac.conditionType = 'Push')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE ac FROM action_conditions ac JOIN actions a ON a.id = ac.action_id
            WHERE a.name = 'bousculade' AND ac.conditionType = 'Push'");
    }
}
