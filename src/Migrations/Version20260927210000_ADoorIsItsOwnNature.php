<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A door is its own structure nature, 'porte', next to édifice and
 * obstacle. The types flagged opens_the_way move to it; the column
 * stays until a later migration drops it, after the deploy.
 */
final class Version20260927210000_ADoorIsItsOwnNature extends AbstractMigration
{
    public function getDescription(): string
    {
        return "races.structure_nature = 'porte' pour les types de porte (opens_the_way)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE races SET structure_nature = 'porte' WHERE kind = 'structure' AND opens_the_way = 1"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE races SET structure_nature = 'obstacle', opens_the_way = 1 WHERE kind = 'structure' AND structure_nature = 'porte'"
        );
    }
}
