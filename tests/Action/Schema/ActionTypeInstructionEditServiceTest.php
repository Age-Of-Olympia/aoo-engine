<?php

namespace Tests\Action\Schema;

use App\Entity\ActionTypeInstruction;
use App\Service\Action\ActionTypeInstructionEditService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionTypeInstructionEditServiceTest extends TestCase
{
    /**
     * @param array<int, ActionTypeInstruction> $existing
     */
    private function entityManager(array $existing = []): EntityManagerInterface&MockObject
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn($existing);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return $em;
    }

    public function testAddsAnInstructionOfAValidTypeToTheTypeKey(): void
    {
        $em = $this->entityManager();
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(ActionTypeInstruction::class));
        $em->expects($this->once())->method('flush');

        $instruction = (new ActionTypeInstructionEditService($em))->addInstruction('attack', 'applystatus');

        $this->assertSame('attack', $instruction->getTypeKey());
        $this->assertSame('applystatus', $instruction->getInstructionType());
        $this->assertSame(0, $instruction->getOrderIndex());
    }

    public function testRejectsAnUnknownActionType(): void
    {
        $em = $this->entityManager();
        $em->expects($this->never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);
        (new ActionTypeInstructionEditService($em))->addInstruction('definitelynotatype', 'applystatus');
    }

    public function testRejectsAnUnknownInstructionType(): void
    {
        $em = $this->entityManager();
        $em->expects($this->never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);
        (new ActionTypeInstructionEditService($em))->addInstruction('attack', 'notarealinstruction');
    }

    public function testRemoveDeletesTheInstruction(): void
    {
        $instruction = new ActionTypeInstruction();
        $em = $this->entityManager();
        $em->method('find')->willReturn($instruction);
        $em->expects($this->once())->method('remove')->with($instruction);
        $em->expects($this->once())->method('flush');

        (new ActionTypeInstructionEditService($em))->removeInstruction(5);
    }

    public function testRejectsRemovingAMissingInstruction(): void
    {
        $em = $this->entityManager();
        $em->method('find')->willReturn(null);
        $em->expects($this->never())->method('remove');

        $this->expectException(\InvalidArgumentException::class);
        (new ActionTypeInstructionEditService($em))->removeInstruction(404);
    }

    public function testSaveParametersMergesPostedRawIntoTheInstruction(): void
    {
        $instruction = (new ActionTypeInstruction())
            ->setTypeKey('attack')
            ->setInstructionType('applystatus')
            ->setOrderIndex(0)
            ->setParameters([]);
        $instruction->setId(7);

        $em = $this->entityManager([$instruction]);
        $em->expects($this->once())->method('flush');

        (new ActionTypeInstructionEditService($em))->saveParameters(
            'attack',
            [],
            [7 => [['k' => 'foo', 'v' => 'bar']]],
        );

        $this->assertArrayHasKey('foo', $instruction->getParameters());
    }
}
