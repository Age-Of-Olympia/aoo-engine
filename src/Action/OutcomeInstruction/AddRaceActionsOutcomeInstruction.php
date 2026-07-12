<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterSchema;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;


#[ORM\Entity]
class AddRaceActionsOutcomeInstruction extends OutcomeInstruction implements HasParameterSchema
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {

        (new \App\Service\PlayerActionsService())
            ->grantRaceStarterPack($actor->id, $actor->data->race);

        return new OutcomeResult(true, array(), array());
    }

}
