<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Refresh the "Se déplacer" step text to use the ⛔ glyph (matching
 * the new blocked-tile marker) and reflect that the marker now also
 * covers cells under a 'forbidden' trigger and can be enabled in
 * regular play through the player options.
 *
 * Idempotent + scoped: only updates the row when it's still on the
 * exact previous text from Version20260430210000 (so admin edits
 * via tutorial-step-editor are preserved).
 */
final class Version20260501000000_UpdateMovementIntroForbiddenIcon extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Refresh movement_intro step text to use the ⛔ glyph";
    }

    public function up(Schema $schema): void
    {
        $newText = 'Regardez les <strong>cases</strong> autour de vous : ce sont les cases où vous pouvez vous déplacer. Les cases marquées d\'un <strong>⛔</strong> sont infranchissables (murs, joueurs, cases interdites). En jeu, ce repère peut être activé dans vos options.';
        $oldText = 'Regardez les <strong>cases</strong> autour de vous : ce sont les cases où vous pouvez vous déplacer. Pendant le tutoriel, les cases marquées d\'une <strong style="color:#dc1e1e">×</strong> rouge sont infranchissables — ce marqueur n\'apparaît pas dans le jeu réel.';

        $this->addSql(
            "UPDATE tutorial_steps SET text = ?
             WHERE step_id = 'movement_intro'
               AND version = '1.0.0'
               AND text = ?",
            [$newText, $oldText]
        );
    }

    public function down(Schema $schema): void
    {
        $newText = 'Regardez les <strong>cases</strong> autour de vous : ce sont les cases où vous pouvez vous déplacer. Les cases marquées d\'un <strong>⛔</strong> sont infranchissables (murs, joueurs, cases interdites). En jeu, ce repère peut être activé dans vos options.';
        $oldText = 'Regardez les <strong>cases</strong> autour de vous : ce sont les cases où vous pouvez vous déplacer. Pendant le tutoriel, les cases marquées d\'une <strong style="color:#dc1e1e">×</strong> rouge sont infranchissables — ce marqueur n\'apparaît pas dans le jeu réel.';

        $this->addSql(
            "UPDATE tutorial_steps SET text = ?
             WHERE step_id = 'movement_intro'
               AND version = '1.0.0'
               AND text = ?",
            [$oldText, $newText]
        );
    }
}
