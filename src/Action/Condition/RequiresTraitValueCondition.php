<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use App\Action\Schema\DeclaresSimulationInputs;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterSchema;
use App\Action\Schema\SimulationField;

class RequiresTraitValueCondition extends BaseCondition implements HasParameterSchema, DeclaresSimulationInputs
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public static function simulationInputs(array $params): array
    {
        $fields = [];
        foreach ($params as $trait => $value) {
            // Only real carac costs get a "disponible" field; marker params whose
            // value is a non-numeric string (e.g. energie:"both", repos:"effets")
            // aren't payable costs, so they get no input.
            if (!is_numeric($value) && !is_array($value)) {
                continue;
            }
            $fields[] = new SimulationField(SimulationField::KIND_REMAINING, SimulationField::SIDE_ACTOR, (string) $trait, 'Acteur — ' . $trait . ' disponible');
        }

        return $fields;
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $preConditionResult = parent::check($actor, $target, $condition, $conditionObject);

        if (!$preConditionResult->isSuccess()) {
            return $preConditionResult;
        }

        $result = new ConditionResult(true, array(), array());
        $params = $condition->getParameters(); // e.g. { "a": 1, "pm": 10 }

        $details = array();
        $costIsAffordable = true;

        foreach ($params as $key => $value) {
            if ($key == "energie") {
                continue;
            }
            else if($key == "imposture"){
                /* Clé de coût historique « imposture » = coût multiplié
                   par les effets portés cost_multiplier (catalogue). */
                $impostureValue = (new \App\Service\EffectService())->costMultiplier($actor->getEffects());
                if($actor->getRemaining("pm") < (floor($value[0]*$impostureValue))){
                    array_push($details, "Pas assez de PM");
                    $costIsAffordable = false;
                    break;
                }
                if($actor->getRemaining("mvt") < (floor($value[1]*$impostureValue))){
                    array_push($details, "Pas assez de Mvt");
                    $costIsAffordable = false;
                    break;
                }
            }   
            else if($key == "remaining"){
                if($actor->getRemaining($value) < 1){
                    array_push($details, "Pas assez de ".CARACS[$value]);
                    $costIsAffordable = false;
                    break;
                }
            }    
            else if($key == "remainingNullable"){
                    break;
            }   
            else if(is_array($value)){
                if ($actor->getRemaining($key) < $this->costForActorPassive($actor, $value)) {
                    array_push($details, "Pas assez de ".(CARACS[$key] ?? $key));
                    $costIsAffordable = false;
                }
            } else if (is_numeric($value)) {
                if ($actor->getRemaining($key) < $value) {
                    array_push($details, "Pas assez de ".(CARACS[$key] ?? $key));
                    $costIsAffordable = false;
                }
            }
            // A non-numeric value (e.g. {"repos":"effets"}) is a marker, not a
            // payable cost — it doesn't gate the action here.
        }
        
        if (!$costIsAffordable) {
            $result = new ConditionResult(false, array(), $details);
        }

        return $result;
    }

    public function applyCosts(ActorInterface $actor, ?ActorInterface $target, ActionCondition $conditionToPay): array
    {
        $result = array();
        $parameters = $conditionToPay->getParameters();
        foreach ($parameters as $key => $value) {
            if ($key == "energie") {
                continue;
            }
            if ($key == "imposture") {
                $impostureValue = (new \App\Service\EffectService())->costMultiplier($actor->getEffects());
                $pmCost = floor($value[0]*$impostureValue);
                $mvtCost = floor($value[1]*$impostureValue);
                $actor->putBonus(["pm" => -$pmCost]);
                $text1 = "Vous avez dépensé " . $pmCost . " PM.";
                array_push($result, $text1);
                $actor->putBonus(["mvt" => -$mvtCost]);
                $text1 = "Vous avez dépensé " . $mvtCost . " Mvt.";
                array_push($result, $text1);
                break;
            }
            if ($key == "remaining" || $key == "remainingNullable") {
                $nb = $actor->getRemaining($value);
                $actor->putBonus([$value => -$nb]);
                $text = "Vous avez dépensé " . $nb . " " . CARACS[$value] . ".";
                array_push($result, $text);
                break;
            }
            if(is_array($value)){
                $cost = $this->costForActorPassive($actor, $value);
                $actor->putBonus([$key => -$cost]);
                $text = "Vous avez dépensé " . $cost . " " . (CARACS[$key] ?? $key).".";
                array_push($result, $text);
                break;
            }
            if (!is_numeric($value)) {
                continue; // marker (e.g. {"repos":"effets"}), nothing to spend
            }
            $actor->putBonus([$key => -$value]);
            $text = "Vous avez dépensé " . $value . " " . (CARACS[$key] ?? $key).".";
            array_push($result, $text);
        }
        return $result;
    }

    /**
     * How much of the resource this action costs *for this actor*, when the cost
     * varies by passive. The option list is [passiveName, cost] pairs plus a
     * ["none", cost] fallback, e.g. [["berserk", 5], ["none", 3]] = "5 if the
     * actor has the berserk passive, otherwise 3". The passive is always active;
     * it only selects which cost applies. The "none" fallback is read whether or
     * not the actor has any passives so check() and applyCosts() agree.
     *
     * @param array<int, array{0: string, 1: int|string}> $options
     */
    private function costForActorPassive(ActorInterface $actor, array $options): int
    {
        $passives = $actor->getPassives($actor->getId());
        $default = 0;
        foreach ($options as $option) {
            foreach ($passives as $passive) {
                if ($passive->getName() == $option[0]) {
                    return (int) $option[1];
                }
            }
            if ($option[0] == "none") {
                $default = (int) $option[1];
            }
        }

        return $default;
    }

    public function toRemove(): bool {
        return true;
    }
}