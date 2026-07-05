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
            'actor_remaining' => ['a' => '4'],
            'distance' => '3',
            'actor_weapon' => 'tir',
            'actor_effect_name' => ['vulnerabilite', '', 'protection'],
            'actor_effect_value' => ['3', '9', '2'],
            'actor_passives' => ['duelliste', 'anguille'],
        ]);

        $this->assertSame(['cc' => 15, 'f' => 10], $input->actorCaracs);
        $this->assertSame(['agi' => 8], $input->targetCaracs);
        $this->assertSame(4, $input->actorRemaining['a']);
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
        $this->assertSame(['a' => 6, 'pv' => 20, 'pm' => 15, 'mvt' => 6], $input->actorRemaining);
        $this->assertSame('gaia', $input->plan);
        $this->assertFalse($input->actorBerserk);
    }

    public function testMapsTheEnvironmentToggles(): void
    {
        $input = (new SimulationInputMapper())->fromPost(['enfers' => '1', 'actor_berserk' => '1']);

        $this->assertSame('enfers', $input->plan);
        $this->assertTrue($input->actorBerserk);
    }

    public function testMapsPerSlotEquipmentForBothSidesDroppingEmpties(): void
    {
        $input = (new SimulationInputMapper())->fromPost([
            'actor_equipment' => ['tete' => 'casque', 'doigt' => ''],
            'target_equipment' => ['tete' => 'casque'],
        ]);

        $this->assertSame(['tete' => 'casque'], $input->actorEquipment);
        $this->assertSame(['tete' => 'casque'], $input->targetEquipment);
    }

    public function testMapsCheckedTileTypesAndDropsUnchecked(): void
    {
        $input = (new SimulationInputMapper())->fromPost([
            'tile' => ['routes' => '1', 'eau' => '0', 'sable' => '1'],
        ]);

        $this->assertSame(['routes', 'sable'], $input->tileTypes);
    }

    public function testTileTypesDefaultToEmpty(): void
    {
        $this->assertSame([], (new SimulationInputMapper())->fromPost([])->tileTypes);
    }

    public function testMapsPerSideRankFloorIngAtOne(): void
    {
        $input = (new SimulationInputMapper())->fromPost(['actor_rank' => '5', 'target_rank' => '0']);

        $this->assertSame(5, $input->actorRank);
        $this->assertSame(1, $input->targetRank); // floored to 1
    }

    public function testRankDefaultsToOne(): void
    {
        $input = (new SimulationInputMapper())->fromPost([]);

        $this->assertSame(1, $input->actorRank);
        $this->assertSame(1, $input->targetRank);
    }

    public function testMapsEnergieDefaultingToTheRealMaxForTheActionPoints(): void
    {
        // actor_energie posted -> kept; target_energie absent -> ENERGIE_CST − a,
        // where a falls back to the same baseline the remaining map uses (6), so
        // 7 − 6 = 1 — not a different baseline that would mismatch the action points.
        $input = (new SimulationInputMapper())->fromPost(['actor_energie' => '2']);

        $this->assertSame(2, $input->actorEnergie);
        $this->assertSame(6, $input->targetRemaining['a']);
        $this->assertSame(1, $input->targetEnergie);
    }

    public function testEnergieDefaultTracksThePostedActionPoints(): void
    {
        // 6 action points -> max energie 7 − 6 = 1.
        $input = (new SimulationInputMapper())->fromPost(['actor_remaining' => ['a' => '6']]);

        $this->assertSame(1, $input->actorEnergie);
    }

    public function testActionPointsBaselineUsesTheEngineKeyNotPa(): void
    {
        // The engine reads action points as getRemaining('a'); a 'pa' baseline
        // would never be consulted, leaving 'a' to fall back to 0.
        $input = (new SimulationInputMapper())->fromPost([]);

        $this->assertSame(6, $input->actorRemaining['a']);
        $this->assertArrayNotHasKey('pa', $input->actorRemaining);
    }

    public function testRunsAreClampedToTheAllowedRange(): void
    {
        $mapper = new SimulationInputMapper();

        $this->assertSame(1, $mapper->runs([]));
        $this->assertSame(1, $mapper->runs(['runs' => '0']));
        $this->assertSame(100, $mapper->runs(['runs' => '99999']));
        $this->assertSame(100, $mapper->runs(['runs' => '250']));
        $this->assertSame(60, $mapper->runs(['runs' => '60']));
    }
}
