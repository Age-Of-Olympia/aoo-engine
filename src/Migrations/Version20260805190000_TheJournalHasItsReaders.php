<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The journal has its READERS: the showLogs rank flag says who opens
 * the faction's journal. The top rank of every faction receives it —
 * idempotent, a tuned ladder only gains — and the ladder editor
 * distributes it like every other flag.
 */
final class Version20260805190000_TheJournalHasItsReaders extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'faction_roles.showLogs — le droit de lire le journal, accordé au plus haut rang';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE faction_roles ADD COLUMN IF NOT EXISTS showLogs TINYINT(1) NOT NULL DEFAULT 0'
        );
        $this->addSql(
            'UPDATE faction_roles fr
               JOIN (SELECT faction_id, MAX(position) AS top FROM faction_roles GROUP BY faction_id) t
                 ON t.faction_id = fr.faction_id AND fr.position = t.top
                SET fr.showLogs = 1'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE faction_roles DROP COLUMN IF EXISTS showLogs');
    }
}
