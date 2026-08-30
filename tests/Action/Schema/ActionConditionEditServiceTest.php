<?php

namespace Tests\Action\Schema;

use App\Action\Condition\ConditionRegistry;
use App\Entity\Action;
use App\Entity\ActionCondition;
use App\Service\Action\ActionConditionEditService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionConditionEditServiceTest extends TestCase
{
    private function service(EntityManagerInterface $em): ActionConditionEditService
    {
        return new ActionConditionEditService($em, new ConditionRegistry());
    }

    private function entityManager(): EntityManagerInterface&MockObject
    {
        return $this->createMock(EntityManagerInterface::class);
    }

    public function testAddsAnEmptyConditionOfTheGivenTypeToTheAction(): void
    {
        $action = $this->createMock(Action::class);
        $action->expects($this->once())->method('addCondition')->with($this->isInstanceOf(ActionCondition::class));

        $em = $this->entityManager();
        $em->method('find')->willReturn($action);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(ActionCondition::class));
        $em->expects($this->once())->method('flush');

        $condition = $this->service($em)->addCondition(5, 'Plan');

        $this->assertSame('Plan', $condition->getConditionType());
        $this->assertSame([], $condition->getParameters());
    }

    /**
     * A fresh condition refuses the action until told otherwise. Born
     * non-blocking, a half-configured requirement let the action run and
     * charged the player for it without a word.
     */
    public function testANewConditionIsBornBlocking(): void
    {
        $action = $this->createMock(Action::class);
        $em = $this->entityManager();
        $em->method('find')->willReturn($action);

        $this->assertTrue($this->service($em)->addCondition(5, 'Plan')->isBlocking());
    }

    public function testRejectsAnUnknownConditionType(): void
    {
        $em = $this->entityManager();
        $em->method('find')->willReturn($this->createMock(Action::class));
        $em->expects($this->never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);
        $this->service($em)->addCondition(5, 'NotARealCondition');
    }

    public function testRejectsAddingToAMissingAction(): void
    {
        $em = $this->entityManager();
        $em->method('find')->willReturn(null);
        $em->expects($this->never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);
        $this->service($em)->addCondition(999, 'Plan');
    }

    public function testRemoveDetachesTheConditionFromItsActionForOrphanRemoval(): void
    {
        $action = $this->createMock(Action::class);
        $condition = $this->createMock(ActionCondition::class);
        $condition->method('getAction')->willReturn($action);

        $action->expects($this->once())->method('removeCondition')->with($condition);

        $em = $this->entityManager();
        $em->method('find')->willReturn($condition);
        $em->expects($this->never())->method('remove');
        $em->expects($this->once())->method('flush');

        $this->service($em)->removeCondition(7);
    }

    public function testRejectsRemovingAMissingCondition(): void
    {
        $em = $this->entityManager();
        $em->method('find')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->service($em)->removeCondition(404);
    }
}
