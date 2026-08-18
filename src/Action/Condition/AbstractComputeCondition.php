<?php

namespace App\Action\Condition;

use App\Action\Combat\CombatResolver;
use App\Action\OutcomeInstruction\MalusOutcomeInstruction;
use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use Classes\Dice;
use Classes\View;

/**
 * Shared engine for the opposed-roll "compute" conditions. It owns the parts
 * that were duplicated verbatim between ComputeCondition (RollDetailView output)
 * and ComputePureCondition ("Jet pur" output): parameter unpacking, passive
 * modifiers, distance, the opposed-roll resolution and the malus-on-miss.
 *
 * Subclasses provide only the roll math + tooltip via computeActor()/
 * computeTarget(); distance behaviour is overridable for ranged variants.
 */
abstract class AbstractComputeCondition extends BaseCondition
{
    protected int $distance;
    protected string $throwName = "Le tir";
    protected string $actorRollTrait;
    protected string $targetRollTrait;
    protected ?Dice $dice = null;

    public function __construct(?Dice $dice = null)
    {
        $this->dice = $dice;
        // Dodge/NoBerserk preconditions used to be pushed here; they are now
        // data-driven, resolved per condition type by ConditionPreconditionResolver
        // and run by ActionExecutorService.
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
        $this->targetRollTrait = $params['targetRollType'] ?? null;
        $conditionObject->setActorRollBonus($params['actorRollBonus'] ?? 0);
        $conditionObject->setTargetRollBonus($params['targetRollBonus'] ?? 0);
        $conditionObject->setActorRollTrait($params['actorRollType'] ?? 0);
        $conditionObject->setTargetRollTrait($params['targetRollType'] ?? 0);
        $conditionObject->setActorAdvantage($params['actorAdvantage'] ?? false);
        $conditionObject->setTargetAdvantage($params['targetAdvantage'] ?? false);
        $conditionObject->setActorDisadvantage($params['actorDisadvantage'] ?? false);
        $conditionObject->setTargetDisadvantage($params['targetDisadvantage'] ?? false);

        $this->applyActorPassives($actor, $conditionObject);
        $this->applyTargetPassives($target, $conditionObject);

        $this->distance = View::get_distance_to_entity($actor->getCoords(), $target->getId(), $target->getCoords());

        $result = $this->computeAttack($actor, $target, $conditionObject);

        if (!$result->isSuccess()) {
            $condition->getAction()->addAutomaticOutcomeInstruction(new MalusOutcomeInstruction());
        }

        return $result;
    }

    private function applyActorPassives(ActorInterface $actor, ConditionObject $conditionObject): void
    {
        foreach ($actor->playerPassiveService->getPassivesByPlayerId($actor->getId()) as $actorPassive) {
            if (in_array($this->actorRollTrait, $actorPassive->getTraits()) && ($actorPassive->getType() == "att" || $actorPassive->getType() == "mixte")) {
                if ($actor->playerPassiveService->checkPassiveConditionsByPlayerById($actor, $actorPassive, $conditionObject)) {
                    if ($actorPassive->getCarac() == "advantage") {
                        $conditionObject->setActorAdvantage(true);
                    } else {
                        $conditionObject->addActorRollBonus($actor->playerPassiveService->getComputedValueByPlayerIdById($actor->id, $actorPassive->getId()));
                    }
                }
            }
        }
    }

    private function applyTargetPassives(ActorInterface $target, ConditionObject $conditionObject): void
    {
        foreach ($target->playerPassiveService->getPassivesByPlayerId($target->getId()) as $targetPassive) {
            if (in_array($this->targetRollTrait, $targetPassive->getTraits()) && ($targetPassive->getType() == "def" || $targetPassive->getType() == "mixte")) {
                if ($target->playerPassiveService->checkPassiveConditionsByPlayerById($target, $targetPassive, $conditionObject)) {
                    if ($targetPassive->getCarac() == "advantage") {
                        $conditionObject->setTargetAdvantage(true);
                    } else {
                        $conditionObject->addTargetRollBonus($target->playerPassiveService->getComputedValueByPlayerIdById($target->id, $targetPassive->getId()));
                    }
                }
            }
        }
    }

    private function computeAttack(ActorInterface $actor, ?ActorInterface $target, ConditionObject $conditionObject): ConditionResult
    {
        $dice = $this->dice ?? new Dice(3);

        list($actorRoll, $actorTotal, $actorTxt) = $this->computeActor($actor, $dice, $conditionObject);
        $conditionDetailsSuccess[0] = $actorTxt;
        list($targetRoll, $targetTotal, $targetTxt) = $this->computeTarget($target, $dice, $conditionObject);
        // Le jet adverse existe mécaniquement même contre une structure,
        // mais un bâtiment « n'esquive » pas aux yeux du joueur : sa
        // ligne « Jet … » n'est pas affichée.
        if (\App\Enum\EntityCategory::fromPlayerType($target->data->player_type ?? 'real') !== \App\Enum\EntityCategory::Structure) {
            $conditionDetailsSuccess[1] = $targetTxt;
        }

        $checkAboveDistance = $this->checkDistanceCondition($actorTotal);

        $rollResult = (new CombatResolver())->resolve($actorTotal, $targetTotal, $checkAboveDistance);
        $success = !AUTO_FAIL && $rollResult->hit;

        $conditionDetailsFailure = array();
        if (!$success) {
            $conditionDetailsFailure = $conditionDetailsSuccess;
            if (!$checkAboveDistance) {
                $conditionDetailsFailure[] = $this->throwName." n'atteint pas sa cible ! Il fallait un jet supérieur à ". $this->getDistanceTreshold() . ".";
            }
        }

        return new ConditionResult($success, $conditionDetailsSuccess, $conditionDetailsFailure);
    }

    /** @return array [rolls, total, tooltip html] */
    abstract protected function computeActor($actor, $dice, $conditionObject);

    /** @return array [rolls, total, tooltip html] */
    abstract protected function computeTarget($target, $dice, $conditionObject);

    protected function getDistanceTreshold(): int
    {
        return 0;
    }

    protected function checkDistanceCondition(int $actorTotal): bool
    {
        return true;
    }

    protected function getDistanceMalus(): int
    {
        return 0;
    }
    
}
