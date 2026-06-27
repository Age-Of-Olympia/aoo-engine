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
use App\Action\Schema\SchemaSimulationInputs;
use App\Action\Schema\SimulationField;
use Classes\Dice;
use Classes\View;

class ComputeCondition extends AbstractComputeCondition implements HasParameterSchema
{
    public static function parameterSchema(): ParameterSchema
    {
        // The simulator derives its caracs from this schema (SchemaSimulationInputs):
        // actorRollType on the actor, targetRollType on the target. The physical
        // subclasses (melee/distance) instead read a fixed cc/agi defense — see
        // physicalDefenseSimulationInputs() — so they declare inputs by hand.
        return new ParameterSchema(
            new ParameterField('actorRollType', FieldType::TRAIT, "Trait du jet de l'acteur", required: true),
            new ParameterField('targetRollType', FieldType::TRAIT_OR_INT, 'Trait du jet de la cible', required: true, side: 'target'),
            new ParameterField('actorRollBonus', FieldType::INT, 'Bonus au jet acteur', default: 0),
            new ParameterField('targetRollBonus', FieldType::INT, 'Bonus au jet cible', default: 0),
            new ParameterField('actorAdvantage', FieldType::BOOL, 'Avantage acteur', default: false),
            new ParameterField('targetAdvantage', FieldType::BOOL, 'Avantage cible', default: false),
            new ParameterField('actorDisadvantage', FieldType::BOOL, 'Désavantage acteur', default: false),
            new ParameterField('targetDisadvantage', FieldType::BOOL, 'Désavantage cible', default: false),
        );
    }

    /**
     * Inputs for the physical computes (melee, distance): the actor's roll trait
     * comes from the schema, but the defense is a fixed formula over the target's
     * cc and agi (see each computeTarget), independent of targetRollType — so both
     * caracs are always offered.
     *
     * @param array<string, mixed> $params
     * @return list<SimulationField>
     */
    protected static function physicalDefenseSimulationInputs(array $params): array
    {
        $fields = array_values(array_filter(
            SchemaSimulationInputs::derive(static::parameterSchema(), $params),
            static fn (SimulationField $field): bool => $field->side === SimulationField::SIDE_ACTOR,
        ));
        $fields[] = new SimulationField(SimulationField::KIND_TRAIT, SimulationField::SIDE_TARGET, 'cc', 'Cible — cc');
        $fields[] = new SimulationField(SimulationField::KIND_TRAIT, SimulationField::SIDE_TARGET, 'agi', 'Cible — agi');

        return $fields;
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

}
