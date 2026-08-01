<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use App\Action\Schema\FieldType;
use App\Action\Schema\HasParameterSchemaInterface;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class ManaLossOutcomeInstruction extends OutcomeInstruction implements HasParameterSchemaInterface
{
    // The simulator derives its input from this schema: `value` is TRAIT_OR_INT,
    // so lossType=carac (value="m") surfaces the actor's M, while fixed/lifeloss/
    // difference (numeric or absent value) surface nothing — no custom code needed.
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('lossType', FieldType::ENUM, 'Type de perte', default: 'fixed', options: [
                'carac' => 'Depuis un trait',
                'fixed' => 'Montant fixe',
                'lifeloss' => 'Depuis les dégâts',
                'difference' => 'Différence de jet',
            ]),
            new ParameterField('value', FieldType::TRAIT_OR_INT, 'Valeur / trait', default: 0, help: 'Trait si lossType=carac, entier si fixed'),
            new ParameterField('typeDivisor', FieldType::INT, 'Diviseur', default: 1),
        );
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {

        // e.g. { "lossType": "carac", "value":"m", "typeDivisor":2 }
        // e.g. { "lossType": "fixed", "value":5 }
        // e.g. { "lossType": "lifeloss" }
        // e.g. { "lossType": "difference" }
        $lossType = $this->getParameters()['lossType'] ?? '';
        $value = $this->getParameters()['value'] ?? 0;
        $typeDivisor = $this->getParameters()['typeDivisor'] ?? 1;
        $outcomeSuccessMessages = array();
        $outcomeFailureMessages = array();
        $backfire = false;

        switch ($lossType) {
            case "carac":
                $manaloss = floor($actor->caracs->{$value} / $typeDivisor);
                break;
            case "fixed":
                $manaloss = $value;
                break;
            case "lifeloss":
                $manaloss = floor($conditionObject->getLifeloss() / $typeDivisor);
                break;
            case "difference":
                $difference = $this->computeManaLossDifference(
                    (int) $conditionObject->getActorRoll(),
                    (int) $conditionObject->getTargetRoll()
                );
                $manaloss = $difference['loss'];
                $backfire = $difference['backfire'];
                if($backfire){
                    $outcomeFailureMessages[sizeof($outcomeFailureMessages)] = 'Aïe... votre sort se retourne contre vous.';
                }
                break;
            default:
                $manaloss = 0;
        }

        $finalTarget = $backfire ? $actor : $target;
        $remainingPM = $finalTarget->getRemaining("pm");
        if($remainingPM < $manaloss){
            $spill = $this->computePmSpill((int) $manaloss, (int) $remainingPM);
            $lifeloss = $spill['pv'];
            $finalTarget->putBonus(array('pm'=>-$remainingPM));
            $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = 'Vous faites perdre ' . $remainingPM . ' PM à ' . $finalTarget->data->name . '.';
            $finalTarget->putBonus(array('pv'=>-$lifeloss));
            $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = $finalTarget->data->name . ' ne supporte pas l\'invasion psychique et perd ' . $lifeloss . ' PV.';
            $recoverMalus = $spill['malus'];
            $finalTarget->put_malus(-$recoverMalus);
            $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = $finalTarget->data->name . ' récupère ' . $recoverMalus . ' Malus.';

            if($backfire){
                $outcomeFailureMessages[sizeof($outcomeFailureMessages)] = 'Vous perdez ' . $remainingPM . ' PM.';
                $outcomeFailureMessages[sizeof($outcomeFailureMessages)] = 'Vous ne supportez pas l\'invasion psychique et perdez ' . $lifeloss . ' PV.';
                $outcomeFailureMessages[sizeof($outcomeFailureMessages)] = 'Vous récupèrez ' . $recoverMalus . ' Malus.';  
            }
        }
        else{
            $finalTarget->putBonus(array('pm'=>-$manaloss));
            $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = 'Vous faites perdre ' . $manaloss . ' PM à ' . $finalTarget->data->name . '.';
            if($backfire){
                $outcomeFailureMessages[sizeof($outcomeFailureMessages)] = 'Vous perdez ' . $remainingPM . ' PM.';
            }
        }

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages:$outcomeFailureMessages);
    }

    /**
     * @return array{loss: int, backfire: bool}
     */
    public function computeManaLossDifference(int $actorRoll, int $targetRoll): array
    {
        $loss = $actorRoll - $targetRoll;

        return $loss < 0
            ? ['loss' => abs($loss), 'backfire' => true]
            : ['loss' => $loss, 'backfire' => false];
    }

    /**
     * @return array{pv: int, malus: int}
     */
    public function computePmSpill(int $manaloss, int $remainingPM): array
    {
        $pv = (int) floor(($manaloss - $remainingPM) / 2);

        return ['pv' => $pv, 'malus' => (int) floor($pv / 2)];
    }

}

