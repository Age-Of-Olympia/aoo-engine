<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `venerer` is aimed AT the altar, so its outcome says target.
 *
 * It said `self`, because worshipping changes the worshipper. But the engine
 * reads `apply_to` to know what an action can be aimed at
 * (`ActionTargeting::scopeOf`), so `self` meant "cannot be used on anything
 * else" and the button never appeared on an altar — the one place it belongs.
 *
 * Who is aimed at and who changes are two different questions: the
 * instruction already carries the second, through from/to.
 */
final class Version20260730110000_VenererViseLautel extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'venerer aims at the altar: its outcome applies to the target';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE action_outcomes o
               JOIN actions a ON a.id = o.action_id
                SET o.apply_to = 'target'
              WHERE a.name = 'venerer' AND o.apply_to = 'self'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE action_outcomes o
               JOIN actions a ON a.id = o.action_id
                SET o.apply_to = 'self'
              WHERE a.name = 'venerer' AND o.apply_to = 'target'"
        );
    }
}
