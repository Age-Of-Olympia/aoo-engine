<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le tutoriel parle la langue du HUD : bandeau de sélection recomposé.
 *
 * La carte héritée ne réapparaît plus pendant le tutoriel (js/hud.js la
 * recompose désormais comme partout) ; le contenu suit :
 *
 *  - « Fermer » vit dans le volet d'actions après recomposition : les
 *    étapes de fermeture le visent par sa classe (button.close-card),
 *    valable dans les deux habillages, plus par sa position d'origine ;
 *  - la pastille « Récoltable » s'appelle .building-status depuis le
 *    chantier entités — .resource-status ne matchait déjà plus rien ;
 *  - la récolte gagne son étape manquante : depuis que l'arbre est une
 *    entité, sa fiche n'offre que le corps à corps — Fouiller vit dans
 *    la fiche du PERSONNAGE. Après la présentation de l'arbre, sa fiche
 *    se ferme (auto_close_card) et une étape fait cliquer son propre
 *    personnage avant de fouiller, comme l'étape 12 l'a déjà enseigné.
 */
final class Version20260820155000_TheTutorialSpeaksHud extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'tutoriel: sélecteurs du bandeau HUD + étape « cliquez votre personnage » avant Fouiller';
    }

    public function up(Schema $schema): void
    {
        // « Fermer » par sa classe, où qu'il soit.
        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.target_selector = 'button.close-card'
             WHERE s.version = '1.0.0'
               AND u.target_selector = '#ui-card .close-card'
        ");

        // La pastille de récolte porte son nom d'aujourd'hui.
        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.target_selector = '.building-status'
             WHERE s.version = '1.0.0' AND s.step_id = 'tree_info'
               AND u.target_selector = '.resource-status'
        ");

        // La fiche de l'arbre se ferme en quittant sa présentation, pour
        // que l'étape suivante attende une fiche FRAÎCHE (le validateur
        // « panneau ouvert » se déclencherait sinon sur celle de l'arbre).
        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.auto_close_card = 1
             WHERE s.version = '1.0.0' AND s.step_id = 'tree_info'
               AND u.auto_close_card = 0
        ");

        // L'étape manquante : revenir à sa propre fiche avant de fouiller.
        $this->addSql("
            INSERT INTO tutorial_steps
                (version, step_id, next_step, step_number, step_type, title, text, xp_reward, is_active)
            SELECT '1.0.0', 'click_yourself_for_gather', 'use_fouiller', 17.5, 'ui_interaction',
                   'À vous de jouer',
                   'Cliquez sur <strong>votre personnage</strong> : c’est lui qui récolte, depuis sa case voisine de l’arbre.',
                   5, 1
             WHERE NOT EXISTS (
                SELECT 1 FROM tutorial_steps
                 WHERE version = '1.0.0' AND step_id = 'click_yourself_for_gather'
             )
        ");

        $this->addSql("
            UPDATE tutorial_steps
               SET next_step = 'click_yourself_for_gather'
             WHERE version = '1.0.0' AND step_id = 'tree_info'
               AND next_step = 'use_fouiller'
        ");

        $this->addSql("
            INSERT INTO tutorial_step_ui (step_id, target_selector, tooltip_position, interaction_mode, show_delay, auto_close_card)
            SELECT s.id, '#current-player-avatar', 'bottom', 'semi-blocking', 300, 0
              FROM tutorial_steps s
             WHERE s.version = '1.0.0' AND s.step_id = 'click_yourself_for_gather'
               AND NOT EXISTS (SELECT 1 FROM tutorial_step_ui u WHERE u.step_id = s.id)
        ");

        $this->addSql("
            INSERT INTO tutorial_step_validation (step_id, requires_validation, validation_type, panel_id, validation_hint)
            SELECT s.id, 1, 'ui_panel_opened', 'actions', 'Cliquez sur votre personnage'
              FROM tutorial_steps s
             WHERE s.version = '1.0.0' AND s.step_id = 'click_yourself_for_gather'
               AND NOT EXISTS (SELECT 1 FROM tutorial_step_validation v WHERE v.step_id = s.id)
        ");

        // Mêmes clics autorisés que l'étape 12 (cliquer son personnage).
        $this->addSql("
            INSERT INTO tutorial_step_interactions (step_id, selector, description)
            SELECT s.id, i.selector, i.description
              FROM tutorial_steps s
              JOIN tutorial_steps s12 ON s12.version = '1.0.0' AND s12.step_id = 'click_yourself'
              JOIN tutorial_step_interactions i ON i.step_id = s12.id
             WHERE s.version = '1.0.0' AND s.step_id = 'click_yourself_for_gather'
               AND NOT EXISTS (SELECT 1 FROM tutorial_step_interactions x WHERE x.step_id = s.id)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            UPDATE tutorial_steps
               SET next_step = 'use_fouiller'
             WHERE version = '1.0.0' AND step_id = 'tree_info'
               AND next_step = 'click_yourself_for_gather'
        ");

        $this->addSql("
            DELETE i FROM tutorial_step_interactions i
              JOIN tutorial_steps s ON s.id = i.step_id
             WHERE s.version = '1.0.0' AND s.step_id = 'click_yourself_for_gather'
        ");
        $this->addSql("
            DELETE v FROM tutorial_step_validation v
              JOIN tutorial_steps s ON s.id = v.step_id
             WHERE s.version = '1.0.0' AND s.step_id = 'click_yourself_for_gather'
        ");
        $this->addSql("
            DELETE u FROM tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
             WHERE s.version = '1.0.0' AND s.step_id = 'click_yourself_for_gather'
        ");
        $this->addSql("
            DELETE FROM tutorial_steps
             WHERE version = '1.0.0' AND step_id = 'click_yourself_for_gather'
        ");

        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.auto_close_card = 0
             WHERE s.version = '1.0.0' AND s.step_id = 'tree_info'
               AND u.auto_close_card = 1
        ");

        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.target_selector = '.resource-status'
             WHERE s.version = '1.0.0' AND s.step_id = 'tree_info'
               AND u.target_selector = '.building-status'
        ");

        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.target_selector = '#ui-card .close-card'
             WHERE s.version = '1.0.0'
               AND u.target_selector = 'button.close-card'
        ");
    }
}
