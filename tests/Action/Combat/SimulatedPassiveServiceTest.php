<?php

namespace Tests\Action\Combat;

use App\Entity\ActionPassive;
use App\Simulation\SimulatedPassiveService;
use App\Simulation\SimulatedPlayer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-combat')]
class SimulatedPassiveServiceTest extends TestCase
{
    private function passive(int $id, string $name, string $carac, float $value): ActionPassive
    {
        $passive = new ActionPassive();
        $passive->setId($id);
        $passive->setName($name);
        $passive->setTraits([]);
        $passive->setType('att');
        $passive->setCarac($carac);
        $passive->setValue($value);

        return $passive;
    }

    private function player(): SimulatedPlayer
    {
        return new SimulatedPlayer(1, ['cc' => 12], [], (object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => 'gaia']);
    }

    public function testReturnsTheGivenPassivesAndAnswersNameChecks(): void
    {
        $service = new SimulatedPassiveService([$this->passive(7, 'griffes', 'fixed', 3.0)], $this->player());

        $this->assertCount(1, $service->getPassivesByPlayerId(1));
        $this->assertTrue($service->hasPassiveByPlayerIdByName(1, 'griffes'));
        $this->assertFalse($service->hasPassiveByPlayerIdByName(1, 'berserker'));
    }

    public function testComputesAValueLookedUpByIdOrByName(): void
    {
        $service = new SimulatedPassiveService([$this->passive(7, 'griffes', 'fixed', 3.0)], $this->player());

        $this->assertSame(3, $service->getComputedValueByPlayerIdById(1, 7));     // actor side: by id
        $this->assertSame(3, $service->getComputedValueByPlayerIdById(1, 'griffes')); // target side: by name
        $this->assertSame(0, $service->getComputedValueByPlayerIdById(1, 'unknown'));
    }

    public function testSelectedPassivesAreTreatedAsActive(): void
    {
        $service = new SimulatedPassiveService([$this->passive(7, 'griffes', 'fixed', 3.0)], $this->player());

        $this->assertTrue($service->checkPassiveConditionsByPlayerById($this->player(), $this->passive(7, 'griffes', 'fixed', 3.0), null));
    }
}
