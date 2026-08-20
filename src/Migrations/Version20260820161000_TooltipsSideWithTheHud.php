<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les infobulles se placent du côté libre du HUD.
 *
 * Les positions dataient de la carte héritée, au centre de l'écran, où
 * « à droite » donnait sur du vide. Dans le HUD, les boutons d'action
 * (Fermer, Fouiller, Corps à corps…) vivent dans le volet Actions, collé
 * au bord DROIT : l'infobulle « à droite » se rabattait sur le volet et
 * recouvrait ce qu'elle désignait. Même geste pour les pilules du bandeau
 * HAUT : « à droite » recouvrait les pilules voisines — sous la cible,
 * comme l'étape 8 le fait déjà.
 *
 * Bornés sur l'ancienne valeur : rejouable, muet sur un contenu retouché.
 */
final class Version20260820161000_TooltipsSideWithTheHud extends AbstractMigration
{
    public function getDescription(): string
    {
        return "tutoriel: infobulles à gauche du volet Actions, sous les pilules du bandeau";
    }

    public function up(Schema $schema): void
    {
        // Cibles du volet Actions (bord droit) : l'infobulle vient de gauche.
        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.tooltip_position = 'left'
             WHERE s.version = '1.0.0'
               AND u.tooltip_position = 'right'
               AND (u.target_selector LIKE '.action[data-action%'
                    OR u.target_selector = 'button.close-card'
                    OR u.target_selector = '.card-actions')
        ");

        // Cibles du bandeau haut (pilules) : l'infobulle vient d'en bas.
        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.tooltip_position = 'bottom'
             WHERE s.version = '1.0.0'
               AND u.tooltip_position = 'right'
               AND u.target_selector LIKE '#hud-pill-%'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.tooltip_position = 'right'
             WHERE s.version = '1.0.0'
               AND u.tooltip_position = 'left'
               AND (u.target_selector LIKE '.action[data-action%'
                    OR u.target_selector = 'button.close-card'
                    OR u.target_selector = '.card-actions')
        ");

        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.tooltip_position = 'right'
             WHERE s.version = '1.0.0'
               AND u.tooltip_position = 'bottom'
               AND u.target_selector LIKE '#hud-pill-%'
        ");
    }
}
