<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The useDoor rank flag: open and shut the faction's doors. The top rank
 * of every faction receives it — idempotent, a tuned ladder only gains.
 */
final class Version20260927210100_ARankOpensTheDoors extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'faction_roles.useDoor — le droit d\'ouvrir les portes de la faction, accordé au plus haut rang';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE faction_roles ADD COLUMN IF NOT EXISTS useDoor TINYINT(1) NOT NULL DEFAULT 0'
        );
        $this->addSql(
            'UPDATE faction_roles fr
               JOIN (SELECT faction_id, MAX(position) AS top FROM faction_roles GROUP BY faction_id) t
                 ON t.faction_id = fr.faction_id AND fr.position = t.top
                SET fr.useDoor = 1'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE faction_roles DROP COLUMN IF EXISTS useDoor');
    }
}
