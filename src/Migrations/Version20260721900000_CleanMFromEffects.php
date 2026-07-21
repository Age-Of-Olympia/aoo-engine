<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Update the 'diamant' effect debuff_carac and description.
 */
final class Version20260721300000_CleanMFromEffects extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Update effect 'diamant' to debuff 'res' with description 'Diminue la Résistance de 1.'";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE effects SET debuff_carac = ?, description = ? WHERE name = ?",
            ['res', 'Diminue la Résistance de 1.', 'diamant']
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE effects SET debuff_carac = ?, description = ? WHERE name = ?",
            ['m', 'Diminue la Magie de 1.', 'diamant']
        );
    }
}