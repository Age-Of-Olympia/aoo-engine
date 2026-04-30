<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Refresh the "Se déplacer" step text to mention the tutorial-only
 * red × marker on non-walkable tiles. The marker is rendered by the
 * spotlight (Version20260430200000 family) and only appears during
 * the tutorial — players who skip it never see it, so the text now
 * tells them so.
 *
 * Idempotent + scoped: only updates the row when it's still on the
 * exact previous text (so admin edits via tutorial-step-editor are
 * preserved).
 */
final class Version20260430210000_UpdateMovementIntroText extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Refresh movement_intro step text to explain the tutorial-only × marker";
    }

    public function up(Schema $schema): void
    {
        $newText = 'Regardez les <strong>cases</strong> autour de vous : ce sont les cases où vous pouvez vous déplacer. Pendant le tutoriel, les cases marquées d\'une <strong style="color:#dc1e1e">×</strong> rouge sont infranchissables — ce marqueur n\'apparaît pas dans le jeu réel.';
        $oldText = 'Regardez les <strong>cases</strong> autour de vous ! Ce sont les cases où vous pouvez vous déplacer si elles sont vides.';

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
        $newText = 'Regardez les <strong>cases</strong> autour de vous : ce sont les cases où vous pouvez vous déplacer. Pendant le tutoriel, les cases marquées d\'une <strong style="color:#dc1e1e">×</strong> rouge sont infranchissables — ce marqueur n\'apparaît pas dans le jeu réel.';
        $oldText = 'Regardez les <strong>cases</strong> autour de vous ! Ce sont les cases où vous pouvez vous déplacer si elles sont vides.';

        $this->addSql(
            "UPDATE tutorial_steps SET text = ?
             WHERE step_id = 'movement_intro'
               AND version = '1.0.0'
               AND text = ?",
            [$oldText, $newText]
        );
    }
}
