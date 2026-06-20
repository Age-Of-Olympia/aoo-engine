<?php

namespace Tests\Action\Schema;

use App\Service\Action\SimulationInputMapper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class SimulationInputMapperTest extends TestCase
{
    public function testMapsThePostedHypotheticalStateToASimulationInput(): void
    {
        $input = (new SimulationInputMapper())->fromPost([
            'actor_trait' => ['cc' => '15', 'f' => '10'],
            'target_trait' => ['agi' => '8'],
            'actor_remaining' => ['pa' => '4'],
            'distance' => '3',
            'actor_weapon' => 'tir',
            'actor_effect_name' => ['vulnerabilite', '', 'protection'],
            'actor_effect_value' => ['3', '9', '2'],
            'actor_passives' => ['duelliste', 'anguille'],
        ]);

        $this->assertSame(['cc' => 15, 'f' => 10], $input->actorCaracs);
        $this->assertSame(['agi' => 8], $input->targetCaracs);
        $this->assertSame(4, $input->actorRemaining['pa']);
        $this->assertSame(20, $input->actorRemaining['pv']); // base default preserved
        $this->assertSame(3, $input->distance);
        $this->assertSame('tir', $input->actorWeapon);
        $this->assertSame(['vulnerabilite' => 3, 'protection' => 2], $input->actorEffects); // blank name dropped
        $this->assertSame(['duelliste', 'anguille'], $input->actorPassives);
    }

    public function testAppliesDefaultsForAnEmptyPost(): void
    {
        $input = (new SimulationInputMapper())->fromPost([]);

        $this->assertSame(1, $input->distance);
        $this->assertNull($input->actorWeapon);
        $this->assertSame('melee', $input->targetWeapon);
        $this->assertSame([], $input->actorEffects);
        $this->assertSame(['pa' => 6, 'pv' => 20, 'pm' => 15, 'mvt' => 6], $input->actorRemaining);
    }

    public function testRunsAreClampedToTheAllowedRange(): void
    {
        $mapper = new SimulationInputMapper();

        $this->assertSame(1, $mapper->runs([]));
        $this->assertSame(1, $mapper->runs(['runs' => '0']));
        $this->assertSame(5000, $mapper->runs(['runs' => '99999']));
        $this->assertSame(250, $mapper->runs(['runs' => '250']));
    }
}
