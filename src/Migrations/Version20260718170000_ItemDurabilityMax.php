<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La « vie » propre d'un objet devient une carac de CATALOGUE
 * (décision 2026-07-18) : items.durability_max — jusqu'ici le 100 des
 * instances était un défaut de schéma figé, invisible et non
 * configurable. Les instances continuent de porter leur propre
 * durability/durability_max (instantané à la naissance) ; le catalogue
 * fixe la valeur de départ, réglable dans admin → Objets (section
 * Usure) et transportée par les bundles.
 */
final class Version20260718170000_ItemDurabilityMax extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'items.durability_max : la vie de départ des instances, réglable par objet';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items ADD durability_max INT NOT NULL DEFAULT 100');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items DROP COLUMN durability_max');
    }
}
