<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Realigns two already-seeded tutorial values with the corrected seed:
 *   1. `movement_limit_warning.text` — drops the "Chaque déplacement en
 *      consomme 1." clause that contradicted the preceding `first_move` step
 *      (which runs with `unlimited_mvt=1`); consumption only starts at
 *      `deplete_movements`.
 *   2. `observe_tree.allow_manual_advance` — forced to 0 so the step's
 *      `ui_panel_opened` validation is the only way forward (the Next button
 *      previously let the player skip the teaching click).
 * Idempotent: every UPDATE scopes to the pre-fix value.
 */
final class Version20260420110000_TutorialStepCoherenceFixes extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix movement_limit_warning text + force observe_tree to require clicking the tree';
    }

    public function up(Schema $schema): void
    {
        /* 1. movement_limit_warning text */
        $this->addSql("
            UPDATE tutorial_steps
            SET text = '<strong>Attention !</strong> En jeu réel, vos mouvements sont <strong>limités</strong>. Vous avez {max_mvt} mouvements par tour. <strong>À partir de maintenant, chaque déplacement consommera 1 mouvement.</strong>'
            WHERE step_id = 'movement_limit_warning'
              AND version = '1.0.0'
              AND text = '<strong>Attention !</strong> En jeu réel, vos mouvements sont <strong>limités</strong>. Vous avez {max_mvt} mouvements par tour. Chaque déplacement en consomme 1.'
        ");

        /* 2. observe_tree allow_manual_advance flip.
         * JOIN on tutorial_steps because tutorial_step_ui.step_id references
         * the numeric id column, not the string step_id. */
        $this->addSql("
            UPDATE tutorial_step_ui tsu
            JOIN tutorial_steps ts ON ts.id = tsu.step_id
            SET tsu.allow_manual_advance = 0
            WHERE ts.step_id = 'observe_tree'
              AND ts.version = '1.0.0'
              AND tsu.allow_manual_advance = 1
        ");
    }

    public function down(Schema $schema): void
    {
        /* Revert both. */
        $this->addSql("
            UPDATE tutorial_steps
            SET text = '<strong>Attention !</strong> En jeu réel, vos mouvements sont <strong>limités</strong>. Vous avez {max_mvt} mouvements par tour. Chaque déplacement en consomme 1.'
            WHERE step_id = 'movement_limit_warning'
              AND version = '1.0.0'
              AND text = '<strong>Attention !</strong> En jeu réel, vos mouvements sont <strong>limités</strong>. Vous avez {max_mvt} mouvements par tour. <strong>À partir de maintenant, chaque déplacement consommera 1 mouvement.</strong>'
        ");

        $this->addSql("
            UPDATE tutorial_step_ui tsu
            JOIN tutorial_steps ts ON ts.id = tsu.step_id
            SET tsu.allow_manual_advance = 1
            WHERE ts.step_id = 'observe_tree'
              AND ts.version = '1.0.0'
              AND tsu.allow_manual_advance = 0
        ");
    }
}
