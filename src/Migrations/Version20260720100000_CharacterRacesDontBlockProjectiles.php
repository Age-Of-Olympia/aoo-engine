<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les races de personnages n'arrêtent pas les projectiles (revue du
 * 2026-07-20) : blocks_projectiles est une mécanique de structure (la
 * ligne de tir ne consulte que les entités posées) mais les races de
 * personnages portaient encore le défaut à 1 — config trompeuse. Le
 * panneau d'admin force désormais 0 à l'enregistrement d'une race ;
 * cette migration aligne l'existant. Idempotente.
 */
final class Version20260720100000_CharacterRacesDontBlockProjectiles extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Les races de personnages ne bloquent pas les tirs (blocks_projectiles = 0)';
    }

    public function up(Schema $schema): void
    {
        $this->connection->executeStatement(
            "UPDATE races SET blocks_projectiles = 0 WHERE kind = 'character'"
        );
    }

    public function down(Schema $schema): void
    {
        // Retour au défaut historique de la colonne.
        $this->connection->executeStatement(
            "UPDATE races SET blocks_projectiles = 1 WHERE kind = 'character'"
        );
    }
}
