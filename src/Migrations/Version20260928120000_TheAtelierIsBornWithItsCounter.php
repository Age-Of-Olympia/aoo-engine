<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A new atelier is born with the atelier dialog, so it repairs and
 * recycles without an admin setting it by hand. The ateliers already
 * standing without a dialog take it too.
 */
final class Version20260928120000_TheAtelierIsBornWithItsCounter extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Dialogue par défaut de l'atelier : réparer et recycler dès la construction";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE races SET default_dialog = 'atelier' WHERE name = 'atelier' AND default_dialog = ''");
        $this->addSql(
            "UPDATE buildings b JOIN players p ON p.id = b.player_id
                SET b.dialog = 'atelier'
              WHERE p.race = 'atelier' AND b.dialog = ''"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE races SET default_dialog = '' WHERE name = 'atelier' AND default_dialog = 'atelier'");
    }
}
