<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Production item #1251 (Frénésie liquide) granted +2 A while consuming it
 * cost 1 A, a net +1. Consuming now costs 1 Ac (Version20261103100000), so
 * the bonus drops to +1 A to keep the same net gain. Guarded on the current
 * value: idempotent, and a no-op where the item does not exist (dev, CI).
 */
final class Version20261104100000_FrenesieLiquideGivesOneAction extends AbstractMigration
{
    private const ITEM_ID = 1251;

    public function getDescription(): string
    {
        return 'Frénésie liquide (#1251) donne 1 A au lieu de 2';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE items SET a = 1 WHERE id = ? AND a = 2', [self::ITEM_ID]);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE items SET a = 2 WHERE id = ? AND a = 1', [self::ITEM_ID]);
    }
}
