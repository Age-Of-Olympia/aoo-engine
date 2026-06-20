<?php

namespace App\Action\Combat;

final class DamageCalculator
{
    public function additionalDamages(DamageModifiers $modifiers): int
    {
        $offense = $modifiers->bonusDamages + $modifiers->othersDamages + $modifiers->agressivite - $modifiers->faiblesse;
        $defense = $modifiers->bonusDefense + $modifiers->othersDefense + $modifiers->armure - $modifiers->fragilite;

        return $offense - $defense;
    }
}
