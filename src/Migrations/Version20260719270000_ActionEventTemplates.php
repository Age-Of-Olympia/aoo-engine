<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Toute action laisse un événement (revue du 2026-07-19) : gabarits
 * action_type_logs pour les nouveaux types — equip, craft, et dig
 * (creuser passe du type search au sous-type dig : l'XP hérite de la
 * règle search, mais l'événement ne raconte plus « fouillé les
 * alentours » pour un tunnel). Idempotente.
 */
final class Version20260719270000_ActionEventTemplates extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Gabarits d'événements pour equip/craft/dig ; creuser passe au type dig";
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        $conn->executeStatement("UPDATE actions SET type = 'dig' WHERE name = 'creuser'");

        foreach ([
            'dig'   => 'Vous avez creusé une galerie.',
            'equip' => '{actor} a ajusté son équipement.',
            'craft' => '{actor} a fabriqué.',
        ] as $typeKey => $template) {
            $conn->executeStatement(
                'INSERT INTO action_type_logs (type_key, actor_template, target_template)
                 VALUES (?, ?, NULL)
                 ON DUPLICATE KEY UPDATE actor_template = VALUES(actor_template)',
                [$typeKey, $template]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->connection->executeStatement("UPDATE actions SET type = 'search' WHERE name = 'creuser'");
        $this->connection->executeStatement("DELETE FROM action_type_logs WHERE type_key IN ('dig', 'equip', 'craft')");
    }
}
