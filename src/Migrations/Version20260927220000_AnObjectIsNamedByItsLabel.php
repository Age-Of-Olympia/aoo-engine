<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Object entities were named after their catalogue code ("Coffre_bois").
 * Those still carrying that raw name take the item's label, or the code
 * with spaces when the label is empty. Names chosen by hand are left alone.
 */
final class Version20260927220000_AnObjectIsNamedByItsLabel extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'players.name des objets posés : libellé du catalogue au lieu du code (« Coffre_bois »)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE players p
               JOIN item_instances ii ON ii.entity_id = p.id
               JOIN items i ON i.id = ii.item_id
                SET p.name = IF(i.label <> '', i.label,
                                CONCAT(UPPER(LEFT(REPLACE(i.name, '_', ' '), 1)), SUBSTRING(REPLACE(i.name, '_', ' '), 2)))
              WHERE p.player_type = 'item'
                AND CONVERT(p.name USING utf8mb4) = CONVERT(CONCAT(UPPER(LEFT(i.name, 1)), SUBSTRING(i.name, 2)) USING utf8mb4)"
        );
    }

    public function down(Schema $schema): void
    {
        // Irreversible on purpose: the raw code was never a wanted name.
    }
}
