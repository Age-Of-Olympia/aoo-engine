<?php
namespace App\Action\Condition;

use App\Action\Condition\MinimumDistanceCondition;
use App\Action\Condition\ComputeCondition;
use App\Entity\ActionCondition;

class ConditionRegistry
{
    /** @var array<string, ConditionInterface> */
    private array $conditions = [];

    public function __construct()
    {
        // For each known Condition Type, we store an instance:
        $this->conditions = [
            'RequiresDistance'    => new RequiresDistanceCondition(), // should include wall check ?
            'RequiresTraitValue' => new RequiresTraitValueCondition(), // number of action, maximum life ?
            // 'NoBerserk' => new NoBerserkCondition(),
            'ForbidIfHasEffect'   => new ForbidIfHasEffectCondition(),
            'MinimumDistance' => new MinimumDistanceCondition(),
            
            
            'Compute' => new ComputeCondition(), // include equipment effect ?
            //'RequiresCaracValue'  => new RequiresCaracValueCondition(),
            // etc...
        ];
    }

    public function getCondition(string $type): ?ConditionInterface
    {
        return $this->conditions[$type] ?? null;
    }
}
