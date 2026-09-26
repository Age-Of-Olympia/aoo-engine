<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A plan import runs step by step and commits as it goes, so a huge map no
 * longer needs one transaction and one request. This table remembers how far
 * a bundle got: the same bundle imported again resumes at its first unfinished
 * step instead of starting over.
 *
 * The bundle is identified by a fingerprint of its content, so an edited
 * bundle is a new run.
 */
final class Version20260922160000_APlanImportRemembersWhereItStopped extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Reprise d'un import de plan interrompu (table plan_import_progress)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS plan_import_progress (
                fingerprint CHAR(64) NOT NULL,
                plan VARCHAR(100) NOT NULL,
                step INT NOT NULL DEFAULT 0,
                total INT NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (fingerprint, plan)
            ) DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS plan_import_progress');
    }
}
