<?php

namespace Tests\Action\Combat;

use App\Action\Combat\PassiveValueCalculator;
use App\Entity\ActionPassive;
use App\Simulation\SimulatedPlayer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-combat')]
class PassiveValueCalculatorTest extends TestCase
{
    private function passive(string $carac, float $value): ActionPassive
    {
        $passive = new ActionPassive();
        $passive->setName('p');
        $passive->setTraits([]);
        $passive->setType('att');
        $passive->setCarac($carac);
        $passive->setValue($value);

        return $passive;
    }

    /**
     * @param array<string, int> $caracs
     * @param array<string, int> $remaining
     * @param array<string, int> $effects
     */
    private function player(array $caracs, array $remaining = [], array $effects = []): SimulatedPlayer
    {
        return new SimulatedPlayer(
            1,
            $caracs,
            $remaining,
            (object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => 'gaia'],
            [],
            null,
            $effects,
            [],
        );
    }

    public function testFixedReturnsTheRawValue(): void
    {
        $this->assertSame(3, (new PassiveValueCalculator())->compute($this->passive('fixed', 3.0), $this->player([])));
    }

    public function testTraitCaracScalesByTheCaracValue(): void
    {
        // floor(10 * 0.5)
        $this->assertSame(5, (new PassiveValueCalculator())->compute($this->passive('mvt', 0.5), $this->player(['mvt' => 10])));
    }

    public function testLostPvScalesByMissingHealth(): void
    {
        // floor((30 - 10) * 0.1)
        $value = (new PassiveValueCalculator())->compute($this->passive('lostPV', 0.1), $this->player(['pv' => 30], ['pv' => 10]));
        $this->assertSame(2, $value);
    }

    public function testEffectsScalesByEffectCount(): void
    {
        // floor(2 effects * 2)
        $value = (new PassiveValueCalculator())->compute($this->passive('effects', 2.0), $this->player([], [], ['poison' => 1, 'feu' => 1]));
        $this->assertSame(4, $value);
    }
}
