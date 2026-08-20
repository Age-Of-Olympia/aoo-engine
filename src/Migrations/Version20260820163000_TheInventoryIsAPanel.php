<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * L'inventaire du tutoriel s'ouvre en panneau, et se ferme comme un panneau.
 *
 * Le routeur du HUD travaille désormais pendant le tutoriel : « Inventaire »
 * ouvre le panneau coulissant, plus la page héritée. L'étape de fermeture
 * visait le bouton « Retour » (#back) de cette page — dans le panneau, on
 * ferme par sa croix.
 */
final class Version20260820163000_TheInventoryIsAPanel extends AbstractMigration
{
    public function getDescription(): string
    {
        return "tutoriel: l'inventaire se ferme par la croix du panneau, plus par #back";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.target_selector = '.hud-panel-close', u.tooltip_position = 'left'
             WHERE s.version = '1.0.0' AND s.step_id = 'close_inventory'
               AND u.target_selector = '#back'
        ");

        $this->addSql("
            UPDATE tutorial_step_validation v
              JOIN tutorial_steps s ON s.id = v.step_id
               SET v.element_clicked = '.hud-panel-close'
             WHERE s.version = '1.0.0' AND s.step_id = 'close_inventory'
               AND v.element_clicked = '#back'
        ");

        $this->addSql("
            UPDATE tutorial_step_interactions i
              JOIN tutorial_steps s ON s.id = i.step_id
               SET i.selector = '.hud-panel-close'
             WHERE s.version = '1.0.0' AND s.step_id = 'close_inventory'
               AND i.selector = '#back'
        ");

        $this->addSql("
            UPDATE tutorial_steps
               SET text = 'Fermez l’inventaire pour revenir au jeu : cliquez sur la <strong>croix</strong> du panneau.'
             WHERE version = '1.0.0' AND step_id = 'close_inventory'
               AND text LIKE '%<strong>Retour</strong>%'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            UPDATE tutorial_steps
               SET text = 'Fermez l''inventaire pour revenir au jeu. Cliquez sur <strong>Retour</strong>.'
             WHERE version = '1.0.0' AND step_id = 'close_inventory'
               AND text LIKE '%croix%'
        ");

        $this->addSql("
            UPDATE tutorial_step_interactions i
              JOIN tutorial_steps s ON s.id = i.step_id
               SET i.selector = '#back'
             WHERE s.version = '1.0.0' AND s.step_id = 'close_inventory'
               AND i.selector = '.hud-panel-close'
        ");

        $this->addSql("
            UPDATE tutorial_step_validation v
              JOIN tutorial_steps s ON s.id = v.step_id
               SET v.element_clicked = '#back'
             WHERE s.version = '1.0.0' AND s.step_id = 'close_inventory'
               AND v.element_clicked = '.hud-panel-close'
        ");

        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.target_selector = '#back', u.tooltip_position = 'bottom'
             WHERE s.version = '1.0.0' AND s.step_id = 'close_inventory'
               AND u.target_selector = '.hud-panel-close'
        ");
    }
}
