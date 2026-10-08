<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Vénérer pointed at ra-prayer, a glyph RPG-Awesome does not ship: the
 * action showed no icon. It takes the candle; an icon an admin already
 * changed is left alone.
 */
final class Version20261008120000_VenererCandleIcon extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'vénérer : icône ra-candle à la place de ra-prayer, absente de la police';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE actions SET icon = 'ra-candle' WHERE name = 'venerer' AND icon = 'ra-prayer'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE actions SET icon = 'ra-prayer' WHERE name = 'venerer' AND icon = 'ra-candle'");
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
