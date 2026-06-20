<?php
namespace App\Action\Condition;

use App\Action\OutcomeInstruction\MalusOutcomeInstruction;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use App\Action\Combat\CombatResolver;
use App\Action\Combat\RollDetail;
use App\Action\Combat\RollDetailView;
use App\Action\Schema\FieldType;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use Classes\Dice;
use Classes\View;

enum Roll: string
{
    case cc = "cc";
    case ct = "ct";
    case fm = "fm";
    case cc_agi = "cc_agi";
}

class ComputeCondition extends BaseCondition implements HasParameterSchema, \App\Action\Schema\DeclaresSimulationInputs
{
    protected int $distance;
    protected string $throwName = "Le tir";
    protected string $actorRollTrait;
    protected string $targetRollTrait;
    protected ?Dice $dice = null;


    public function __construct(?Dice $dice = null) {
        $this->dice = $dice;
        array_push($this->preConditions, new DodgeCondition());
        array_push($this->preConditions, new NoBerserkCondition());
    }

    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('actorRollType', FieldType::TRAIT, "Trait du jet de l'acteur", required: true),
            new ParameterField('targetRollType', FieldType::TRAIT_OR_INT, 'Trait du jet de la cible', required: true),
            new ParameterField('actorRollBonus', FieldType::INT, 'Bonus au jet acteur', default: 0),
            new ParameterField('targetRollBonus', FieldType::INT, 'Bonus au jet cible', default: 0),
            new ParameterField('actorAdvantage', FieldType::BOOL, 'Avantage acteur', default: false),
            new ParameterField('targetAdvantage', FieldType::BOOL, 'Avantage cible', default: false),
            new ParameterField('actorDisadvantage', FieldType::BOOL, 'Désavantage acteur', default: false),
            new ParameterField('targetDisadvantage', FieldType::BOOL, 'Désavantage cible', default: false),
        );
    }

    public static function simulationInputs(array $params): array
    {
        $fields = [];
        foreach (explode('/', (string) ($params['actorRollType'] ?? '')) as $trait) {
            if ($trait !== '') {
                $fields[] = new \App\Action\Schema\SimulationField('trait', 'actor', $trait, 'Acteur — ' . $trait);
            }
        }
        foreach (static::targetSimulationTraits($params) as $trait) {
            $fields[] = new \App\Action\Schema\SimulationField('trait', 'target', $trait, 'Cible — ' . $trait);
        }

        return $fields;
    }

    /**
     * Traits the target's defense roll reads. When the subclass implements a
     * per-type defense formula, its parameter names ARE the traits it reads
     * (SSOT — e.g. targetDefenseValue(int $cc, int $agi)). Otherwise the roll
     * uses the configured targetRollType.
     *
     * @param array<string, mixed> $params
     * @return list<string>
     */
    protected static function targetSimulationTraits(array $params): array
    {
        if (method_exists(static::class, 'targetDefenseValue')) {
            return array_map(
                static fn(\ReflectionParameter $parameter): string => $parameter->getName(),
                (new \ReflectionMethod(static::class, 'targetDefenseValue'))->getParameters(),
            );
        }

        $traits = [];
        foreach (explode('/', (string) ($params['targetRollType'] ?? '')) as $trait) {
            if ($trait !== '' && !is_numeric($trait)) {
                $traits[] = $trait;
            }
        }

        return $traits;
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

        $params = $condition->getParameters(); // e.g. { "max": 1 }
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
        $target->playerPassiveService->getPassivesByPlayerId($target->getId());

        foreach ($actor->playerPassiveService->getPassivesByPlayerId($actor->getId()) as $actorPassive) {
            if (in_array($this->actorRollTrait, $actorPassive->getTraits()) && ($actorPassive->getType() == "att" || $actorPassive->getType() == "mixte" )) {
                if($actor->playerPassiveService->checkPassiveConditionsByPlayerById($actor,$actorPassive,$conditionObject)){
                    if($actorPassive->getCarac() == "advantage"){
                        $conditionObject->setActorAdvantage(true);
                    }
                    else{
                    $conditionObject->addActorRollBonus($actor->playerPassiveService->getComputedValueByPlayerIdById($actor->id,$actorPassive->getId()));
                    }
                }
            }
        }

        foreach ($target->playerPassiveService->getPassivesByPlayerId($target->getId()) as $targetPassive) {
            if (in_array($this->targetRollTrait, $targetPassive->getTraits()) && ($targetPassive->getType() == "def" || $targetPassive->getType() == "mixte" )) {
                if($target->playerPassiveService->checkPassiveConditionsByPlayerById($target,$targetPassive,$conditionObject)){
                    if($targetPassive->getCarac() == "advantage"){
                        $conditionObject->setTargetAdvantage(true);
                    }
                    else{
                        $conditionObject->addTargetRollBonus($target->playerPassiveService->getComputedValueByPlayerIdById($target->id,$targetPassive->getName()));
                    }
                }
            }
        }

        if (!$target) {
            $errorMessages[0] = "Aucune cible n'a été spécifiée.";
            return new ConditionResult(success: false, conditionSuccessMessages:$errorMessages, conditionFailureMessages:array());
        }

        $this->distance = View::get_distance($actor->getCoords(), $target->getCoords());

        $result = $this->computeAttack($actor, $target, $conditionObject);

        if (!$result->isSuccess()) {
            $condition->getAction()->addAutomaticOutcomeInstruction(new MalusOutcomeInstruction());
        }

        return $result;
    }

    private function computeAttack(ActorInterface $actor, ?ActorInterface $target, ConditionObject $conditionObject): ConditionResult 
    {
        $success = false;
        $dice = $this->dice ?? new Dice(3);

        list($actorRoll, $actorTotal, $actorTxt) = $this->computeActor($actor, $dice, $conditionObject);
        $conditionDetailsSuccess[0] = $actorTxt;
        list($targetRoll, $targetTotal, $targetTxt) = $this->computeTarget($target, $dice, $conditionObject);
        $conditionDetailsSuccess[1] = $targetTxt;
       
        $checkAboveDistance = $this->checkDistanceCondition($actorTotal);

        $rollResult = (new CombatResolver())->resolve($actorTotal, $targetTotal, $checkAboveDistance);
        $success = !AUTO_FAIL && $rollResult->hit;

        $conditionDetailsFailure = array();
        if (!$success) {
            $conditionDetailsFailure[0] = $conditionDetailsSuccess[0];
            $conditionDetailsFailure[1] = $conditionDetailsSuccess[1];
            if (!$checkAboveDistance) {
                $conditionDetailsFailure[2] = $this->throwName." n'atteint pas sa cible ! Il fallait un jet supérieur à ". $this->getDistanceTreshold() . ".";
            }
        }

        return new ConditionResult($success,$conditionDetailsSuccess,$conditionDetailsFailure);
    }

    protected function computeActor($actor, $dice, $conditionObject)
    {
        $actorRoll = (new CombatResolver($dice))->roll(
            (int) $actor->caracs->{$this->actorRollTrait},
            (bool) $conditionObject->getActorAdvantage(),
            (bool) $conditionObject->getActorDisadvantage()
        );

        $bonus = (int) $conditionObject->getActorRollBonus();
        $dexterite = (int) ($actor->getEffectValue("dexterite") ?: 0);
        $maladresse = (int) ($actor->getEffectValue("maladresse") ?: 0);
        $distanceMalus = $this->getDistanceMalus();
        $total = array_sum($actorRoll) + $bonus + $dexterite - $maladresse - $distanceMalus;

        $detail = new RollDetail(
            name: $actor->data->name,
            rollSum: array_sum($actorRoll),
            bonus: $bonus,
            positiveEffect: $dexterite,
            negativeEffect: $maladresse,
            distanceMalus: $distanceMalus,
            total: $total,
        );

        $conditionObject->setActorRoll($total);

        return array($actorRoll, $total, (new RollDetailView())->renderActor($detail));
    }

    protected function computeTarget($target, $dice, $conditionObject)
    {
        $targetRollBonus = $conditionObject->getTargetRollBonus();
        $traitsArray = explode('/', $this->targetRollTrait);
        if (sizeof($traitsArray) == 1) {
            $targetRollTraitValue = $target->caracs->{$this->targetRollTrait};
        } else if (sizeof($traitsArray) == 2) {
            $option1 = $target->caracs->{$traitsArray[0]};
            $option2 = $target->caracs->{$traitsArray[1]};
            $targetRollTraitValue = max($option1, $option2);
        } else {
            return array(0, 0, "Impossible de calculer, erreur de paramétrage.");
        }
        
        $targetRoll = (new CombatResolver($dice))->roll(
            (int) $targetRollTraitValue,
            (bool) $conditionObject->getTargetAdvantage(),
            (bool) $conditionObject->getTargetDisadvantage()
        );
        $bonus = (int) $conditionObject->getTargetRollBonus();
        $protection = (int) ($target->getEffectValue("protection") ?: 0);
        $vulnerabilite = (int) ($target->getEffectValue("vulnerabilite") ?: 0);
        $malus = (int) $target->data->malus;
        $total = array_sum($targetRoll) - $malus + $bonus + $protection - $vulnerabilite;

        $detail = new RollDetail(
            name: $target->data->name,
            rollSum: array_sum($targetRoll),
            bonus: $bonus,
            positiveEffect: $protection,
            negativeEffect: $vulnerabilite,
            malus: $malus,
            total: $total,
        );

        $conditionObject->setTargetRoll($total);

        return array($targetRoll, $total, (new RollDetailView())->renderTarget($detail));
    }

    protected function getDistanceTreshold() : int {
        return 0;
    }

    protected function checkDistanceCondition(int $actorTotal): bool {
        return true;
    }
    
    protected function getDistanceMalus(): int {
        return 0;
    }

}