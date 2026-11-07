<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Removes the legacy item breakage: every `damageobject` instruction, and
 * the outcomes that held nothing else (the weapon_break_on_miss outcomes of
 * Version20261107120000 among them). Equipment now wears through
 * WearService only. Selected by type, never by id: prod has actions dev lacks.
 */
final class Version20261108100000_LegacyItemBreakRemoved extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suppression de la casse héritée (instruction damageobject)';
    }

    public function up(Schema $schema): void
    {
        // Outcomes whose only instructions are damageobject: emptied below, then deleted
        $emptied = array_map('intval', $this->connection->fetchFirstColumn(
            "SELECT o.id FROM action_outcomes o
              WHERE EXISTS (SELECT 1 FROM outcome_instructions oi WHERE oi.outcome_id = o.id AND oi.type = 'damageobject')
                AND NOT EXISTS (SELECT 1 FROM outcome_instructions oi WHERE oi.outcome_id = o.id AND oi.type <> 'damageobject')"
        ));

        $this->addSql("DELETE FROM outcome_instructions WHERE type = 'damageobject'");

        if ($emptied !== []) {
            $this->addSql('DELETE FROM action_outcomes WHERE id IN (' . implode(',', $emptied) . ')');
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The damageobject instruction class no longer exists.');
    }
}
