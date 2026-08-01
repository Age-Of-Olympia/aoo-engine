<?php

namespace App\Action\Schema;

use App\Action\Condition\ConditionRegistry;
use App\Action\OutcomeInstruction\OutcomeInstructionFactory;

final class ActionSchemaCatalog
{
    private ConditionRegistry $conditionRegistry;

    public function __construct(?ConditionRegistry $conditionRegistry = null)
    {
        $this->conditionRegistry = $conditionRegistry ?? new ConditionRegistry();
    }

    /**
     * @return array<int, string>
     */
    public function allConditionTypes(): array
    {
        return $this->conditionRegistry->getTypes();
    }

    /**
     * @return array<int, string>
     */
    public function allOutcomeInstructionTypes(): array
    {
        return array_keys(OutcomeInstructionFactory::typeMap());
    }

    public function schemaForCondition(string $type): ParameterSchema
    {
        $handler = $this->conditionRegistry->getCondition($type);

        return $this->schemaForClass($handler !== null ? $handler::class : null);
    }

    public function schemaForOutcomeInstruction(string $type): ParameterSchema
    {
        return $this->schemaForClass(OutcomeInstructionFactory::typeMap()[$type] ?? null);
    }

    private function schemaForClass(?string $class): ParameterSchema
    {
        if ($class !== null && is_subclass_of($class, HasParameterSchemaInterface::class)) {
            return $class::parameterSchema();
        }

        return new ParameterSchema();
    }
}
