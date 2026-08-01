<?php

namespace App\Service\Action;

use App\Action\Condition\ConditionRegistry;
use App\Action\Schema\DeclaresSimulationInputsInterface;
use App\Action\Schema\HasParameterSchemaInterface;
use App\Action\Schema\SchemaSimulationInputs;
use App\Action\Schema\SimulationField;
use App\Entity\Action;
use App\Service\OutcomeInstructionService;

/**
 * Derives the simulator's form fields from an action's actual conditions +
 * outcomes, and unions the results — so the form always shows exactly what THAT
 * action needs; add a condition, its inputs appear.
 *
 * Each condition/outcome contributes its inputs one of two ways: a
 * HasParameterSchemaInterface one has its TRAIT fields derived from the schema
 * ({@see SchemaSimulationInputs}); a DeclaresSimulationInputsInterface one supplies them by
 * hand (for caracs read with no backing param, e.g. Rest, or a roll that reads
 * nothing, e.g. BuffCompute). The hand-written declaration wins when both apply.
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
            if ($handler !== null) {
                foreach ($this->inputsFor($handler, $condition->getParameters() ?? []) as $field) {
                    $fields[$field->id()] = $field;
                }
            }
        }

        foreach ($action->getOutcomes() as $outcome) {
            foreach ($this->instructionService->getOutcomeInstructionsByOutcome((int) $outcome->getId()) as $instruction) {
                foreach ($this->inputsFor($instruction, $instruction->getParameters() ?? []) as $field) {
                    $fields[$field->id()] = $field;
                }
            }
        }

        return array_values($fields);
    }

    /**
     * Inputs a single condition/outcome contributes: a hand-written declaration if
     * it has one, otherwise the TRAIT fields derived from its parameter schema.
     *
     * @param array<string, mixed> $params
     * @return list<SimulationField>
     */
    private function inputsFor(object $handler, array $params): array
    {
        if ($handler instanceof DeclaresSimulationInputsInterface) {
            return $handler::simulationInputs($params);
        }
        if ($handler instanceof HasParameterSchemaInterface) {
            return SchemaSimulationInputs::derive($handler::parameterSchema(), $params);
        }

        return [];
    }
}
