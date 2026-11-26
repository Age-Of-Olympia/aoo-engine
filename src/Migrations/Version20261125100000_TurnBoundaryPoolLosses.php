<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Configure effects that reduce a pool when the previous turn ends. */
final class Version20261125100000_TurnBoundaryPoolLosses extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add end-of-turn PV/PM losses to Brûlure and Mouillé';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE effects ADD COLUMN IF NOT EXISTS turn_loss_carac VARCHAR(4) NOT NULL DEFAULT ''");
        $this->addSql(
            "UPDATE effects SET turn_loss_carac = 'pv',
                description = 'Au début du nouveau tour, retire la valeur portée en PV. Si les PV tombent à zéro, le personnage meurt à cause des éléments.'
             WHERE name = 'brulure'"
        );
        $this->addSql(
            "INSERT INTO effects (name, label, description, icon, turn_loss_carac)
             VALUES ('mouille', 'Mouillé', 'Au début du nouveau tour, retire la valeur portée en PM.', 'ra-droplet', 'pm')
             ON DUPLICATE KEY UPDATE turn_loss_carac = 'pm'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM players_effects WHERE name = 'mouille'");
        $this->addSql("DELETE FROM effects WHERE name = 'mouille'");
        $this->addSql("UPDATE effects SET turn_loss_carac = '' WHERE name = 'brulure'");
        $this->addSql('ALTER TABLE effects DROP COLUMN IF EXISTS turn_loss_carac');
    }
}
