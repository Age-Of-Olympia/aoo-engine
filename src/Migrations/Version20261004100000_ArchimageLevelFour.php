<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The Archimage passives sit at level 4 of the magic tree, not 5.
 */
final class Version20261004100000_ArchimageLevelFour extends AbstractMigration
{
    private const NAMES = ['archimage_i', 'archimage_ii', 'archimage_iii', 'archimage_iv', 'archimage_v'];

    public function getDescription(): string
    {
        return 'Passifs Archimage au niveau 4';
    }

    public function up(Schema $schema): void
    {
        $this->setLevel(4);
    }

    public function down(Schema $schema): void
    {
        $this->setLevel(5);
    }

    private function setLevel(int $level): void
    {
        $in = implode(',', array_fill(0, count(self::NAMES), '?'));
        $this->addSql("UPDATE action_passives SET level = ? WHERE name IN ($in)", [$level, ...self::NAMES]);
    }
}
