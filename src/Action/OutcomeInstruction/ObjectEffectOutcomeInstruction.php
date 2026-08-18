<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class ObjectEffectOutcomeInstruction extends OutcomeInstruction implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {

        // Item effects are now applied by ActionExecutorService::applyEquippedItemsEffects().
        return new OutcomeResult(true, outcomeSuccessMessages: array(), outcomeFailureMessages: array());
    }

}
