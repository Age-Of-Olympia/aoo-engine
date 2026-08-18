<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * items.magique — an equipped magical item switches the damage trait of
 * melee/distance attacks from F to M. Flag column like cursed/vorpal:
 * the DB is the source, editable from the item's admin sheet.
 */
final class Version20260818120000_ItemsMagique extends AbstractMigration
{
    public function getDescription(): string
    {
        return "items.magique — l'objet équipé bascule les dégâts d'attaque sur la M";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items ADD COLUMN IF NOT EXISTS magique TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items DROP COLUMN IF EXISTS magique');
    }
}
