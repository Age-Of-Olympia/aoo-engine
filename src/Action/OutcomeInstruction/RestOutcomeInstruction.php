<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use App\Interface\DeclaresSimulationInputsInterface;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;
use App\Action\Schema\SimulationField;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class RestOutcomeInstruction extends OutcomeInstruction implements HasParameterSchemaInterface, DeclaresSimulationInputsInterface
{
    /**
     * Recovery is recup PV = remaining(a) × R / 4, recup PM = remaining(a) × RM
     * / 4, malus = remaining(mvt) / 3 — fixed reads, no parameters. Surface every
     * input so the simulated rest matches the game.
     */
    public static function simulationInputs(array $params): array
    {
        return [
            new SimulationField(SimulationField::KIND_TRAIT, SimulationField::SIDE_ACTOR, 'r', 'Acteur — r'),
            new SimulationField(SimulationField::KIND_TRAIT, SimulationField::SIDE_ACTOR, 'rm', 'Acteur — rm'),
            new SimulationField(SimulationField::KIND_REMAINING, SimulationField::SIDE_ACTOR, 'a', 'Acteur — a'),
            new SimulationField(SimulationField::KIND_REMAINING, SimulationField::SIDE_ACTOR, 'mvt', 'Acteur — mvt'),
        ];
    }

    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {
        
        $actor->get_caracs();

        $bonusPV = 0.0;
        $bonusPM = 0.0;
        $bonusMalus = 0.0;

        foreach ($conditionObject->getActorPassives() as $actorPassive) {
            $passiveName = $actorPassive->getName();

            if (!$actor->playerPassiveService->checkPassiveConditionsByPlayerById($actor, $actorPassive, $conditionObject)) {
                continue;
            }

            $traitsArray = $actorPassive->getTraits();
            $trait = $traitsArray[0] ?? null;
            $passiveValue = $actorPassive->getValue();

            if (!$trait || empty($passiveValue) || !isset($actor->caracs->{$trait})) {
                continue;
            }

            $statValue = $actor->caracs->{$trait};
            $bonusRatio = $statValue / $passiveValue;

            if(in_array($passiveName, ["meditation_arcanique", "meditation_somatique", "recuperation_runique"], true)){
                $bonusPM += $bonusRatio;
            } elseif (in_array($passiveName, ["recuperation_arcanique", "recuperation_somatique"], true)) {
                $bonusPV += $bonusRatio;
            } elseif ($passiveName === "retablissement_rapide") {
                $bonusMalus += $bonusRatio;
            }
        }

        $recupPV = floor($actor->getRemaining("a")*$actor->caracs->r/4 + $bonusPV);
        $recupPM = floor($actor->getRemaining("a")*$actor->caracs->rm/4 + $bonusPM);
        $recupMalus = floor($actor->getRemaining("mvt")/3 + $bonusMalus);

        $actor->putBonus(array('pv'=>$recupPV));
        $actor->putBonus(array('pm'=>$recupPM));
        $actor->put_malus(-$recupMalus);

        $outcomeMalusMessages = array();
        $outcomeMalusMessages[] = 'Votre repos vous retire '. $recupMalus .' malus.';
        $outcomeMalusMessages[] = 'Votre repos vous rend '. $recupPV .' PV.';
        $outcomeMalusMessages[] = 'Votre repos vous rend '. $recupPM .' PM.';

        return new OutcomeResult(true, $outcomeMalusMessages, $outcomeMalusMessages);

        }

}
