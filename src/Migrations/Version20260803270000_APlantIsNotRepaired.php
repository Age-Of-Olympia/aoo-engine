<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `reparer` reaches only the families that mend: buildings, scenery and placed
 * objects. Attacks keep the whole branch — felling a tree is intended.
 *
 * Deployment window: between this migration and the code that reads families,
 * the old code does not recognise `building` and refuses `reparer` everywhere.
 * The reverse order would leave plants repairable.
 */
final class Version20260803270000_APlantIsNotRepaired extends AbstractMigration
{
    private const REPAIRABLE = '{"allowed":["building","scenery","item"]}';

    public function getDescription(): string
    {
        return 'reparer targets the families that mend, not the whole branch';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE action_conditions c
               JOIN actions a ON a.id = c.action_id
                SET c.parameters = ?
              WHERE a.name = 'reparer' AND c.conditionType = 'TargetType'",
            [self::REPAIRABLE]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE action_conditions c
               JOIN actions a ON a.id = c.action_id
                SET c.parameters = '{\"allowed\":[\"structure\"]}'
              WHERE a.name = 'reparer' AND c.conditionType = 'TargetType'"
        );
    }
}
