<?php

namespace App\Action;

use App\Entity\Action;

abstract class AttackAction extends Action
{
    // Adrenaline + object-effect used to be added here in code; they now live as
    // type-level instructions under the "attack" key (action_type_instructions)
    // and are applied by the executor via ActionTypeInstructionResolver. XP and
    // log messages are likewise data now (action_type_xp / action_type_logs).

    public function activateAntiBerserk(): bool {
        return true;
    }
}
