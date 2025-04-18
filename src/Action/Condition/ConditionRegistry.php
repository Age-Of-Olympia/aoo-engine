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
            'Plan' => new PlanCondition(),
            'Obstacle' => new ObstacleCondition(),
            'RequiresDistance'    => new RequiresDistanceCondition(),
            'RequiresTraitValue' => new RequiresTraitValueCondition(),
            'RequiresWeaponType' => new RequiresWeaponTypeCondition(),
            'RequiresWeaponCraftedWith' => new RequiresWeaponCraftedWithCondition(),
            'NoBerserk' => new NoBerserkCondition(),
            'ForbidIfHasEffect'   => new ForbidIfHasEffectCondition(),
            'RequiresAmmo' => new RequiresAmmoCondition(),
            'RequiresResource' => new RequiresResourceCondition(),
            
            'Compute' => new ComputeCondition(),
            'MeleeCompute' => new MeleeComputeCondition(), // include equipment effect ?
            'DistanceCompute' => new DistanceComputeCondition(),
            'SpellCompute' => new SpellComputeCondition(),
            'TechniqueCompute' => new TechniqueComputeCondition(),

            'Dodge' => new DodgeCondition(),
            'RequiresGodAffiliation' => new RequiresGodAffiliationCondition(),
            'AntiSpell' => new AntiSpellCondition(),

            'Option' => new OptionCondition(),
        ];
    }

    public function getCondition(string $type): ?ConditionInterface
    {
        return $this->conditions[$type] ?? null;
    }
}
