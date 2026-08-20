<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le tutoriel montre les caracs là où le HUD les affiche : les pilules.
 *
 * L'étape 8 faisait ouvrir l'ancien volet Caractéristiques (#load-caracs),
 * et quatre étapes surlignaient ses compteurs #mvt-counter / #action-counter
 * — un panneau que le HUD a remplacé par les pilules du bandeau haut, et
 * qu'il ne ressuscitait plus que par compatibilité avec ces étapes mêmes.
 * Le serpent se mord la queue : on adapte le contenu, et la compatibilité
 * tombe (js/hud.js rend le bouton inerte pendant le tutoriel).
 *
 * L'étape 8 devient une étape d'information qui surligne la rangée de
 * pilules ; les surlignages de compteurs visent les pilules Mvt et A.
 * Chaque UPDATE est borné sur l'ancienne valeur : rejouable, silencieux
 * sur un contenu déjà adapté.
 */
final class Version20260820154000_CaracsLiveInTheChips extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'tutoriel: les étapes de caracs ciblent les pilules du HUD, plus l\'ancien volet';
    }

    public function up(Schema $schema): void
    {
        // Étape 8 : plus rien à cliquer — on regarde le bandeau.
        $this->addSql("
            UPDATE tutorial_steps
               SET step_type = 'info',
                   text = 'Vos caractéristiques sont affichées <strong>en haut de l’écran</strong> : PV, Mouvements (Mvt), Actions (A)… Gardez un œil sur la pilule <strong>Mvt</strong> : chaque déplacement la fait diminuer.'
             WHERE version = '1.0.0' AND step_id = 'show_characteristics'
               AND step_type = 'ui_interaction'
        ");

        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.target_selector = '#hud-topbar .hud-pills',
                   u.interaction_mode = 'blocking'
             WHERE s.version = '1.0.0' AND s.step_id = 'show_characteristics'
               AND u.target_selector = '#show-caracs'
        ");

        $this->addSql("
            UPDATE tutorial_step_validation v
              JOIN tutorial_steps s ON s.id = v.step_id
               SET v.requires_validation = 0,
                   v.validation_type = NULL,
                   v.panel_id = NULL
             WHERE s.version = '1.0.0' AND s.step_id = 'show_characteristics'
               AND v.validation_type = 'ui_panel_opened'
        ");

        $this->addSql("
            DELETE i FROM tutorial_step_interactions i
              JOIN tutorial_steps s ON s.id = i.step_id
             WHERE s.version = '1.0.0' AND s.step_id = 'show_characteristics'
               AND i.selector = '#show-caracs'
        ");

        // Les compteurs de l'ancien volet deviennent les pilules du bandeau.
        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.target_selector = '#hud-pill-mvt'
             WHERE s.version = '1.0.0' AND u.target_selector = '#mvt-counter'
        ");

        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.target_selector = '#hud-pill-a'
             WHERE s.version = '1.0.0' AND u.target_selector = '#action-counter'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.target_selector = '#action-counter'
             WHERE s.version = '1.0.0' AND u.target_selector = '#hud-pill-a'
        ");

        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.target_selector = '#mvt-counter'
             WHERE s.version = '1.0.0' AND u.target_selector = '#hud-pill-mvt'
        ");

        $this->addSql("
            INSERT INTO tutorial_step_interactions (step_id, selector)
            SELECT s.id, '#show-caracs'
              FROM tutorial_steps s
             WHERE s.version = '1.0.0' AND s.step_id = 'show_characteristics'
        ");

        $this->addSql("
            UPDATE tutorial_step_validation v
              JOIN tutorial_steps s ON s.id = v.step_id
               SET v.requires_validation = 1,
                   v.validation_type = 'ui_panel_opened',
                   v.panel_id = 'characteristics'
             WHERE s.version = '1.0.0' AND s.step_id = 'show_characteristics'
        ");

        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.target_selector = '#show-caracs',
                   u.interaction_mode = 'semi-blocking'
             WHERE s.version = '1.0.0' AND s.step_id = 'show_characteristics'
               AND u.target_selector = '#hud-topbar .hud-pills'
        ");

        $this->addSql("
            UPDATE tutorial_steps
               SET step_type = 'ui_interaction',
                   text = 'Cliquez sur <strong>\"Caractéristiques\"</strong> pour voir vos stats, dont vos mouvements restants.'
             WHERE version = '1.0.0' AND step_id = 'show_characteristics'
               AND step_type = 'info'
        ");
    }
}
