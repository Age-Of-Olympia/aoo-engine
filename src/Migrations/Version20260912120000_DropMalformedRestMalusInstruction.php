<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The rest action carried two instructions for the malus: the `rest` one,
 * which computes the recovery from the remaining movement, and a `player`
 * one whose value was the letter "r" — a trait name where an integer is
 * expected. The second reached MariaDB as a column name and broke every
 * rest. It never had anything to add: the `rest` instruction already
 * removes the malus.
 */
final class Version20260912120000_DropMalformedRestMalusInstruction extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'retire l\'instruction player malus/"r" doublonnant le repos';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "DELETE FROM outcome_instructions
             WHERE type = 'player'
               AND JSON_UNQUOTE(JSON_EXTRACT(parameters, '$.carac')) = 'malus'
               AND JSON_UNQUOTE(JSON_EXTRACT(parameters, '$.value')) = 'r'"
        );
    }

    public function down(Schema $schema): void
    {
        // Nothing to restore: the instruction was broken and redundant.
    }
}
