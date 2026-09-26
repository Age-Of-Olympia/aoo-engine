<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * An element type names the effect it applies instead of being it.
 *
 * A type without a row keeps the effect of its own name, so nothing moves
 * at deployment; a row with no effect makes the type decor. Deleting an
 * effect turns the types that applied it into decor.
 */
final class Version20260923160000_AnElementTypeNamesItsEffect extends AbstractMigration
{
    public function getDescription(): string
    {
        return "crée element_types : l'effet appliqué par un type d'élément";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS element_types (
                name VARCHAR(100) NOT NULL,
                effect_name VARCHAR(100) DEFAULT NULL,
                PRIMARY KEY (name),
                KEY effect_name (effect_name),
                CONSTRAINT element_types_effect FOREIGN KEY (effect_name) REFERENCES effects (name)
                    ON UPDATE CASCADE ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS element_types');
    }
}
