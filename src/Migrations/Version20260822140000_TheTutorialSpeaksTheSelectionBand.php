<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le tutoriel enseigne le bandeau de sélection, plus la fiche à fermer.
 *
 * Le HUD masque le bouton « Fermer » du volet d’actions (css/hud.css :
 * « changer de sélection suffit ») : deux étapes demandaient pourtant de
 * cliquer une croix invisible. Le harnais Cypress ne le voyait pas — il
 * clique le nœud caché par trigger('click') — un joueur, si.
 *
 *  - « Fermer la fiche » devient « Changer de sélection » : la fiche vit
 *    dans le bandeau du bas et suit les clics ; cliquer une case vide la
 *    remplace. La validation ne bouge pas (case vide ⇒ plus de #ui-card).
 *  - « Direction l’arbre » ne demande plus de fermer : le bandeau ne
 *    couvre pas le damier, rien n’empêche de marcher. L’étape redevient
 *    une annonce, et son auto_close_card nettoie la fiche au passage.
 *
 * Le reste suit la même règle — dire où sont les choses dans le HUD :
 * volet d’actions en bas à droite, boutons-icônes qu’un premier clic
 * arme et qu’un second confirme, Inventaire en icône dans le rail
 * gauche, médaillon Affichage pour les cases infranchissables. Et le
 * consigne morte de l’étape des caractéristiques disparaît : elle ne
 * valide plus rien depuis qu’elle montre les pilules du bandeau.
 */
final class Version20260822140000_TheTutorialSpeaksTheSelectionBand extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'tutoriel: le bandeau de sélection remplace la fiche à fermer, et les consignes disent où regarder dans le HUD';
    }

    public function up(Schema $schema): void
    {
        /* --- Étape 4 : fermer une fiche devient changer de sélection --- */

        $this->addSql("
            UPDATE tutorial_steps
               SET title = 'Changer de sélection',
                   text  = 'La fiche s’affiche dans le <strong>bandeau du bas</strong>, sous le damier : il n’y a rien à fermer, elle suit vos clics. <strong>Cliquez sur une case vide</strong> et la sélection change — Gaïa laisse la place.'
             WHERE version = '1.0.0' AND step_id = 'close_card'
        ");

        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.target_selector  = '#ajax-data',
                   u.tooltip_position = 'top'
             WHERE s.version = '1.0.0' AND s.step_id = 'close_card'
        ");

        $this->addSql("
            UPDATE tutorial_step_validation v
              JOIN tutorial_steps s ON s.id = v.step_id
               SET v.validation_hint = 'Cliquez sur une case vide du damier'
             WHERE s.version = '1.0.0' AND s.step_id = 'close_card'
        ");

        /* La croix n’existe plus dans le HUD : la laisser dans la liste
         * blanche serait garder une notion morte comme règle. */
        $this->addSql("
            DELETE i FROM tutorial_step_interactions i
              JOIN tutorial_steps s ON s.id = i.step_id
             WHERE s.version = '1.0.0' AND s.step_id = 'close_card'
               AND i.selector = '.close-card'
        ");

        /* --- Étape 14 : annonce de l’arbre, sans fermeture exigée --- */

        $this->addSql("
            UPDATE tutorial_steps
               SET step_type = 'info',
                   text = 'Un arbre pousse à deux pas. Approchons-nous : on ne récolte que depuis une <strong>case voisine</strong>.'
             WHERE version = '1.0.0' AND step_id = 'close_card_for_tree'
        ");

        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.target_selector  = '.case[data-coords=\"0,1\"]',
                   u.tooltip_position = 'bottom',
                   u.interaction_mode = 'blocking',
                   u.show_delay       = 300
             WHERE s.version = '1.0.0' AND s.step_id = 'close_card_for_tree'
        ");

        $this->addSql("
            UPDATE tutorial_step_validation v
              JOIN tutorial_steps s ON s.id = v.step_id
               SET v.requires_validation = 0,
                   v.validation_type     = NULL,
                   v.element_selector    = NULL,
                   v.validation_hint     = NULL
             WHERE s.version = '1.0.0' AND s.step_id = 'close_card_for_tree'
        ");

        /* Étape bloquante : la liste blanche ne sert plus. */
        $this->addSql("
            DELETE i FROM tutorial_step_interactions i
              JOIN tutorial_steps s ON s.id = i.step_id
             WHERE s.version = '1.0.0' AND s.step_id = 'close_card_for_tree'
        ");

        /* --- Dire où sont les choses dans le HUD --- */

        // Étape 5 : le médaillon Affichage, à côté des options du compte.
        $this->addSql("
            UPDATE tutorial_steps
               SET text = 'Regardez les <strong>cases</strong> autour de vous : ce sont les cases où vous pouvez vous déplacer. Les cases marquées d’un <strong>⛔</strong> sont infranchissables (murs, joueurs, cases interdites). En jeu, ce repère s’active dans vos options, ou depuis le médaillon <strong>Affichage</strong> au coin bas-droit du damier.'
             WHERE version = '1.0.0' AND step_id = 'movement_intro'
        ");

        // Étape 8 : plus de validation depuis qu’elle montre les pilules.
        $this->addSql("
            UPDATE tutorial_step_validation v
              JOIN tutorial_steps s ON s.id = v.step_id
               SET v.validation_hint = NULL
             WHERE s.version = '1.0.0' AND s.step_id = 'show_characteristics'
               AND v.requires_validation = 0
        ");

        // Étape 13 : le volet d’actions a quitté la fiche pour le coin bas-droit.
        $this->addSql("
            UPDATE tutorial_steps
               SET text = 'Voici vos <strong>actions disponibles</strong>, rassemblées <strong>en bas à droite</strong>. Chaque bouton est une icône — son nom apparaît au survol — et chaque action coûte des PA. Nous allons en tester une : la récolte de ressources.'
             WHERE version = '1.0.0' AND step_id = 'actions_panel_info'
        ");

        // Étape 18 : armer puis confirmer, la règle de tous les boutons d’action.
        $this->addSql("
            UPDATE tutorial_steps
               SET text = 'Dans le panneau d’actions, cliquez sur <strong>Fouiller</strong> pour récolter du bois. Un premier clic <strong>arme</strong> le bouton, un second <strong>confirme</strong> : toutes les actions se déclenchent ainsi.'
             WHERE version = '1.0.0' AND step_id = 'use_fouiller'
        ");

        $this->addSql("
            UPDATE tutorial_step_validation v
              JOIN tutorial_steps s ON s.id = v.step_id
               SET v.validation_hint = 'Armez puis confirmez l’action Fouiller'
             WHERE s.version = '1.0.0' AND s.step_id = 'use_fouiller'
        ");

        // Étape 21 : l’inventaire est une icône du rail gauche.
        $this->addSql("
            UPDATE tutorial_steps
               SET text = 'Ouvrez votre <strong>Inventaire</strong> pour voir le bois récolté : c’est l’icône de sac, dans le <strong>rail à gauche</strong>.'
             WHERE version = '1.0.0' AND step_id = 'open_inventory'
        ");

        $this->addSql("
            UPDATE tutorial_step_validation v
              JOIN tutorial_steps s ON s.id = v.step_id
               SET v.validation_hint = 'Cliquez sur l’icône Inventaire, dans le rail à gauche'
             WHERE s.version = '1.0.0' AND s.step_id = 'open_inventory'
        ");

        // Étape 28 : même geste à deux clics que Fouiller.
        $this->addSql("
            UPDATE tutorial_steps
               SET text = 'Cliquez sur <strong>Corps à corps</strong> dans le panneau d’actions, puis confirmez d’un second clic pour frapper l’âme d’entraînement !'
             WHERE version = '1.0.0' AND step_id = 'attack_enemy'
        ");

        $this->addSql("
            UPDATE tutorial_step_validation v
              JOIN tutorial_steps s ON s.id = v.step_id
               SET v.validation_hint = 'Armez puis confirmez le corps à corps'
             WHERE s.version = '1.0.0' AND s.step_id = 'attack_enemy'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            UPDATE tutorial_steps
               SET title = 'Fermer la fiche',
                   text  = 'Vous pouvez <strong>fermer la fiche</strong> en cliquant sur le bouton X, sur une case vide, ou ailleurs sur le damier.'
             WHERE version = '1.0.0' AND step_id = 'close_card'
        ");

        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.target_selector  = 'button.close-card',
                   u.tooltip_position = 'left'
             WHERE s.version = '1.0.0' AND s.step_id = 'close_card'
        ");

        $this->addSql("
            UPDATE tutorial_step_validation v
              JOIN tutorial_steps s ON s.id = v.step_id
               SET v.validation_hint = 'Fermez la fiche de personnage'
             WHERE s.version = '1.0.0' AND s.step_id = 'close_card'
        ");

        $this->addSql("
            INSERT INTO tutorial_step_interactions (step_id, selector, description)
            SELECT s.id, '.close-card', 'Bouton fermer'
              FROM tutorial_steps s
             WHERE s.version = '1.0.0' AND s.step_id = 'close_card'
               AND NOT EXISTS (
                    SELECT 1 FROM tutorial_step_interactions i
                     WHERE i.step_id = s.id AND i.selector = '.close-card'
               )
        ");

        $this->addSql("
            UPDATE tutorial_steps
               SET step_type = 'ui_interaction',
                   text = 'Fermez cette fiche. Nous allons aller vers un <strong>arbre</strong> pour le récolter.'
             WHERE version = '1.0.0' AND step_id = 'close_card_for_tree'
        ");

        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.target_selector  = 'button.close-card',
                   u.tooltip_position = 'left',
                   u.interaction_mode = 'semi-blocking',
                   u.show_delay       = 0
             WHERE s.version = '1.0.0' AND s.step_id = 'close_card_for_tree'
        ");

        $this->addSql("
            UPDATE tutorial_step_validation v
              JOIN tutorial_steps s ON s.id = v.step_id
               SET v.requires_validation = 1,
                   v.validation_type     = 'ui_element_hidden',
                   v.element_selector    = '#ui-card',
                   v.validation_hint     = 'Fermez la fiche'
             WHERE s.version = '1.0.0' AND s.step_id = 'close_card_for_tree'
        ");

        $this->addSql("
            INSERT INTO tutorial_step_interactions (step_id, selector, description)
            SELECT s.id, x.selector, x.description
              FROM tutorial_steps s
              JOIN (SELECT '.case' AS selector, 'Cases du damier' AS description
                    UNION ALL SELECT '.close-card', 'Bouton fermer') x
             WHERE s.version = '1.0.0' AND s.step_id = 'close_card_for_tree'
               AND NOT EXISTS (
                    SELECT 1 FROM tutorial_step_interactions i
                     WHERE i.step_id = s.id AND i.selector = x.selector
               )
        ");

        $this->addSql("
            UPDATE tutorial_steps
               SET text = 'Regardez les <strong>cases</strong> autour de vous : ce sont les cases où vous pouvez vous déplacer. Les cases marquées d’un <strong>⛔</strong> sont infranchissables (murs, joueurs, cases interdites). En jeu, ce repère peut être activé dans vos options.'
             WHERE version = '1.0.0' AND step_id = 'movement_intro'
        ");

        $this->addSql("
            UPDATE tutorial_step_validation v
              JOIN tutorial_steps s ON s.id = v.step_id
               SET v.validation_hint = 'Ouvrez le panneau des caractéristiques'
             WHERE s.version = '1.0.0' AND s.step_id = 'show_characteristics'
        ");

        $this->addSql("
            UPDATE tutorial_steps
               SET text = 'Voici vos <strong>actions disponibles</strong> ! Chaque action consomme des PA. Nous allons en tester une : la récolte de ressources.'
             WHERE version = '1.0.0' AND step_id = 'actions_panel_info'
        ");

        $this->addSql("
            UPDATE tutorial_steps
               SET text = 'Cliquez sur <strong>Fouiller</strong> pour récolter du bois de l''arbre.'
             WHERE version = '1.0.0' AND step_id = 'use_fouiller'
        ");

        $this->addSql("
            UPDATE tutorial_step_validation v
              JOIN tutorial_steps s ON s.id = v.step_id
               SET v.validation_hint = 'Utilisez l''action Fouiller'
             WHERE s.version = '1.0.0' AND s.step_id = 'use_fouiller'
        ");

        $this->addSql("
            UPDATE tutorial_steps
               SET text = 'Ouvrez votre <strong>Inventaire</strong> pour voir le bois récolté.'
             WHERE version = '1.0.0' AND step_id = 'open_inventory'
        ");

        $this->addSql("
            UPDATE tutorial_step_validation v
              JOIN tutorial_steps s ON s.id = v.step_id
               SET v.validation_hint = 'Cliquez sur le bouton Inventaire'
             WHERE s.version = '1.0.0' AND s.step_id = 'open_inventory'
        ");

        $this->addSql("
            UPDATE tutorial_steps
               SET text = 'Cliquez sur <strong>Corps à corps</strong> pour frapper l''âme d''entraînement !'
             WHERE version = '1.0.0' AND step_id = 'attack_enemy'
        ");

        $this->addSql("
            UPDATE tutorial_step_validation v
              JOIN tutorial_steps s ON s.id = v.step_id
               SET v.validation_hint = 'Attaquez l''ennemi'
             WHERE s.version = '1.0.0' AND s.step_id = 'attack_enemy'
        ");
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
