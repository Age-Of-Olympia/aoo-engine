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
        $result = new ConditionResult(true, array(), array());
        $params = $condition->getParameters(); // e.g. { "type": ["melee"] } { "type": ["tir","jet"] } { "type": ["bouclier"], "location": ["main2"] }
        $weaponTypes = $params['type'] ?? array();
        $locationArray = $params['location'] ?? ['main1'];
        $weaponTypeOk = false;
        $weaponTypesKo = array();
        foreach ($locationArray as $location) {
            if (!isset($actor->emplacements->{$location}) || $actor->emplacements->{$location} === null) {
                continue;
            }
            foreach ($weaponTypes as $weaponType) {
                if ($actor->emplacements->{$location}->data->subtype == $weaponType) {
                    $weaponTypeOk = true;
                    break 2;
                } else {
                    array_push($weaponTypesKo, $weaponType);
                }
            }
        }

        if (!$weaponTypeOk) {
            $errorMessage[0] = 'Vous n\'êtes pas équipé d\'une arme de type '. join("/",$weaponTypesKo). '.';
            $result = new ConditionResult(false, array(), $errorMessage);
        }
        

        return $result;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
