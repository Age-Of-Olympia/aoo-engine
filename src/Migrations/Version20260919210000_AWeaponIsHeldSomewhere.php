<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A RequiresWeaponType saved from the workbench without an emplacement
 * carried "location": [] — read as "no slot to look at", so the action
 * refused with an empty « arme de type . ». The empty list is the
 * default (main1) now; the stored key is dropped so the rows read as
 * they did before being re-saved.
 */
final class Version20260919210000_AWeaponIsHeldSomewhere extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'action_conditions: drop an empty "location" from RequiresWeaponType parameters';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE action_conditions
                SET parameters = JSON_REMOVE(parameters, '$.location')
              WHERE conditionType = 'RequiresWeaponType'
                AND JSON_VALID(parameters)
                AND JSON_LENGTH(parameters, '$.location') = 0"
        );
    }

    public function down(Schema $schema): void
    {
        // Nothing to restore: an absent key and an empty list now mean the same.
    }
}
