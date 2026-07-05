<?php

namespace Tests\Action\Schema;

use App\Action\HealAction;
use App\Action\MeleeAction;
use App\Entity\Action;
use App\Service\Action\ActionCreateService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionCreateServiceTest extends TestCase
{
    private function entityManager(): EntityManagerInterface&MockObject
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->discriminatorMap = ['melee' => MeleeAction::class, 'heal' => HealAction::class];

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn($metadata);

        return $em;
    }

    public function testCreatesAnActionOfTheRequestedStiType(): void
    {
        $em = $this->entityManager();
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(MeleeAction::class));
        $em->expects($this->once())->method('flush');

        $action = (new ActionCreateService($em))->create('melee', 'attaquer', 'Attaquer', 3, 'melee');

        $this->assertInstanceOf(MeleeAction::class, $action);
        $this->assertSame('attaquer', $action->getName());
        $this->assertSame('Attaquer', $action->getDisplayName());
        $this->assertSame(3, $action->getLevel());
        $this->assertSame('melee', $action->getCategory());
    }

    public function testDisplayNameFallsBackToTheName(): void
    {
        $action = (new ActionCreateService($this->entityManager()))->create('heal', 'soin', '   ', 1);

        $this->assertSame('soin', $action->getDisplayName());
    }

    public function testRejectsAnUnknownType(): void
    {
        $em = $this->entityManager();
        $em->expects($this->never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);
        (new ActionCreateService($em))->create('banana', 'x', 'X', 1);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ActionCreateService($this->entityManager()))->create('melee', '   ', 'X', 1);
    }

    public function testAvailableTypesAreDerivedFromTheDiscriminatorMap(): void
    {
        $types = (new ActionCreateService($this->entityManager()))->availableTypes();

        $this->assertSame(['melee' => 'Melee', 'heal' => 'Heal'], $types);
    }
}
