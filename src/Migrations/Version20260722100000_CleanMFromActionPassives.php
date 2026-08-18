<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Update traits for action_passives with ID 3.
 */
final class Version20260722100000_CleanMFromActionPassives extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update traits column in action_passives for ID 3 to ["e","res"]';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE action_passives SET traits = ? WHERE id = ?',
            ['["e","res"]', 3]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'UPDATE action_passives SET traits = ? WHERE id = ?',
            ['["e","m"]', 3]
        );
    }
}