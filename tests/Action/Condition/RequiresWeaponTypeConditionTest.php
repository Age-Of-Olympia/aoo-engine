<?php

namespace Tests\Action\Condition;

use App\Action\Condition\ConditionObject;
use App\Action\Condition\RequiresWeaponTypeCondition;
use App\Entity\ActionCondition;
use Classes\Item;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-condition')]
class RequiresWeaponTypeConditionTest extends TestCase
{
    private function actorHolding(string $subtype): Player
    {
        $weapon = $this->createMock(Item::class);
        $weapon->data = (object) ['subtype' => $subtype, 'name' => 'Épée'];
        $actor = $this->createMock(Player::class);
        $actor->emplacements = (object) ['main1' => $weapon];

        return $actor;
    }

    private function check(array $params, Player $actor): bool
    {
        $condition = new ActionCondition();
        $condition->setParameters($params);

        return (new RequiresWeaponTypeCondition())->check($actor, null, $condition, new ConditionObject())->isSuccess();
    }

    public function testAnEmptyEmplacementListMeansTheMainHand(): void
    {
        $actor = $this->actorHolding('melee');

        $this->assertTrue($this->check(['type' => ['melee'], 'location' => []], $actor), 'the workbench saves [] for an untouched select');
        $this->assertTrue($this->check(['type' => ['melee']], $actor), 'no key: main hand too');
        $this->assertFalse($this->check(['type' => ['tir']], $actor), 'the wrong type is still refused');
    }
}
