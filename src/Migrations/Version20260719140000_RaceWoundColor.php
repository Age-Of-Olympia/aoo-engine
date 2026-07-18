<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Couleur du voile de blessure par race/type (races.wound_color) : le
 * rouge sang historique reste le défaut pour les personnages, mais une
 * structure ne saigne pas rouge — les types structure existants passent
 * au bronze, ajustable ensuite dans admin → Races.
 */
final class Version20260719140000_RaceWoundColor extends AbstractMigration
{
    /** Le rouge du voile historique (rgba(119, 0, 1)). */
    private const DEFAULT_WOUND_COLOR = '#770001';

    private const STRUCTURE_WOUND_COLOR = '#cd7f32';

    public function getDescription(): string
    {
        return 'Add races.wound_color (wound veil tint), bronze for structure kinds';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(sprintf(
            "ALTER TABLE races ADD COLUMN IF NOT EXISTS wound_color VARCHAR(20) NOT NULL DEFAULT '%s'",
            self::DEFAULT_WOUND_COLOR
        ));

        // Seulement les lignes encore au défaut : re-jouer la migration ne
        // doit pas écraser un réglage admin.
        $this->addSql(
            "UPDATE races SET wound_color = ? WHERE kind = 'structure' AND wound_color = ?",
            [self::STRUCTURE_WOUND_COLOR, self::DEFAULT_WOUND_COLOR]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS wound_color');
    }
}
