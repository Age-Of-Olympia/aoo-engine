<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ac (Action de consommable), the twin of Ae: a race carac reset each turn,
 * spent by the generic `consommer` action instead of 1 A. Races receive as
 * many Ac as they have Ae (playable races 1, soul/structures 0). Idempotent.
 */
final class Version20261103100000_ConsumableActionCarac extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Carac Ac (races.ac, items.ac) ; consommer coûte 1 Ac au lieu de 1 A';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        if (!$this->hasColumn('races', 'ac')) {
            $conn->executeStatement('ALTER TABLE races ADD COLUMN ac INT NOT NULL DEFAULT 0');
            $conn->executeStatement('UPDATE races SET ac = ae');
        }
        $conn->executeStatement('ALTER TABLE items ADD COLUMN IF NOT EXISTS ac INT NOT NULL DEFAULT 0');

        $this->setConsumeCost('a', 'ac');
    }

    public function down(Schema $schema): void
    {
        $this->setConsumeCost('ac', 'a');
        $this->connection->executeStatement('ALTER TABLE items DROP COLUMN IF EXISTS ac');
        $this->connection->executeStatement('ALTER TABLE races DROP COLUMN IF EXISTS ac');
    }

    /** Swaps the pool paid by the `consommer` action's RequiresTraitValue cost. */
    private function setConsumeCost(string $from, string $to): void
    {
        $this->connection->executeStatement(
            "UPDATE action_conditions c JOIN actions a ON a.id = c.action_id
             SET c.parameters = ?
             WHERE a.name = 'consommer' AND c.conditionType = 'RequiresTraitValue'
               AND JSON_EXTRACT(c.parameters, ?) IS NOT NULL",
            [json_encode([$to => 1]), '$.' . $from]
        );
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        ) > 0;
    }
}
