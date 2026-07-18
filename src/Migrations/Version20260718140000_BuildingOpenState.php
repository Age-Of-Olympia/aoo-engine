<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ouverture d'un bâtiment : buildings.is_open (fermeture VOLONTAIRE,
 * admin — un jour le propriétaire). L'état effectif « ouvert » combine
 * ce drapeau avec l'état du bâtiment : un bâtiment endommagé sous le
 * seuil, en construction ou en ruine est fermé d'office — un bâtiment
 * fermé tait son dialogue (BuildingService::closureReason, source
 * unique de la règle).
 */
final class Version20260718140000_BuildingOpenState extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'buildings.is_open : fermeture volontaire (le dialogue se tait)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE buildings ADD is_open TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE buildings DROP COLUMN is_open');
    }
}
