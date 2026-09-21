<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919160000_AnEffectSaysWhatItDoes extends AbstractMigration
{
    public function getDescription(): string
    {
        return "effects.apply_text: the sentence shown when the effect lands ({cible} prend feu)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE effects ADD COLUMN IF NOT EXISTS apply_text VARCHAR(255) NOT NULL DEFAULT ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE effects DROP COLUMN IF EXISTS apply_text');
    }
}
