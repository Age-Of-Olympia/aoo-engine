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
        $params = $condition->getParameters(); // e.g. { "type": "melee" } { "type": "tir/jet" } 
        $type = $params['type'] ?? "";
        $weaponTypes = explode(",", $type);
        $weaponTypeOk = false;
        $weaponTypesKo = array();
        foreach ($weaponTypes as $weaponType) {
            if($actor->emplacements->main1->data->subtype == $weaponType){
                $weaponTypeOk = true;
                break;
            } else {
                array_push($weaponTypesKo, $weaponType);
            }
        }

        if (!$weaponTypeOk) {
            $errorMessage[0] = 'Vous n\'êtes pas équipé d\'une arme de '. join("/",$weaponTypesKo). '.';
            $result = new ConditionResult(false, null, $errorMessage);
        }
        

        return $result;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
