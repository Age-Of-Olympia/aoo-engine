<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926180000_LeftoverPlantRowsGo extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'map_plants rows left after the plant entities conversion: no plant in the game, only a ghost in the cell panel';
    }

    public function up(Schema $schema): void
    {
        // Plants are entities; nothing reads or writes this table any more
        $this->addSql('DELETE FROM map_plants');
    }

    public function down(Schema $schema): void
    {
        // The rows described nothing the game had: nothing to restore
    }
}
