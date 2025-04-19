<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;

//add enum to display correctly the weapon type names (melee, distance, multipurpose, etc)

class RequiresWeaponCraftedWithCondition extends BaseCondition
{
    private ?string $errorMessage = null;

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {
        $result = new ConditionResult(true, array(), array());
        $params = $condition->getParameters(); // e.g. { "craftedWith": ["bois"], "location":["main1"] }  
        $craftedWithArray = $params['craftedWith'] ?? array();
        $locationArray = $params['location'] ?? array();
        $weaponTypeOk = true;
        $weaponCraftTypesKo = array();
        foreach ($locationArray as $location) {
            foreach ($craftedWithArray as $craftedWith) {
                $item = $actor->emplacements->{$location};
                if ($item->is_crafted_with($craftedWith)) {
                    $weaponTypeOk = true;
                    break 2;
                } else {
                    $weaponTypeOk = false;
                    array_push($weaponCraftTypesKo, $craftedWith);
                }
            }
        }

        if (!$weaponTypeOk) {
            $errorMessage[0] = 'Vous n\'êtes pas équipé d\'une arme fabriquée en '. join("/",$weaponCraftTypesKo). '.';
            $result = new ConditionResult(false, array(), $errorMessage);
        }
        
        return $result;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
