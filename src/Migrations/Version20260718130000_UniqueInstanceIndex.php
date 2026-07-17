<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Verrou d'invariant (revue 2026-07-18) : une instance d'objet a UNE
 * localisation — elle ne peut pas être enveloppée par deux entités
 * UniqueObject à la fois. La garde applicative de placeInstance()
 * (INNER JOIN sur le lien de possession) ferme le chemin normal ; cet
 * index UNIQUE ferme tous les autres.
 */
final class Version20260718130000_UniqueInstanceIndex extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'unique_objects.item_instance_id : index UNIQUE (une instance = une localisation)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unique_objects ADD UNIQUE INDEX uniq_item_instance (item_instance_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unique_objects DROP INDEX uniq_item_instance');
    }
}
