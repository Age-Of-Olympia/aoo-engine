<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * L'infobulle du résultat d'attaque s'ouvre vers le HAUT.
 *
 * Elle désigne la jauge de blessure (#red-filter) — dans le HUD, le
 * portrait de la cible vit dans le bandeau BAS de l'écran : ouverte « à
 * droite », l'infobulle se rabattait dans le bandeau et son bouton
 * « Suivant » passait sous le bord de l'écran (le HUD ne défile pas).
 * Au-dessus de la cible, elle flotte sur le damier, toujours libre.
 */
final class Version20260820165000_TheWoundSpeaksUpward extends AbstractMigration
{
    public function getDescription(): string
    {
        return "tutoriel: l'infobulle du résultat d'attaque s'ouvre au-dessus de la jauge";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.tooltip_position = 'top'
             WHERE s.version = '1.0.0' AND s.step_id = 'attack_result'
               AND u.target_selector = '#red-filter'
               AND u.tooltip_position = 'right'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            UPDATE tutorial_step_ui u
              JOIN tutorial_steps s ON s.id = u.step_id
               SET u.tooltip_position = 'right'
             WHERE s.version = '1.0.0' AND s.step_id = 'attack_result'
               AND u.target_selector = '#red-filter'
               AND u.tooltip_position = 'top'
        ");
    }
}
