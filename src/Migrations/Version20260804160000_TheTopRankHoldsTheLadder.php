<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The top rank holds the ladder.
 *
 * Every faction's highest role (position ascends: the last rung rules)
 * gains editRole and initRole: it moves its people between ranks and
 * settles what each lower rank authorizes — the in-game ladder gestures
 * shipping with the faction screen. Idempotent; a ladder the admin
 * already tuned only gains, never loses.
 */
final class Version20260804160000_TheTopRankHoldsTheLadder extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Le plus haut rang de chaque faction reçoit editRole et initRole';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE faction_roles fr
               JOIN (SELECT faction_id, MAX(position) AS top FROM faction_roles GROUP BY faction_id) t
                 ON t.faction_id = fr.faction_id AND fr.position = t.top
                SET fr.editRole = 1, fr.initRole = 1'
        );
    }

    public function down(Schema $schema): void
    {
        // The grant cannot be told apart from an admin's own: nothing to undo.
        $this->warnIf(true, 'editRole/initRole du plus haut rang conservés (indiscernables d\'un réglage admin).');
    }
}
