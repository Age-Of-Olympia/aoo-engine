<?php
namespace App\Action\Condition;

use App\Action\Condition\MinimumDistanceCondition;
use App\Action\Condition\ComputeCondition;
use App\Interface\ConditionInterface;

class ConditionRegistry
{
    /** @var array<string, ConditionInterface> */
    private array $conditions = [];

    public function __construct()
    {
        // For each known Condition Type, we store an instance:
        $this->conditions = [
            'RequiresDistance'    => new RequiresDistanceCondition(), // should include wall check ? No : can be different from an action to another
            'RequiresTraitValue' => new RequiresTraitValueCondition(), 
            'RequiresWeaponType' => new RequiresWeaponTypeCondition(), 
            'NoBerserk' => new NoBerserkCondition(),
            'ForbidIfHasEffect'   => new ForbidIfHasEffectCondition(),
            'RequiresAmmo' => new RequiresAmmoCondition(),
            
            
            'Compute' => new ComputeCondition(), // include equipment effect ?
            // etc...
        ];
    }

    public function getCondition(string $type): ?ConditionInterface
    {
        return $this->conditions[$type] ?? null;
    }
}
