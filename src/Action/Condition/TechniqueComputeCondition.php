<?php
namespace App\Action\Condition;

use App\Interface\ActorInterface;

class TechniqueComputeCondition extends ComputeCondition
{
    protected string $throwName = "La technique";

    /** 4 per cell beyond the first, lowered by the actor's 'seuil' passives (retrait). */
    protected function getDistanceTreshold(ActorInterface $actor) : int {
        return 4 * ($this->distance - 1) - $actor->traitBonus('seuil');
    }

    
}