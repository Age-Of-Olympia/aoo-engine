<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le tapis de l'arène du tutoriel ne se pose qu'une fois.
 *
 * Même histoire que tutorial_npcs (Version20260820150000) : le seed
 * consolidé re-jouait « une tuile eryn_dolen par case intérieure » sans
 * garde, et chaque exécution empilait un tapis de plus — 49 cases, 147
 * tuiles. La copie d'instance recopie les lignes telles quelles : chaque
 * session héritait de la pile entière, et la minimap comme le damier
 * redessinaient trois fois la même herbe.
 *
 * Bornée au plan du tutoriel : ailleurs, deux tuiles sur une case
 * peuvent être une superposition voulue.
 */
final class Version20260820152000_TutorialTilesSingleSeed extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'map_tiles: une seule tuile par case du plan tutorial (doublons du re-seed)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            DELETE dupe FROM map_tiles dupe
            JOIN map_tiles kept
              ON kept.coords_id = dupe.coords_id
             AND kept.name = dupe.name
             AND kept.foreground = dupe.foreground
             AND kept.id < dupe.id
            JOIN coords c ON c.id = dupe.coords_id
            WHERE c.plan = 'tutorial'
        ");
    }

    public function down(Schema $schema): void
    {
        // Les lignes supprimées étaient des doublons : rien à restaurer.
    }
}
