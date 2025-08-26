<?php

namespace App\Interface;

interface OutcomeInstructionServiceInterface
{
    /**
     * Returns a OutcomeInstruction entity that matches the given type and outcome, or null if not found.
     * @param string $type The instruction type
     * @param int $outcomeId The outcome ID
     * @return OutcomeInstructionInterface|null The outcome instruction or null if not found
     */
    public function getOutcomeInstructionByTypeByOutcome(string $type, int $outcomeId): ?OutcomeInstructionInterface;

    /**
     * Get all outcome instructions for a specific outcome, sorted by order index
     * @param int $outcomeId The outcome ID
     * @return array Array of OutcomeInstructionInterface objects sorted by orderIndex
     */
    public function getOutcomeInstructionsByOutcome(int $outcomeId): array;
}