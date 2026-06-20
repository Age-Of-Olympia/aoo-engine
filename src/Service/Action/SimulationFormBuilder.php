<?php

namespace App\Service\Action;

use App\Action\Condition\ConditionRegistry;
use App\Action\Schema\DeclaresSimulationInputs;
use App\Action\Schema\SimulationField;
use App\Entity\Action;
use App\Service\OutcomeInstructionService;

/**
 * Derives the simulator's form fields from an action's actual conditions +
 * outcomes: it asks each one (that implements DeclaresSimulationInputs) which
 * actor/target state it reads, and unions the results. So the form always shows
 * exactly what THAT action needs — add a condition, its inputs appear.
 */
final class SimulationFormBuilder
{
    private ConditionRegistry $registry;
    private OutcomeInstructionService $instructionService;

    public function __construct(?ConditionRegistry $registry = null, ?OutcomeInstructionService $instructionService = null)
    {
        $this->registry = $registry ?? new ConditionRegistry();
        $this->instructionService = $instructionService ?? new OutcomeInstructionService();
    }

    /**
     * @return list<SimulationField>
     */
    public function fieldsFor(Action $action): array
    {
        /** @var array<string, SimulationField> $fields keyed by SimulationField::id() for dedup */
        $fields = [];

        foreach ($action->getConditions() as $condition) {
            $handler = $this->registry->getCondition($condition->getConditionType());
            if ($handler instanceof DeclaresSimulationInputs) {
                foreach ($handler::simulationInputs($condition->getParameters() ?? []) as $field) {
                    $fields[$field->id()] = $field;
                }
            }
        }

        foreach ($action->getOutcomes() as $outcome) {
            foreach ($this->instructionService->getOutcomeInstructionsByOutcome((int) $outcome->getId()) as $instruction) {
                if ($instruction instanceof DeclaresSimulationInputs && $instruction instanceof \App\Entity\OutcomeInstruction) {
                    foreach ($instruction::simulationInputs($instruction->getParameters() ?? []) as $field) {
                        $fields[$field->id()] = $field;
                    }
                }
            }
        }

        return array_values($fields);
    }
}
