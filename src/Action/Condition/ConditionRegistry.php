<?php
namespace App\Action\Condition;

use App\Condition\MinimumDistanceCondition;
use App\Entity\ActionCondition;

class ConditionRegistry
{
    /** @var array<string, ConditionInterface> */
    private array $conditions = [];

    public function __construct()
    {
        // For each known Condition Type, we store an instance:
        $this->conditions = [
            'RequiresDistance'    => new RequiresDistanceCondition(),
            'ForbidIfHasEffect'   => new ForbidIfHasEffectCondition(),
            'MinimumDistanceCondition' => new MinimumDistanceCondition(),
            //'RequiresCaracValue'  => new RequiresCaracValueCondition(),
            // etc...
        ];
    }

    public function getCondition(string $type): ?ConditionInterface
    {
        return $this->conditions[$type] ?? null;
    }
}
