<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Widen `action_passives.value` from DECIMAL(3,2) to DECIMAL(4,2).
 *
 * The entity and init_noupdates.sql already declare DECIMAL(4,2), but no
 * migration ever altered the column, so databases upgraded via migrations
 * (staging/prod) stay at DECIMAL(3,2) — max 9.99. ActionPassive::setValue()
 * number_format()s the value, so saving any passive value >= 10.00 from the
 * workbench fails with SQLSTATE 22003 on those databases only.
 *
 * Idempotent: MODIFY restates the full column definition, so re-running is a
 * no-op against a DB already at DECIMAL(4,2).
 */
final class Version20260628140000_WidenActionPassiveValue extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen action_passives.value to DECIMAL(4,2) so values >= 10 fit';
    }

    public function isTransactional(): bool
    {
        // DDL auto-commits on MySQL, so wrapping it in a transaction leaves
        // nothing to commit at the end ("no active transaction").
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE `action_passives` '
            . 'MODIFY `value` DECIMAL(4,2) DEFAULT NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE `action_passives` '
            . 'MODIFY `value` DECIMAL(3,2) DEFAULT NULL'
        );
    }
}
