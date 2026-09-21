<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Area effects will be a family of action outputs of their own; until then a weapon effect lands on the bearer or the target. */
final class Version20260919190000_NoAreaOnItemEffectsYet extends AbstractMigration
{
    public function getDescription(): string
    {
        return "item_effects.target: drop the 'area' value";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE item_effects SET target = 'target' WHERE target = 'area'");
        $this->addSql("ALTER TABLE item_effects MODIFY target ENUM('self', 'target') NOT NULL DEFAULT 'target'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE item_effects MODIFY target ENUM('self', 'target', 'area') NOT NULL DEFAULT 'target'");
    }
}
