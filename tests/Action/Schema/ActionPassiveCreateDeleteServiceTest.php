<?php

namespace Tests\Action\Schema;

use App\Entity\ActionPassive;
use App\Service\Action\ActionPassiveCreateService;
use App\Service\Action\ActionPassiveDeleteService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionPassiveCreateDeleteServiceTest extends TestCase
{
    public function testCreatesAnEmptyPassiveWithSaneDefaults(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(ActionPassive::class));
        $em->expects($this->once())->method('flush');

        $passive = (new ActionPassiveCreateService($em))->create('  griffes  ');

        $this->assertSame('griffes', $passive->getName());
        $this->assertSame('griffes', $passive->getDisplayName());
        $this->assertSame([], $passive->getTraits());
        $this->assertSame(1, $passive->getLevel());
    }

    public function testCreateRejectsAnEmptyName(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);
        (new ActionPassiveCreateService($em))->create('   ');
    }

    public function testDeleteRemovesThePassive(): void
    {
        $passive = new ActionPassive();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($passive);
        $em->expects($this->once())->method('remove')->with($passive);
        $em->expects($this->once())->method('flush');

        (new ActionPassiveDeleteService($em))->delete(5);
    }

    public function testDeleteRejectsAMissingPassive(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);
        $em->expects($this->never())->method('remove');

        $this->expectException(\InvalidArgumentException::class);
        (new ActionPassiveDeleteService($em))->delete(404);
    }
}
