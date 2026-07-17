<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Retour de revue (2026-07-17) : une instance AU SOL se comporte comme
 * toute bourse — sprite loot, ramassée en MARCHANT dessus, pas
 * d'action dédiée. L'identité est préservée par la donnée, pas par
 * l'UX.
 *
 * - map_items_instances (instance_id PK, coords_id) : la localisation
 *   « au sol » d'une instance — l'invariant une-instance-un-lieu tient
 *   en trois tables (possession / sol / enveloppe unique).
 * - L'action 'ramasser' est retirée du catalogue (marcher remplace) ;
 *   l'enveloppe-entité (unique_objects.item_instance_id) reste pour
 *   les futurs artefacts/coffres attaquables posés par les animateurs.
 */
final class Version20260717170000_GroundInstances extends AbstractMigration
{
    public function getDescription(): string
    {
        return "map_items_instances (bourse d'instances) + retrait de l'action ramasser";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS map_items_instances (
                instance_id INT NOT NULL PRIMARY KEY,
                coords_id INT NOT NULL,
                INDEX idx_mii_coords (coords_id),
                CONSTRAINT fk_mii_instance FOREIGN KEY (instance_id) REFERENCES item_instances (id),
                CONSTRAINT fk_mii_coords FOREIGN KEY (coords_id) REFERENCES coords (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        $this->addSql(
            "DELETE oi FROM outcome_instructions oi
             JOIN action_outcomes o ON o.id = oi.outcome_id
             JOIN actions a ON a.id = o.action_id WHERE a.name = 'ramasser'"
        );
        $this->addSql("DELETE o FROM action_outcomes o JOIN actions a ON a.id = o.action_id WHERE a.name = 'ramasser'");
        $this->addSql("DELETE FROM action_conditions WHERE action_id IN (SELECT id FROM actions WHERE name = 'ramasser')");
        $this->addSql("DELETE FROM players_actions WHERE name = 'ramasser'");
        $this->addSql("DELETE FROM actions WHERE name = 'ramasser'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS map_items_instances');
        // L'action ramasser n'est pas recréée : voir Version20260717160000.
    }
}
