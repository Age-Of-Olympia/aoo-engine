<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tutorial step coherence fixes for already-seeded environments.
 *
 * The initial seed (Version20251127000000_CreateCompleteTutorialSystem) was
 * edited in this session to correct two values, but editing the seed migration
 * only helps fresh installs — it has no effect on environments where that
 * migration (or the equivalent `db/init_noupdates.sql` snapshot) has already
 * been applied. This migration issues UPDATE statements that bring those
 * already-seeded rows in line with the corrected seed.
 *
 *   1. tutorial_steps.text for `movement_limit_warning` — previous wording
 *      "Chaque déplacement en consomme 1." was factually at odds with the
 *      preceding step `first_move` which has `unlimited_mvt=1`. Replaced by
 *      "À partir de maintenant, chaque déplacement consommera 1 mouvement."
 *      so the promise matches reality: consumption only starts at
 *      `deplete_movements`.
 *
 *   2. tutorial_step_ui.allow_manual_advance for `observe_tree` — the step's
 *      validation_type is `ui_panel_opened`, so the player must open the
 *      tree's panel (click the tree) to advance. With allow_manual_advance=1
 *      the step also rendered a Next button that bypassed the click entirely,
 *      letting the player skip the "look at the tree" teaching moment. Flipped
 *      to 0 so the tree click is the only way forward.
 *
 * Idempotent — every UPDATE scopes to the pre-fix value, so re-running is
 * a no-op.
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
