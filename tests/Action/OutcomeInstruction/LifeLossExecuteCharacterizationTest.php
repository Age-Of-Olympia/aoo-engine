<?php

namespace Tests\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Action\OutcomeInstruction\LifeLossOutcomeInstruction;
use App\Entity\ActionPassive;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Action\Mock\PassiveServiceStub;

/**
 * Black-box characterization of LifeLoss::execute() — pins the damage pipeline
 * (DamageCalculator + passive bonuses + crit + tooltip + drain) before the
 * god-method is split. autoCrit forces the crit branch so the result is
 * deterministic despite the method's internal rand()/random_int().
 */
#[Group('action-outcome')]
class LifeLossExecuteCharacterizationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('CARACS')) {
            define('CARACS', ['f' => 'F', 'e' => 'E', 'm' => 'M', 'cc' => 'CC', 'agi' => 'Agi', 'pm' => 'PM']);
        }
        if (!defined('DMG_CRIT')) {
            define('DMG_CRIT', 5);
        }
    }

    /**
     * @param array<string,int> $caracs
     * @param array<int,int>     $bonusCapture filled with every putBonus() argument
     */
    private function player(string $name, array $caracs, array &$bonusCapture): Player&MockObject
    {
        $id = $name === 'Actor' ? 1 : 2;
        $player = $this->createMock(Player::class);
        $player->data = (object) ['name' => $name];
        $player->caracs = (object) $caracs;
        $player->id = $id;
        $player->playerPassiveService = new PassiveServiceStub();
        $player->method('getId')->willReturn($id);
        $player->method('getEffectValue')->willReturn(0);
        $player->method('getRemaining')->willReturn(99);
        $player->method('putBonus')->willReturnCallback(function ($bonus) use (&$bonusCapture) {
            $bonusCapture[] = $bonus;
            return true;
        });

        return $player;
    }

    private function attPassive(string $trait): ActionPassive
    {
        $p = new ActionPassive();
        $p->setId(7);
        $p->setName('griffes');
        $p->setTraits([$trait]);
        $p->setType('att');
        $p->setCarac('fixed');
        $p->setValue(0.0);

        return $p;
    }

    private function defPassive(string $trait): ActionPassive
    {
        $p = new ActionPassive();
        $p->setId(8);
        $p->setName('cuir');
        $p->setTraits([$trait]);
        $p->setType('def');
        $p->setCarac('fixed');
        $p->setValue(0.0);

        return $p;
    }

    /**
     * @param array<string,mixed> $extraParams
     */
    private function instruction(array $extraParams = []): LifeLossOutcomeInstruction
    {
        $instruction = new LifeLossOutcomeInstruction();
        $instruction->setParameters(array_merge(
            ['actorDamagesTrait' => 'f', 'targetDamagesTrait' => 'e', 'autoCrit' => true],
            $extraParams,
        ));

        return $instruction;
    }

    public function testBaseDamageIsAttackMinusDefencePlusCrit(): void
    {
        $actorBonus = [];
        $targetBonus = [];
        $actor = $this->player('Actor', ['f' => 10], $actorBonus);
        $target = $this->player('Target', ['e' => 4], $targetBonus);

        $result = $this->instruction()->execute($actor, $target, new ConditionObject());

        // (10 - 4) + 3 crit = 9, applied as negative PV to the target.
        $this->assertContains(['pv' => -9], $targetBonus);
        $this->assertSame(9, $result->getTotalDamages());
        $message = $result->getOutcomeSuccessMessages()[1];
        $this->assertStringContainsString('>9</span> dégâts à Target.', $message);
        $this->assertStringContainsString('F vs E : 10', $message);
    }

    public function testActorAttackPassiveAddsToDamage(): void
    {
        $actorBonus = [];
        $targetBonus = [];
        $actor = $this->player('Actor', ['f' => 10], $actorBonus);
        $actor->playerPassiveService->passives = [$this->attPassive('f')];
        $actor->playerPassiveService->computedValue = 5;
        $target = $this->player('Target', ['e' => 4], $targetBonus);

        $this->instruction()->execute($actor, $target, new ConditionObject());

        // (10 - 4) + 5 passive + 3 crit = 14
        $this->assertContains(['pv' => -14], $targetBonus);
    }

    public function testTargetDefencePassiveReducesDamage(): void
    {
        $actorBonus = [];
        $targetBonus = [];
        $actor = $this->player('Actor', ['f' => 10], $actorBonus);
        $target = $this->player('Target', ['e' => 4], $targetBonus);
        $target->playerPassiveService->passives = [$this->defPassive('e')];
        $target->playerPassiveService->computedValue = 2;

        $this->instruction()->execute($actor, $target, new ConditionObject());

        // (10 - 4) - 2 defence + 3 crit = 7
        $this->assertContains(['pv' => -7], $targetBonus);
    }

    public function testDrainHealsTheActorByAThirdOfTheDamage(): void
    {
        $actorBonus = [];
        $targetBonus = [];
        $actor = $this->player('Actor', ['f' => 10], $actorBonus);
        $target = $this->player('Target', ['e' => 4], $targetBonus);

        $this->instruction(['drain' => true])->execute($actor, $target, new ConditionObject());

        // damage 9 → leech floor(9/3) = 3 PV back to the actor
        $this->assertContains(['pv' => 3], $actorBonus);
    }
}
