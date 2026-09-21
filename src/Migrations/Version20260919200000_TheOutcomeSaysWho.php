<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ApplyStatus and Player instructions no longer carry their own
 * "player" (actor/target/both): the outcome's apply_to decides. The key is
 * removed from the stored parameters so the workbench stops showing it as
 * a raw leftover.
 */
final class Version20260919200000_TheOutcomeSaysWho extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'outcome_instructions: drop the "player" key from ApplyStatus and Player parameters';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE outcome_instructions
                SET parameters = JSON_REMOVE(parameters, '$.player')
              WHERE type IN ('applystatus', 'player')
                AND JSON_VALID(parameters)
                AND JSON_CONTAINS_PATH(parameters, 'one', '$.player')"
        );
    }

    public function down(Schema $schema): void
    {
        // The key came from the outcome's toggle; nothing to restore.
    }
}
