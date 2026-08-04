<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A chest knows its CAPACITY: how many content LINES it holds — a
 * stack of one item is one line, an exemplar is one line. NULL means
 * unlimited, which every non-container item keeps; the admin sets the
 * number on the item type. The material decides the defaults.
 */
final class Version20260805160000_AChestKnowsItsCapacity extends AbstractMigration
{
    private const DEFAULTS = [
        'coffre_humain' => 6,
        'coffre_bois' => 8,
        'coffre_bois_petrifie' => 12,
        'coffre_metal' => 16,
    ];

    public function getDescription(): string
    {
        return 'items.capacity — la contenance en lignes d\'un coffre, réglable en admin';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items ADD COLUMN IF NOT EXISTS capacity INT NULL DEFAULT NULL');

        foreach (self::DEFAULTS as $name => $capacity) {
            // Only where unset: an admin's own tuning is never overwritten.
            $this->addSql(
                "UPDATE items SET capacity = {$capacity} WHERE name = '{$name}' AND capacity IS NULL"
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items DROP COLUMN IF EXISTS capacity');
    }
}
