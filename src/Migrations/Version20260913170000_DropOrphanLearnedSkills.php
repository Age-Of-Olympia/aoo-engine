<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Version20260721400000 listed 'dm1/pic_de_pierre' where the rows read
 * 'dmg1/pic_de_pierre', so that learned spell outlived its action and the
 * Sorts page reports it as misconfigured. Rather than chase the name, drop
 * every bought skill whose action is gone: such a row can neither be cast
 * nor forgotten.
 */
final class Version20260913170000_DropOrphanLearnedSkills extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'supprime les sorts appris dont l\'action n\'existe plus';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "DELETE pa FROM players_actions pa
             LEFT JOIN actions a ON a.name = pa.name
             WHERE pa.type = 'sort' AND a.id IS NULL"
        );
    }

    public function down(Schema $schema): void
    {
    }
}
