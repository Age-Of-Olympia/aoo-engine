<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A spendable carac (PV, PM, A, Mvt) can be LOST on application instead of
 * having its ceiling moved: loss_mods holds those entries, same shape as
 * carac_mods. pv_on_apply folds into it and is no longer read.
 */
final class Version20260919220000_AnEffectTakesFromThePool extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'effects.loss_mods: {"pv": -10} taken from the pool on application; seeded from pv_on_apply';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE effects ADD COLUMN IF NOT EXISTS loss_mods TEXT NULL');
        $this->addSql("UPDATE effects SET loss_mods = CONCAT('{\"pv\":', pv_on_apply, '}') WHERE loss_mods IS NULL AND pv_on_apply <> 0");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE effects DROP COLUMN IF EXISTS loss_mods');
    }
}
