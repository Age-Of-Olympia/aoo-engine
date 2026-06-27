<?php

namespace Tests\Action\Schema;

use App\Entity\ActionTypePrecondition;
use App\Service\Action\ActionTypePreconditionEditService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionTypePreconditionEditServiceTest extends TestCase
{
    /**
     * @param array<int, ActionTypePrecondition> $existing
     */
    private function entityManager(array $existing = []): EntityManagerInterface&MockObject
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn($existing);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return $em;
    }

    private function setId(ActionTypePrecondition $entity, int $id): void
    {
        $property = new \ReflectionProperty(ActionTypePrecondition::class, 'id');
        $property->setValue($entity, $id);
    }

    public function testAddsAGlobalPreconditionBlockingByDefault(): void
    {
        $em = $this->entityManager();
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(ActionTypePrecondition::class));
        $em->expects($this->once())->method('flush');

        // Empty scope = global (applies to every action).
        $precondition = (new ActionTypePreconditionEditService($em))->addPrecondition('', 'Plan');

        $this->assertSame('', $precondition->getTypeKey());
        $this->assertSame('Plan', $precondition->getConditionType());
        $this->assertTrue($precondition->isBlocking());
    }

    public function testAddRejectsAnUnknownConditionType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ActionTypePreconditionEditService($this->entityManager()))->addPrecondition('', 'NotACondition');
    }

    public function testAddRejectsANonGlobalUnknownScope(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ActionTypePreconditionEditService($this->entityManager()))->addPrecondition('not-a-type', 'Plan');
    }

    public function testSaveParametersAppliesTheBlockingFlagFromTheCheckbox(): void
    {
        $precondition = (new ActionTypePrecondition())->setTypeKey('')->setConditionType('Plan')->setParameters([])->setBlocking(true);
        $this->setId($precondition, 1);
        $em = $this->entityManager([$precondition]);
        $em->expects($this->once())->method('flush');

        // No checkbox posted for id 1 -> blocking becomes false.
        (new ActionTypePreconditionEditService($em))->saveParameters('', [], [], []);

        $this->assertFalse($precondition->isBlocking());
    }
}
