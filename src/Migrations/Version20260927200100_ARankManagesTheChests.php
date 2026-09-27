<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The manageChests rank flag: take a public chest back, give one to a
 * member, abandon one, choose the chest floors. The top rank of every
 * faction receives it — idempotent, a tuned ladder only gains.
 */
final class Version20260927200100_ARankManagesTheChests extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'faction_roles.manageChests — le droit de gérer les coffres de la faction, accordé au plus haut rang';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE faction_roles ADD COLUMN IF NOT EXISTS manageChests TINYINT(1) NOT NULL DEFAULT 0'
        );
        $this->addSql(
            'UPDATE faction_roles fr
               JOIN (SELECT faction_id, MAX(position) AS top FROM faction_roles GROUP BY faction_id) t
                 ON t.faction_id = fr.faction_id AND fr.position = t.top
                SET fr.manageChests = 1'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE faction_roles DROP COLUMN IF EXISTS manageChests');
    }
}
