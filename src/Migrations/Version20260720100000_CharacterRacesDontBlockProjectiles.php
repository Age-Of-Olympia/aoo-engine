<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Par défaut, un personnage ne fait pas écran aux tirs (revue du
 * 2026-07-20) : la ligne de tir consulte désormais TOUTES les entités
 * dont la race coche blocks_projectiles — structures comme personnages.
 * Les 16 races de personnages portaient le défaut de colonne à 1 (héritée
 * de la création des structures), ce qui aurait bloqué toutes les
 * flèches : on les passe à 0. L'option reste éditable race par race
 * (une race massive peut faire écran). Idempotente.
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
