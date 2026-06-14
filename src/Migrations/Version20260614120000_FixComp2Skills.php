<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Data fix for several skills (port of db/updates/20260601_comp2_fix.sql):
 * - action 113 "Jet de sable": updated text and cost
 * - action_condition 354: updated parameters
 * - action_passive 3 "Dur à cuire": name, display_name and level
 */
final class Version20260614120000_FixComp2Skills extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix skills data: Jet de sable (action 113), condition 354, Dur à cuire passive (3)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE actions SET text = 'Attaque sans dégâts et sans arme. Aveuglement (x2)' WHERE id = 113"
        );
        $this->addSql(
            'UPDATE actions SET cost = \'<span style="color: #8e44ad;">1 A</span>, '
            . '<span style="color: #2980b9;">10 PM</span>, '
            . '<span style="color: #27ae60;">1 Mvt</span>\' WHERE id = 113'
        );
        $this->addSql(
            'UPDATE action_conditions SET parameters = \'{"a":1, "pm":10, "mvt":1}\' WHERE id = 354'
        );
        $this->addSql("UPDATE action_passives SET name = 'dur_cuire' WHERE id = 3");
        $this->addSql("UPDATE action_passives SET display_name = 'Dur à cuire' WHERE id = 3");
        $this->addSql('UPDATE action_passives SET level = 3 WHERE id = 3');
    }

    public function down(Schema $schema): void
    {
        // No-op: original row values are not available to reverse this data fix.
    }
}
