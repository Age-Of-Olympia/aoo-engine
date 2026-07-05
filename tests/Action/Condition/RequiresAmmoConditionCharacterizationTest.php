<?php

namespace Tests\Action\Condition;

use App\Action\Condition\RequiresAmmoCondition;
use App\Entity\ActionCondition;
use Classes\Item;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-condition')]
class RequiresAmmoConditionCharacterizationTest extends TestCase
{
    public function testThrownWeaponWithoutATargetKeepsTheWeaponInsteadOfFataling(): void
    {
        // applyCosts used to dereference $target->getCoords() with no guard, so a
        // thrown ('jet') weapon with no target fataled. With no target there is no
        // distance, so the weapon is kept.
        $weapon = $this->createMock(Item::class);
        $weapon->data = (object) ['subtype' => 'jet', 'name' => 'Javelot'];

        $actor = $this->createMock(Player::class);
        $actor->emplacements = (object) ['main1' => $weapon];

        $condition = new ActionCondition();
        $condition->setParameters([]);

        $messages = (new RequiresAmmoCondition())->applyCosts($actor, null, $condition);

        $this->assertStringContainsString('Vous gardez Javelot.', implode(' ', $messages));
    }
}
