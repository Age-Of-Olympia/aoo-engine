<?php

namespace App\Action\Condition;

use App\Action\SpellAction;
use App\Entity\ActionCondition;
use App\Entity\Effect;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use App\Action\Schema\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;
use App\Service\EffectService;
use Classes\View;

/**
 * Postures de défense de la cible (catalogue : dodge_*) — chaque posture
 * portée dont la portée et les exigences d'armes matchent annule
 * l'attaque, est consommée, applique sa réaction et laisse son message.
 * Les cinq postures historiques (parade, leurre, dédoublement, clé de
 * bras, pas de côté) ne sont plus codées par nom : l'admin peut en
 * composer d'autres.
 */
class DodgeCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $result = new ConditionResult(true, array(), array());

        $attackKind = $condition->getAction() instanceof SpellAction ? 'spell' : 'physical';

        $errorMessages = array();

        $effectService = new EffectService();

        foreach ($effectService->carriedStances($target->getEffects()) as $stance) {

            if (!$this->scopeMatches($stance, $attackKind)
                || !$this->attackerWeaponMatches($stance, $actor)
                || !$this->defenderWeaponMatches($stance, $target)) {
                continue;
            }

            $target->end_effect($stance->getName());
            $this->applyReaction($stance, $actor, $target);

            $errorMessages[sizeof($errorMessages)] = $this->message($stance, $actor, $target);
            $result = new ConditionResult(false, array(), $errorMessages);
        }

        return $result;
    }

    private function scopeMatches(Effect $stance, string $attackKind): bool
    {
        return $stance->getDodgeScope() === 'any' || $stance->getDodgeScope() === $attackKind;
    }

    private function attackerWeaponMatches(Effect $stance, ActorInterface $actor): bool
    {
        if ($stance->getDodgeAttackerWeapon() !== 'melee') {
            return true;
        }

        return isset($actor->emplacements->main1)
            && $actor->emplacements->main1->data->subtype == 'melee';
    }

    private function defenderWeaponMatches(Effect $stance, ActorInterface $target): bool
    {
        return match ($stance->getDodgeDefenderWeapon()) {
            // Arme de mêlée en main (une éventuelle arme à deux mains
            // doit elle-même être de mêlée).
            'melee' => (!isset($target->emplacements->deuxmains)
                    || $target->emplacements->deuxmains->data->subtype == 'melee')
                && isset($target->emplacements->main1)
                && $target->emplacements->main1->data->subtype == 'melee',
            'poing' => isset($target->emplacements->main1)
                && $target->emplacements->main1->data->name == 'Poing',
            default => true,
        };
    }

    private function applyReaction(Effect $stance, ActorInterface $actor, ActorInterface $target): void
    {
        switch ($stance->getDodgeReaction()) {
            case 'immobilize_attacker':
                $actor->putBonus(array('mvt' => -$actor->getRemaining('mvt')));
                break;

            case 'step_aside':
                $goCoords = $target->coords;
                $goCoords->id = View::get_free_coords_id_arround($target->coords);
                $target->go($goCoords);
                $this->shouldRefresh = true;
                break;

            case 'delete_double':
                View::delete_double($target);
                $this->shouldRefresh = true;
                break;
        }
    }

    private function message(Effect $stance, ActorInterface $actor, ActorInterface $target): string
    {
        $text = strtr($stance->getDodgeMessage() !== ''
            ? $stance->getDodgeMessage()
            : '{defender} esquive votre attaque !', [
            '{attacker}' => $actor->data->name,
            '{defender}' => $target->data->name,
        ]);

        return $text . ' (' . $stance->getName() . ' <span class="ra ' . $stance->getIcon() . '"></span>)';
    }
}
