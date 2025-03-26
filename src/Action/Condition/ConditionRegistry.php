<?php
namespace App\Action\Condition;

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
            'RequiresResource' => new RequiresResourceCondition(),
            
            'MeleeCompute' => new MeleeComputeCondition(), // include equipment effect ?
            'DistanceCompute' => new DistanceComputeCondition(),
            'SpellCompute' => new SpellComputeCondition(),
            'TechniqueCompute' => new TechniqueComputeCondition(),

            'Dodge' => new DodgeCondition(),
            'RequiresGodAffiliation' => new RequiresGodAffiliationCondition()
        ];
    }

    public function getCondition(string $type): ?ConditionInterface
    {
        return $this->conditions[$type] ?? null;
    }
}
