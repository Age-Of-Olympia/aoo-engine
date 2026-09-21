<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919150000_AnEffectMovesSeveralCaracs extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'effects.carac_mods: {"carac": sign} JSON, seeded from buff_carac / debuff_carac';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE effects ADD COLUMN IF NOT EXISTS carac_mods TEXT NULL');

        // One row per legacy pair; a carac in both columns cancels out, as it did.
        $this->addSql(
            "UPDATE effects
                SET carac_mods = CONCAT(
                    '{',
                    IF(buff_carac IS NOT NULL AND buff_carac <> '', CONCAT('\"', buff_carac, '\":1'), ''),
                    IF(buff_carac IS NOT NULL AND buff_carac <> '' AND debuff_carac IS NOT NULL AND debuff_carac <> '', ',', ''),
                    IF(debuff_carac IS NOT NULL AND debuff_carac <> '', CONCAT('\"', debuff_carac, '\":-1'), ''),
                    '}'
                )
              WHERE carac_mods IS NULL
                AND ((buff_carac IS NOT NULL AND buff_carac <> '') OR (debuff_carac IS NOT NULL AND debuff_carac <> ''))"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE effects DROP COLUMN IF EXISTS carac_mods');
    }
}
