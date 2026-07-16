<?php

namespace Tests\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Action\OutcomeInstruction\ApplyStatusOutcomeInstruction;
use Classes\Player;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Category gate of ApplyStatus (retours design 2026-07-16) : un effet ne
 * s'applique qu'aux catégories d'entités déclarées par l'instruction —
 * ['character'] par défaut, donc jamais d'adrénaline sur une palissade,
 * mais une action de siège peut déclarer ['character','structure'] pour
 * incendier un bâtiment. Le retrait d'effet reste toujours permis.
 */
class ApplyStatusCategoryGateTest extends TestCase
{
    /** @var array<int, array{0:string}> */
    private array $applied = [];

    /** @var array<int, string> */
    private array $ended = [];

    protected function setUp(): void
    {
        if (!defined('EFFECTS_HIDDEN')) {
            define('EFFECTS_HIDDEN', []);
        }
        if (!defined('EFFECTS_RA_FONT')) {
            define('EFFECTS_RA_FONT', ['adrenaline' => 'ra-lightning', 'feu' => 'ra-fire']);
        }
        $this->applied = [];
        $this->ended = [];
    }

    private function player(int $id, string $playerType): Player&MockObject
    {
        $player = $this->createMock(Player::class);
        $player->id = $id;
        $player->data = (object) ['name' => 'P' . $id, 'player_type' => $playerType];
        $player->method('add_effect')->willReturnCallback(function ($name) use ($id) {
            $this->applied[] = [$id . ':' . $name];
        });
        $player->method('end_effect')->willReturnCallback(function ($name) use ($id) {
            $this->ended[] = $id . ':' . $name;
        });

        return $player;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function instruction(array $params): ApplyStatusOutcomeInstruction
    {
        $instruction = new ApplyStatusOutcomeInstruction();
        $instruction->setParameters($params);

        return $instruction;
    }

    public function testDefaultGateRefusesAStructureTarget(): void
    {
        $actor = $this->player(1, 'real');
        $building = $this->player(20000001, 'building');

        $result = $this->instruction(['effect' => 'adrenaline', 'player' => 'target'])
            ->execute($actor, $building, new ConditionObject());

        $this->assertSame([], $this->applied, 'no effect may land on a structure by default');
        $this->assertSame([], $result->getOutcomeSuccessMessages(), 'no application message either');
    }

    public function testDeclaringStructureLetsFireReachABuilding(): void
    {
        $actor = $this->player(1, 'real');
        $building = $this->player(20000001, 'building');

        $this->instruction(['effect' => 'feu', 'player' => 'target', 'targets' => ['character', 'structure']])
            ->execute($actor, $building, new ConditionObject());

        $this->assertSame([['20000001:feu']], $this->applied, 'a declared structure effect must apply');
    }

    public function testBothModeAppliesToTheCharacterAndSkipsTheStructure(): void
    {
        $actor = $this->player(1, 'real');
        $building = $this->player(20000001, 'building');

        $this->instruction(['effect' => 'adrenaline', 'player' => 'both'])
            ->execute($actor, $building, new ConditionObject());

        $this->assertSame([['1:adrenaline']], $this->applied, 'the character side applies, the structure side is skipped');
    }

    public function testEffectRemovalIsAlwaysAllowedOnAStructure(): void
    {
        $actor = $this->player(1, 'real');
        $building = $this->player(20000001, 'building');

        $this->instruction(['effect' => 'feu', 'apply' => false, 'player' => 'target'])
            ->execute($actor, $building, new ConditionObject());

        $this->assertSame(['20000001:feu'], $this->ended, 'removing an effect is cleanup, never gated');
    }
}
