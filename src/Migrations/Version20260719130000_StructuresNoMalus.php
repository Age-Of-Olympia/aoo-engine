<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Une structure (bâtiment, objet unique) n'a pas de malus : il pénalise
 * les jets de défense et une structure n'esquive jamais. L'accumulation
 * est désormais bloquée à la source (Player::put_malus) ; ici on remet à
 * zéro ce que les structures déjà attaquées ont pu accumuler.
 */
final class Version20260719130000_StructuresNoMalus extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Zero out accumulated malus on structure entities (buildings, unique objects)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE players SET malus = 0 WHERE player_type IN ('building', 'unique') AND malus != 0");
    }

    public function down(Schema $schema): void
    {
        // Rien à restaurer : le malus accumulé par erreur n'a pas de valeur.
        $this->warnIf(true, 'StructuresNoMalus: no down migration (data fix).');
    }
}
