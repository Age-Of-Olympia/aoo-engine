<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The objecteffect instruction leaves the attack type.
 *
 * It was seeded on `attack` by Version20260622130000 and applied the effects
 * carried by the equipped weapon. The "carac Magique" work moved that to
 * ActionExecutorService::applyEquippedItemsEffects(), and the instruction was
 * emptied to an explicit no-op — but its row stayed. Every attack still
 * resolved a class, built it, called it and got an empty result back, and the
 * workbench listed it under "héritées du type" as if it were configuration.
 *
 * A ghost like this becomes a rule: the next reader configures against an
 * instruction that does nothing. The class goes with the row.
 *
 * Safe in both deployment orders: an unknown instruction type is skipped by
 * ActionTypeInstructionResolver::build(), so a leftover row without its class
 * is simply ignored.
 */
final class Version20260830130000_TheObjectEffectInstructionLeaves extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'retire l\'instruction objecteffect du type attack : les effets d\'objets passent par applyEquippedItemsEffects depuis la carac Magique';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM action_type_instructions WHERE instruction_type = 'objecteffect'");
    }

    public function down(Schema $schema): void
    {
        // Deliberately empty: the instruction no longer exists in code, and
        // restoring the row would only bring the ghost back.
    }
}
