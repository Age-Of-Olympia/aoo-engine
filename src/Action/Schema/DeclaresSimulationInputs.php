<?php

namespace App\Action\Schema;

/**
 * Implemented by condition handlers and outcome instructions that read actor /
 * target state, so the simulator's form can be derived from the action's actual
 * conditions + outcomes (same incremental-adoption model as HasParameterSchema).
 */
interface DeclaresSimulationInputs
{
    /**
     * @param array<string, mixed> $params the condition/outcome's stored parameters
     * @return list<SimulationField>
     */
    public static function simulationInputs(array $params): array;
}
