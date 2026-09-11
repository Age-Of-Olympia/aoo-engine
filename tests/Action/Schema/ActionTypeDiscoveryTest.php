<?php

namespace Tests\Action\Schema;

use App\Action\AttackAction;
use App\Action\MeleeAction;
use App\Action\OutcomeInstruction\LifeLossOutcomeInstruction;
use App\Entity\Action;
use App\Entity\OutcomeInstruction;
use App\Service\Action\ActionTypeDiscovery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionTypeDiscoveryTest extends TestCase
{
    public function testEveryFileOfTheFolderIsAType(): void
    {
        $map = ActionTypeDiscovery::typeMap(Action::class);

        $this->assertSame(MeleeAction::class, $map['melee']);
        $this->assertSame(AttackAction::class, $map['attack'], 'abstract grouping types are types too');
        $this->assertCount(count(glob(__DIR__ . '/../../../src/Action/*Action.php')), $map);
    }

    public function testTheKeyIsTheLowercasedNameWithoutTheSuffix(): void
    {
        $this->assertSame('melee', ActionTypeDiscovery::typeOf(new MeleeAction()));
        $this->assertSame('melee', ActionTypeDiscovery::typeOf(MeleeAction::class));
        $this->assertSame('lifeloss', ActionTypeDiscovery::typeOf(new LifeLossOutcomeInstruction()));
        $this->assertSame(LifeLossOutcomeInstruction::class, ActionTypeDiscovery::typeMap(OutcomeInstruction::class)['lifeloss']);
    }
}
