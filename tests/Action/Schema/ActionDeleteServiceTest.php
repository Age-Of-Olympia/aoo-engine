<?php

namespace Tests\Action\Schema;

use App\Entity\Action;
use App\Entity\Race;
use App\Service\Action\ActionDeleteService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionDeleteServiceTest extends TestCase
{
    public function testRemovesTheActionAndDetachesItFromItsRaces(): void
    {
        $race = $this->createMock(Race::class);
        $action = $this->createMock(Action::class);
        $action->method('getRaces')->willReturn(new ArrayCollection([$race]));

        $race->expects($this->once())->method('removeAction')->with($action);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($action);
        $em->expects($this->once())->method('remove')->with($action);
        $em->expects($this->once())->method('flush');

        (new ActionDeleteService($em))->delete(5);
    }

    public function testThrowsAndRemovesNothingWhenTheActionIsMissing(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);
        $em->expects($this->never())->method('remove');

        $this->expectException(\InvalidArgumentException::class);
        (new ActionDeleteService($em))->delete(999);
    }
}
