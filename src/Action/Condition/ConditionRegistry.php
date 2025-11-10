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
            'ForbidOnEquipedObjectStatus' => new ForbidOnEquipedObjectStatusCondition(),
            'RequiresAmmo' => new RequiresAmmoCondition(),
            'RequiresResource' => new RequiresResourceCondition(),
            
            'Compute' => new ComputeCondition(),
            'ComputePure' => new ComputePureCondition(), // Jet pur, sans prise en compte des Malus ou des Effets
            'MeleeCompute' => new MeleeComputeCondition(), // include equipment effect ?
            'MeleePureCompute' => new MeleePureComputeCondition(), 
            'DistanceCompute' => new DistanceComputeCondition(), 
            'DistancePureCompute' => new DistancePureComputeCondition(),
            'SpellCompute' => new SpellComputeCondition(),
            'SpellPureCompute' => new SpellPureComputeCondition(),
            'TechniqueCompute' => new TechniqueComputeCondition(),
            'TechniquePureCompute' => new TechniquePureComputeCondition(),

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
