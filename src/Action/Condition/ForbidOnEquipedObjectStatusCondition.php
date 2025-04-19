<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use View;

class ForbidOnEquipedObjectStatusCondition extends BaseCondition
{
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        $result = new ConditionResult(true, array(), array());

        $params = $condition->getParameters();
        $location = $params["location"]??"main1";
        $itemToEnchant = $actor->emplacements->{$location};

        if($itemToEnchant->row->enchanted != 0){
            $result = new ConditionResult(false, ['Cet objet est déjà enchanté.'], array());
        }

        return $result;
    }

}
