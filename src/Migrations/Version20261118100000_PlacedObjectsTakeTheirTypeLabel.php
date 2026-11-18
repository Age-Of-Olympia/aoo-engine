<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Objects placed in bulk got a name built from their type code (pilier →
 * « Pilier », torche_sol → « Torche sol ») instead of the type's label.
 * Renaming a type only renames instances named after its old label, so these
 * never followed and the selection card showed the code.
 *
 * Only names equal to the generated one are touched: a name given by hand
 * (« Autel de Hermès ») is kept. Idempotent; no down, the old names were a bug.
 */
final class Version20261118100000_PlacedObjectsTakeTheirTypeLabel extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'objets posés : le nom affiché de leur type remplace le nom tiré du code';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            UPDATE players p
              JOIN races r ON CONVERT(r.name USING utf8mb4) = CONVERT(p.race USING utf8mb4)
               SET p.name = r.label
             WHERE r.kind = 'structure'
               AND r.label <> ''
               AND p.name <> r.label
               AND p.name = CONCAT(UPPER(LEFT(REPLACE(p.race, '_', ' '), 1)), SUBSTRING(REPLACE(p.race, '_', ' '), 2))
        ");
    }

    public function down(Schema $schema): void
    {
    }
}
