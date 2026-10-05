<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The steps of a plan import, computed once from the bundle: each request
 * loads one row instead of decoding the whole bundle again.
 */
final class Version20261006120000_PlanImportStepsStoredOnce extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Étapes d'un import de plan calculées une fois (table plan_import_steps)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS plan_import_steps (
                fingerprint CHAR(64) NOT NULL,
                plan VARCHAR(100) NOT NULL,
                step INT NOT NULL,
                label VARCHAR(255) NOT NULL,
                payload LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (fingerprint, plan, step)
            ) DEFAULT CHARSET=utf8mb4'
        );

        // A cursor saved by the previous code counts steps of another list: resuming on it would skip steps
        $this->addSql('DELETE FROM plan_import_progress');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS plan_import_steps');
    }
}
