<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;

class NoBerserkCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $result = new ConditionResult(true, array(), array());

        if($actor->data->antiBerserkTime > time()) {
            $timeLeft = intval(($actor->data->antiBerserkTime - time()) / 60);

            $errorMessage[0] = '
            <font color="red">Mesure anti-Berserk!</font><br />
            Prochaine Action possible dans :<br />
            '.$this->convertToHoursMins($timeLeft, '%02d heures et %02d minutes.');
            // Refusal: its config row is blocking, so nothing is paid.
            $result = new ConditionResult(false, array(), $errorMessage);
        }

        return $result;
    }

    private function convertToHoursMins($time, $format = '%02d:%02d'): string {
        if ($time < 1) {
            return "Imminent !";
        }
        $hours = floor($time / 60);
        $minutes = floor($time % 60);
        return sprintf($format, $hours, $minutes);
    }

}