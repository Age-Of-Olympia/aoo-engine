<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class MalusOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {

        $baseRoll = random_int(1, 3);
        $params = $this->getParameters();

        $difference = 0;
        $malusText = null;
        if (!empty($params['rollDivisor'])) {
            $difference = $this->computeRollDifference(
                (int) $conditionObject->getActorRoll(),
                (int) $conditionObject->getTargetRoll(),
                (int) $params['rollDivisor']
            );
            $malusText = $baseRoll . ' + ' . $difference . ' (Jet)';
        }

        $to = $param["to"] ?? "target";

        $malusTot = $this->computeMalusTotal($baseRoll, $difference, false);
        $subject = $this->resolveSubject($to, $actor, $target);
        if ($subject !== null) {
            $inepuisable = $subject->playerPassiveService->hasPassiveByPlayerIdByName($subject->getId(), "inepuisable");
            $malusTot = $this->computeMalusTotal($baseRoll, $difference, $inepuisable);
            $subject->put_malus($malusTot);
        }

        $malusTotalTxt = $malusText !== null ? $malusText . ' = ' . $malusTot : $baseRoll;
        $outcomeMalusMessages = array();
        $outcomeMalusMessages[0] = 'Votre action inflige '. $malusTotalTxt .' malus à ' . $target->data->name . '.';

        return new OutcomeResult(true, $outcomeMalusMessages, $outcomeMalusMessages);
    }

    public function computeRollDifference(int $actorRoll, int $targetRoll, int $divisor): int
    {
        return max(0, (int) floor(($actorRoll - $targetRoll) / $divisor));
    }

    public function computeMalusTotal(int $baseRoll, int $difference, bool $inepuisable): int
    {
        $total = $baseRoll + $difference;

        return $inepuisable ? $total - 1 : $total;
    }
}