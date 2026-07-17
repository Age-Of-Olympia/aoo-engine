<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Nature d'un type de structure (races de sorte 'structure') :
 * - 'edifice'  : un vrai bâtiment — a une PORTE, toujours ouvrable /
 *                fermable (buildings.is_open) ; fermé, il tait son
 *                dialogue et fermera ses services ;
 * - 'obstacle' : un objet construit (palissade, mur…) — pas de porte ;
 *                le MÊME drapeau is_open signifiera un jour
 *                verrouillé / laisse passer (passabilité), système
 *                mutualisé avec les coffres.
 *
 * Sans objet pour les races de personnages (valeur ignorée).
 */
final class Version20260718150000_StructureNature extends AbstractMigration
{
    public function getDescription(): string
    {
        return "races.structure_nature : édifice (porte) vs obstacle (mur construit)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE races ADD structure_nature VARCHAR(20) NOT NULL DEFAULT 'edifice'");
        // Les structures existantes issues d'objets construits et le
        // porte-instance des objets uniques sont des obstacles.
        $this->addSql("UPDATE races SET structure_nature = 'obstacle' WHERE name IN ('palissade', 'mur_bois', 'objet')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races DROP COLUMN structure_nature');
    }
}
