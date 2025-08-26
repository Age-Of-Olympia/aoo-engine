<?php
// Mock pour OutcomeInstructionService
namespace Tests\Action\Mock;

use App\Action\OutcomeInstruction\LifeLossOutcomeInstruction;
use App\Interface\OutcomeInstructionServiceInterface;
use App\Interface\OutcomeInstructionInterface;

class OutcomeInstructionServiceMock implements OutcomeInstructionServiceInterface
{
    public function __construct()
    {
        // Mock constructor - no dependencies needed
    }

    public function getOutcomeInstructionByTypeByOutcome(string $type, int $outcomeId): ?OutcomeInstructionInterface
    {
        $res = new LifeLossOutcomeInstruction();
        $res->setParameters([
            'actorDamagesTrait' => 'f',
            'targetDamagesTrait' => 'e'
        ]);
        // Mock implementation
        return $res;
    }

    public function getOutcomeInstructionsByOutcome(int $outcomeId): array
    {
        // Mock implementation
        $outcome1 = new LifeLossOutcomeInstruction();
        $outcome1->setParameters([
            'actorDamagesTrait' => 'f',
            'targetDamagesTrait' => 'e'
        ]);
        // Mock implementation
        return [$outcome1];
    }
}