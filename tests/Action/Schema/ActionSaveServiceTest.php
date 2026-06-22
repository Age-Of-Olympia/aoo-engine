<?php

namespace Tests\Action\Schema;

use App\Action\OutcomeInstruction\ApplyStatusOutcomeInstruction;
use App\Entity\Action;
use App\Entity\ActionCondition;
use App\Entity\ActionOutcome;
use App\Entity\OutcomeInstruction;
use App\Service\Action\ActionSaveService;
use App\Service\OutcomeInstructionService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionSaveServiceTest extends TestCase
{
    private function setId(object $entity, string $declaringClass, int $id): void
    {
        $property = new \ReflectionProperty($declaringClass, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    private function entityManagerReturning(Action $action): EntityManagerInterface
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($action);

        return $em;
    }

    public function testRawEditorParametersAreSavedForASchemalessCondition(): void
    {
        $condition = new ActionCondition();
        $condition->setConditionType('RequiresTraitValue');
        $condition->setParameters(['pm' => 10]);
        $this->setId($condition, ActionCondition::class, 5);

        $action = $this->createMock(Action::class);
        $action->method('getConditions')->willReturn(new ArrayCollection([$condition]));
        $action->method('getOutcomes')->willReturn(new ArrayCollection());

        $service = new ActionSaveService(
            $this->entityManagerReturning($action),
            null,
            null,
            $this->createMock(OutcomeInstructionService::class),
        );

        $service->saveParameters(1, [], [], [5 => [['k' => 'a', 'v' => '1'], ['k' => 'pm', 'v' => '8']]], []);

        $this->assertSame(['a' => 1, 'pm' => 8], $condition->getParameters());
    }

    public function testSavesEachOutcomesApplyToSelfFlag(): void
    {
        $toSelf = new ActionOutcome();
        $this->setId($toSelf, ActionOutcome::class, 3);     // currently target -> set self
        $toTarget = (new ActionOutcome())->setApplyToSelf(true);
        $this->setId($toTarget, ActionOutcome::class, 4);    // currently self -> set target

        $action = $this->createMock(Action::class);
        $action->method('getConditions')->willReturn(new ArrayCollection());
        $action->method('getOutcomes')->willReturn(new ArrayCollection([$toSelf, $toTarget]));

        $service = new ActionSaveService($this->entityManagerReturning($action), null, null, $this->createMock(OutcomeInstructionService::class));

        $service->saveOutcomeTargets(1, [3 => '1', 4 => '0']);

        $this->assertTrue($toSelf->getApplyToSelf());
        $this->assertFalse($toTarget->getApplyToSelf());
    }

    public function testSaveOutcomeTargetsLeavesOutcomesAbsentFromThePayloadUntouched(): void
    {
        $outcome = (new ActionOutcome())->setApplyToSelf(true);
        $this->setId($outcome, ActionOutcome::class, 9);

        $action = $this->createMock(Action::class);
        $action->method('getConditions')->willReturn(new ArrayCollection());
        $action->method('getOutcomes')->willReturn(new ArrayCollection([$outcome]));

        $service = new ActionSaveService($this->entityManagerReturning($action), null, null, $this->createMock(OutcomeInstructionService::class));

        $service->saveOutcomeTargets(1, []);

        $this->assertTrue($outcome->getApplyToSelf());
    }

    public function testApplyStatusRawKeyStaysFirstSoTheEffectResolves(): void
    {
        // ApplyStatus keys its effect off array_key_first(); a typed save must keep
        // the raw effect key ahead of the schema fields.
        $instruction = new ApplyStatusOutcomeInstruction();
        $instruction->setParameters(['adrenaline' => true, 'duration' => 1]);
        $this->setId($instruction, OutcomeInstruction::class, 7);

        $outcome = $this->createMock(ActionOutcome::class);
        $outcome->method('getId')->willReturn(3);

        $action = $this->createMock(Action::class);
        $action->method('getConditions')->willReturn(new ArrayCollection());
        $action->method('getOutcomes')->willReturn(new ArrayCollection([$outcome]));

        $instructionService = $this->createMock(OutcomeInstructionService::class);
        $instructionService->method('getOutcomeInstructionsByOutcome')->willReturn([$instruction]);

        $service = new ActionSaveService($this->entityManagerReturning($action), null, null, $instructionService);

        $service->saveParameters(
            1,
            [],
            [7 => ['duration' => 2, 'player' => 'actor', 'value' => 1, 'stackable' => false]],
            [],
            [7 => [['k' => 'adrenaline', 'v' => 'true']]],
        );

        $params = $instruction->getParameters();
        $this->assertSame('adrenaline', array_key_first($params));
        $this->assertTrue($params['adrenaline']);
        $this->assertSame(2, $params['duration']);
    }
}
