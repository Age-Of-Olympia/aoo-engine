<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use App\Action\Schema\FieldType;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class RemoveMalusOutcomeInstruction extends OutcomeInstruction implements HasParameterSchema
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('actorCarac', FieldType::TRAIT, 'Trait source', default: 0),
            new ParameterField('caracDivisor', FieldType::INT, 'Diviseur du trait', default: 1),
            new ParameterField('fixedMalus', FieldType::INT, 'Malus fixe', default: 0),
            new ParameterField('to', FieldType::ENUM, 'Appliquer à', default: 'target', options: ['actor' => 'Acteur', 'target' => 'Cible']),
        );
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {

        $params = $this->getParameters();
        $actorCarac = $params['actorCarac'] ?? 0;
        $divisor = (int) ($params['caracDivisor'] ?? 1);
        $hasCarac = !empty($actorCarac);

        $caracValue = $hasCarac ? (float) $actor->caracs->{$actorCarac} : 0.0;
        $malus = $this->computeMalusToRemove((int) ($params['fixedMalus'] ?? 0), $hasCarac, $caracValue, $divisor);

        $to = $params["to"] ?? "target";

        $subject = $this->resolveSubject($to, $actor, $target);
        if ($subject !== null) {
            $subject->put_malus(-$malus);
        }

        $outcomeMalusMessages = array();
        $outcomeMalusMessages[0] = 'Votre action retire '. $malus .' malus à ' . ($subject ?? $target)->data->name . '.';

        return new OutcomeResult(true, $outcomeMalusMessages, array());
    }

    public function computeMalusToRemove(int $fixedMalus, bool $hasCarac, float $caracValue, int $divisor): int
    {
        $malus = $fixedMalus;

        if ($hasCarac) {
            $malus = (int) floor($caracValue / $divisor);
        }

        return $malus;
    }

}
