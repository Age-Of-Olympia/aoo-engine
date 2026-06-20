<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class RemoveMalusOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {

        $params = $this->getParameters();
        $actorCarac = $params['actorCarac'] ?? 0;
        $divisor = (int) ($params['caracDivisor'] ?? 1);
        $hasCarac = !empty($actorCarac);

        $caracValue = $hasCarac ? (float) $actor->caracs->{$actorCarac} : 0.0;
        $malus = $this->computeMalusToRemove((int) ($params['fixedMalus'] ?? 0), $hasCarac, $caracValue, $divisor);

        $to = $param["to"] ?? "target";

        if ($to == "target") {
            $target->put_malus(-$malus);
        } else if ($to == "actor") {
            $actor->put_malus(-$malus);
        }

        $outcomeMalusMessages = array();
        $outcomeMalusMessages[0] = 'Votre action retire '. $malus .' malus à ' . $target->data->name . '.';

        return new OutcomeResult(true, $outcomeMalusMessages, $outcomeMalusMessages);
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
