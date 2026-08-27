<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use App\Interface\DeclaresSimulationInputsInterface;
use App\Action\Schema\SimulationField;
use Classes\Dice;

class BuffComputeCondition extends ComputeCondition implements DeclaresSimulationInputsInterface
{
    protected string $throwName = "Le sort de soutien";

    /**
     * A buff resolves on the actor's roll alone — computeTarget is a no-op and the
     * threshold is 6 + 6×level, not a contested roll — so the configured
     * targetRollType reads nothing. Declare only the actor trait; the parent would
     * otherwise surface a dead "Cible" field in the simulator.
     */
    public static function simulationInputs(array $params): array
    {
        $fields = [];
        foreach (explode('/', (string) ($params['actorRollType'] ?? '')) as $trait) {
            if ($trait !== '') {
                $fields[] = new SimulationField(SimulationField::KIND_TRAIT, SimulationField::SIDE_ACTOR, $trait, 'Acteur — ' . $trait);
            }
        }

        return $fields;
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $preConditionResult = parent::check($actor, $target, $condition, $conditionObject);
        if (!$preConditionResult->isSuccess()) {
            return $preConditionResult;
        }

        if (!$target) {
            return new ConditionResult(false, ["Aucune cible spécifiée."], []);
        }

        $params = $condition->getParameters();
        $this->actorRollTrait = $params['actorRollType'] ?? null;
        $conditionObject->setActorRollBonus($params['actorRollBonus'] ?? 0);
        $conditionObject->setActorRollTrait($params['actorRollType'] ?? 0);
        $conditionObject->setActorAdvantage($params['actorAdvantage'] ?? false);
        $conditionObject->setActorDisadvantage($params['actorDisadvantage'] ?? false);

        foreach ($conditionObject->getActorPassives() as $actorPassive) {
            if (in_array($this->actorRollTrait, $actorPassive->getTraits()) && ($actorPassive->getType() == "buff")) {
                if($actor->getPlayerPassiveService()->checkPassiveConditionsByPlayerById($actor,$actorPassive,$conditionObject)){
                    $conditionObject->addActorRollBonus($actor->getPlayerPassiveService()->getComputedValueByPlayerIdById($actor->id,$actorPassive->getId()));
                }
            }
        }

        if (!$target) {
            $errorMessages[0] = "Aucune cible n'a été spécifiée.";
            return new ConditionResult(success: false, conditionSuccessMessages:$errorMessages, conditionFailureMessages:array());
        }

        $result = $this->computeAttack($actor, $conditionObject, $condition);

        return $result;
    }

    private function computeAttack(ActorInterface $actor, ConditionObject $conditionObject, ActionCondition $condition): ConditionResult 
    {
        $success = false;
        $dice = new Dice(3);

        list($actorRoll, $actorTotal, $actorTxt) = $this->computeActor($actor, $dice, $conditionObject);
        $conditionDetailsSuccess = [$actorTxt];

        $threshold = $this->getLevelTreshold($condition);
        $checkBuffThreshold = $actorTotal >= $threshold;

        if(!AUTO_FAIL && $checkBuffThreshold)
        {
            $success = true;
        }

        $conditionDetailsFailure = [];
        if (!$success) {
            foreach ($conditionDetailsSuccess as $detail) {
                $conditionDetailsFailure[] = $detail;
            }

            if (!$checkBuffThreshold) {
                $conditionDetailsFailure[] = $this->throwName." n'arrive pas à canaliser son sort ! Il fallait un jet supérieur à ". $this->getLevelTreshold($condition) . ".";
            }
        }

        return new ConditionResult($success,$conditionDetailsSuccess,$conditionDetailsFailure);
    }

    protected function computeTarget($target, $dice, $targetRollBonus)
    {
        
        return array([], 0, "Impossible de calculer, erreur de paramétrage.");
    }

    protected function getLevelTreshold(ActionCondition $condition) : int {

        // Calcul du seuil des buffs à 6 + 6xLevel
        return 6+(6*$condition->getAction()->getLevel());
    }
    
}