<?php

namespace Tests\Action\Schema;

use App\Action\OutcomeInstruction\LifeLossOutcomeInstruction;
use App\Entity\Action;
use App\Entity\ActionOutcome;
use App\Entity\OutcomeInstruction;
use App\Service\Action\ActionOutcomeEditService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionOutcomeEditServiceTest extends TestCase
{
    private function entityManager(): EntityManagerInterface&MockObject
    {
        return $this->createMock(EntityManagerInterface::class);
    }

    public function testAddOutcomeLinksAndPersistsAnOutcome(): void
    {
        $action = $this->createMock(Action::class);
        $action->expects($this->once())->method('addOutcome')->with($this->isInstanceOf(ActionOutcome::class));

        $em = $this->entityManager();
        $em->method('find')->willReturn($action);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(ActionOutcome::class));
        $em->expects($this->once())->method('flush');

        $outcome = (new ActionOutcomeEditService($em))->addOutcome(2, false);

        $this->assertFalse($outcome->isOnSuccess());
    }

    public function testAddInstructionInstantiatesTheStiSubclassForTheType(): void
    {
        $outcome = $this->createMock(ActionOutcome::class);
        $outcome->method('getInstructions')->willReturn(new ArrayCollection());
        $outcome->expects($this->once())->method('addInstruction')->with($this->isInstanceOf(OutcomeInstruction::class));

        $em = $this->entityManager();
        $em->method('find')->willReturn($outcome);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(LifeLossOutcomeInstruction::class));
        $em->expects($this->once())->method('flush');

        $instruction = (new ActionOutcomeEditService($em))->addInstruction(3, 'lifeloss');

        $this->assertInstanceOf(LifeLossOutcomeInstruction::class, $instruction);
        $this->assertSame([], $instruction->getParameters());
    }

    public function testAddInstructionRejectsAnUnknownType(): void
    {
        $em = $this->entityManager();
        $em->method('find')->willReturn($this->createMock(ActionOutcome::class));
        $em->expects($this->never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);
        (new ActionOutcomeEditService($em))->addInstruction(3, 'notarealtype');
    }

    public function testRemoveOutcomeDetachesFromItsActionForOrphanRemoval(): void
    {
        $action = $this->createMock(Action::class);
        $outcome = $this->createMock(ActionOutcome::class);
        $outcome->method('getAction')->willReturn($action);
        $action->expects($this->once())->method('removeOutcome')->with($outcome);

        $em = $this->entityManager();
        $em->method('find')->willReturn($outcome);
        $em->expects($this->never())->method('remove');
        $em->expects($this->once())->method('flush');

        (new ActionOutcomeEditService($em))->removeOutcome(5);
    }

    public function testRemoveInstructionDetachesFromItsOutcomeForOrphanRemoval(): void
    {
        $outcome = $this->createMock(ActionOutcome::class);
        $instruction = $this->createMock(OutcomeInstruction::class);
        $instruction->method('getOutcome')->willReturn($outcome);
        $outcome->expects($this->once())->method('removeInstruction')->with($instruction);

        $em = $this->entityManager();
        $em->method('find')->willReturn($instruction);
        $em->expects($this->once())->method('flush');

        (new ActionOutcomeEditService($em))->removeInstruction(9);
    }

    public function testRejectsAddingToAMissingOutcome(): void
    {
        $em = $this->entityManager();
        $em->method('find')->willReturn(null);
        $em->expects($this->never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);
        (new ActionOutcomeEditService($em))->addInstruction(404, 'lifeloss');
    }
}
