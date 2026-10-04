<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The craft event names what was made ({item}). Only the untouched seed
 * template is rewritten: an edited one is the admins' wording.
 */
final class Version20261004110000_CraftEventNamesItem extends AbstractMigration
{
    private const BEFORE = '{actor} a fabriqué.';
    private const AFTER = '{actor} a fabriqué {item}.';

    public function getDescription(): string
    {
        return "L'événement de fabrication nomme l'objet fabriqué";
    }

    public function up(Schema $schema): void
    {
        $this->swap(self::BEFORE, self::AFTER);
    }

    public function down(Schema $schema): void
    {
        $this->swap(self::AFTER, self::BEFORE);
    }

    private function swap(string $from, string $to): void
    {
        $this->addSql(
            "UPDATE action_type_logs SET actor_template = ? WHERE type_key = 'craft' AND actor_template = ?",
            [$to, $from]
        );
    }
}
