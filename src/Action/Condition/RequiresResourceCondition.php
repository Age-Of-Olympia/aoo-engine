<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Service\ResourceService;

class RequiresResourceCondition extends BaseCondition
{
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition): ConditionResult
    {

        $res = ResourceService::findResourcesAround($actor);
        
        if($res == 0){
            $errorMessages[0] = 'Il n\'y a rien par ici.';
            $result = new ConditionResult(false, array(), $errorMessages);
        } else {
            $result = new ConditionResult(true, array(), array(), null, null, $res);
        }

        return $result;
    }
}
