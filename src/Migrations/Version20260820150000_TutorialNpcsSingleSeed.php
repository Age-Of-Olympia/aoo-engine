<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le roster du tutoriel ne porte qu'un exemplaire de chaque PNJ.
 *
 * La migration consolidée (Version20251127000000_CreateCompleteTutorialSystem)
 * re-sème `tutorial_npcs` sans garde : sur une base qui avait déjà exécuté
 * Version20260430220000_CreateTutorialNpcsTable, chaque PNJ existe en double —
 * deux Gaïa sur (1,0), et l'Âme d'entraînement à la fois liée à son étape de
 * combat et apparue dès l'ouverture de session.
 *
 * Deux passes, toutes deux rejouables :
 *  1. un doublon SANS étape s'efface devant son frère lié à une étape —
 *     c'est la copie périmée du seed, pas une configuration voulue ;
 *  2. deux lignes strictement identiques gardent la plus ancienne.
 */
final class Version20260820150000_TutorialNpcsSingleSeed extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'tutorial_npcs: retire les doublons du double-seed (une ligne par PNJ)';
    }

    public function up(Schema $schema): void
    {
        // Passe 1 : la copie sans étape cède devant la ligne liée à une étape.
        $this->addSql("
            DELETE stale FROM tutorial_npcs stale
            JOIN tutorial_npcs kept
              ON kept.version = stale.version
             AND kept.role = stale.role
             AND kept.spawn_mode = stale.spawn_mode
             AND kept.name = stale.name
             AND kept.id <> stale.id
            WHERE stale.spawn_at_step_id IS NULL
              AND kept.spawn_at_step_id IS NOT NULL
        ");

        // Passe 2 : à configuration identique, la plus ancienne reste.
        $this->addSql("
            DELETE dupe FROM tutorial_npcs dupe
            JOIN tutorial_npcs kept
              ON kept.version = dupe.version
             AND kept.role = dupe.role
             AND kept.spawn_mode = dupe.spawn_mode
             AND kept.name = dupe.name
             AND kept.x = dupe.x
             AND kept.y = dupe.y
             AND kept.id < dupe.id
            WHERE kept.spawn_at_step_id <=> dupe.spawn_at_step_id
        ");
    }

    public function down(Schema $schema): void
    {
        // Les lignes supprimées étaient des doublons : rien à restaurer.
    }
}
