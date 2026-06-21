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
        foreach (array_keys($params) as $trait) {
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
                $impostureValue = $actor->playerEffectService->getEffectValueByPlayerIdByEffectName($actor->id,"imposture") + 1;
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
                if ($actor->getRemaining($key) < $this->passiveArrayCost($actor, $value)) {
                    array_push($details, "Pas assez de ".CARACS[$key]);
                    $costIsAffordable = false;
                }
            } else if ($actor->getRemaining($key) < $value) {
                array_push($details, "Pas assez de ".CARACS[$key]);
                $costIsAffordable = false;
            }
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
                $impostureValue = $actor->playerEffectService->getEffectValueByPlayerIdByEffectName($actor->id,"imposture") + 1;
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
                $cost = $this->passiveArrayCost($actor, $value);
                $actor->putBonus([$key => -$cost]);
                $text = "Vous avez dépensé " . $cost . " " . CARACS[$key].".";
                array_push($result, $text);
                break;
            }
            $actor->putBonus([$key => -$value]);
            $text = "Vous avez dépensé " . $value . " " . CARACS[$key].".";
            array_push($result, $text);
        }
        return $result;
    }

    /**
     * Cost for a passive-gated entry like [["berserk", 5], ["none", 3]]: the value
     * tied to the first listed passive the actor owns, otherwise the "none"
     * default. The default is read whether or not the actor has any passives, so
     * check() and applyCosts() resolve the same amount.
     *
     * @param array<int, array{0: string, 1: int|string}> $value
     */
    private function passiveArrayCost(ActorInterface $actor, array $value): int
    {
        $passives = $actor->getPassives($actor->getId());
        $default = 0;
        foreach ($value as $item) {
            foreach ($passives as $passive) {
                if ($passive->getName() == $item[0]) {
                    return (int) $item[1];
                }
            }
            if ($item[0] == "none") {
                $default = (int) $item[1];
            }
        }

        return $default;
    }

    public function toRemove(): bool {
        return true;
    }
}