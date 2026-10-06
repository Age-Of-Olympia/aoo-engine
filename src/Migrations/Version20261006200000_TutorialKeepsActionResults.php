<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The tutorial shows the result of its two actions.
 *
 * Fouiller closed the player's card as soon as the step validated, taking
 * the harvest result with it; the "Ennemi blessé" tooltip sat on the attack
 * result in the enemy's card. The texts now say where the result is.
 */
final class Version20261006200000_TutorialKeepsActionResults extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'tutoriel: résultats de Fouiller et du corps à corps visibles dans la fiche';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.auto_close_card = 0
             WHERE s.version = '1.0.0' AND s.step_id = 'use_fouiller'
        ");

        $this->addSql(
            "UPDATE tutorial_steps SET text = ? WHERE version = '1.0.0' AND step_id = 'action_consumed'",
            ['Vous avez récolté du <strong>bois</strong> : le résultat s’affiche dans votre fiche, sous le damier. Remarquez que l’action a consommé <strong>1 A</strong>. Vos A se régénèrent aussi à chaque tour.']
        );

        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.tooltip_position = 'center-top'
             WHERE s.version = '1.0.0' AND s.step_id = 'attack_result'
        ");

        $this->addSql(
            "UPDATE tutorial_steps SET text = ? WHERE version = '1.0.0' AND step_id = 'attack_result'",
            ['Excellent ! Le <strong>résultat de l’attaque</strong> s’affiche dans la fiche de l’âme, sous le damier. La <strong>barre rouge</strong> sur son portrait montre les PV qu’elle a perdus.']
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.auto_close_card = 1
             WHERE s.version = '1.0.0' AND s.step_id = 'use_fouiller'
        ");

        $this->addSql(
            "UPDATE tutorial_steps SET text = ? WHERE version = '1.0.0' AND step_id = 'action_consumed'",
            ['Vous avez récolté du <strong>bois</strong> ! Remarquez que l\'action a consommé <strong>1 A</strong>. Vos A se régénèrent aussi à chaque tour.']
        );

        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.tooltip_position = 'top'
             WHERE s.version = '1.0.0' AND s.step_id = 'attack_result'
        ");

        $this->addSql(
            "UPDATE tutorial_steps SET text = ? WHERE version = '1.0.0' AND step_id = 'attack_result'",
            ['Excellent ! Vous pouvez voir le <strong>résultat de l\'attaque</strong> : l\'ennemi a perdu des PV ! Regardez la barre rouge qui indique les dégâts.']
        );
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
