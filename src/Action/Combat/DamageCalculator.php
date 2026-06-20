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

    /**
     * The single source of truth for combat damage before distance/crit/encaisse
     * and before the minimum-1 floor: base (attack - defense, floored at 0 when
     * the target has a defense bonus) plus the additional modifiers. Used by both
     * LifeLossOutcomeInstruction::execute() and the simulator.
     */
    public function rawDamage(int $actorDamages, int $targetDefense, DamageModifiers $modifiers): int
    {
        $base = $actorDamages - $targetDefense;
        if ($modifiers->bonusDefense > 0) {
            $base = max(0, $base);
        }

        return $base + $this->additionalDamages($modifiers);
    }

    /**
     * rawDamage() with the minimum-1 floor — the damage a hit deals before
     * distance, crit and encaisse.
     */
    public function totalDamage(int $actorDamages, int $targetDefense, DamageModifiers $modifiers): int
    {
        return max(1, $this->rawDamage($actorDamages, $targetDefense, $modifiers));
    }
}
