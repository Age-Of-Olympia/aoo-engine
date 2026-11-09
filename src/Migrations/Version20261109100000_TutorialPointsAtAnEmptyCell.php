<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Étape 4 « Changer de sélection » : la cible était tout le bandeau
 * (#ajax-data), rien ne montrait où cliquer sur le damier. Elle vise
 * désormais une case vide voisine, (-1,-1), libre pour toutes les races
 * (le parcours Cypress y clique). Viser la case la met en surbrillance
 * et la bulle ne la couvre plus (TutorialTooltip.keepClearZone).
 */
final class Version20261109100000_TutorialPointsAtAnEmptyCell extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'tutoriel : l’étape 4 met en surbrillance une case vide à cliquer';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.target_selector = '.case[data-coords=\"-1,-1\"]'
             WHERE s.version = '1.0.0' AND s.step_id = 'close_card'
        ");

        $this->addSql("
            UPDATE tutorial_steps
               SET text = REPLACE(text, 'Cliquez sur une case vide</strong>', 'Cliquez sur la case vide en surbrillance</strong>')
             WHERE version = '1.0.0' AND step_id = 'close_card'
        ");

        $this->addSql("
            UPDATE tutorial_step_validation v
              JOIN tutorial_steps s ON s.id = v.step_id
               SET v.validation_hint = 'Cliquez sur la case vide en surbrillance'
             WHERE s.version = '1.0.0' AND s.step_id = 'close_card'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.target_selector = '#ajax-data'
             WHERE s.version = '1.0.0' AND s.step_id = 'close_card'
        ");

        $this->addSql("
            UPDATE tutorial_steps
               SET text = REPLACE(text, 'Cliquez sur la case vide en surbrillance</strong>', 'Cliquez sur une case vide</strong>')
             WHERE version = '1.0.0' AND step_id = 'close_card'
        ");

        $this->addSql("
            UPDATE tutorial_step_validation v
              JOIN tutorial_steps s ON s.id = v.step_id
               SET v.validation_hint = 'Cliquez sur une case vide du damier'
             WHERE s.version = '1.0.0' AND s.step_id = 'close_card'
        ");
    }
}
