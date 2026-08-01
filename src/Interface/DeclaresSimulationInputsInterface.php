<?php

namespace App\Interface;

use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\SimulationField;

/**
 * Implemented by condition handlers and outcome instructions that read actor /
 * target state, so the simulator's form can be derived from the action's actual
 * conditions + outcomes (same incremental-adoption model as HasParameterSchemaInterface).
 */
interface DeclaresSimulationInputsInterface
{
    /**
     * @param array<string, mixed> $params the condition/outcome's stored parameters
     * @return list<SimulationField>
     */
    public static function simulationInputs(array $params): array;
}
