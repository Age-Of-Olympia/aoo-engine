<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;

//add enum to display correctly the weapon type names (melee, distance, multipurpose, etc)

class RequiresWeaponTypeCondition extends BaseCondition
{
    private ?string $errorMessage = null;

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        $result = new ConditionResult(true);
        $params = $condition->getParameters(); // e.g. { "type": "melee" }
        $weaponType = $params['type'] ?? "";
        if($actor->emplacements->main1->data->subtype != $weaponType){
            $errorMessage[0] = 'Vous n\'avez pas une arme de '. $weaponType;
            $result = new ConditionResult(false, null, $errorMessage);
        }

        return $result;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
