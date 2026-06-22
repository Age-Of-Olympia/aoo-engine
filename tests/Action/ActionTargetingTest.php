<?php

namespace Tests\Action;

use App\Action\BuffAction;
use App\Action\MeleeAction;
use App\Entity\ActionOutcome;
use App\Service\Action\ActionTargeting;
use PHPUnit\Framework\TestCase;

final class ActionTargetingTest extends TestCase
{
    private ActionTargeting $targeting;

    protected function setUp(): void
    {
        $this->targeting = new ActionTargeting();
    }

    public function testBuffActionIsSelf(): void
    {
        $this->assertSame(ActionTargeting::SELF, $this->targeting->scopeOf(new BuffAction()));
    }

    public function testOnlySelfOutcomesIsSelf(): void
    {
        $action = $this->actionWith([true, true]);

        $this->assertSame(ActionTargeting::SELF, $this->targeting->scopeOf($action));
        $this->assertTrue($this->targeting->canTargetSelf($action));
        $this->assertFalse($this->targeting->canTargetOther($action));
    }

    public function testOnlyTargetOutcomesIsTarget(): void
    {
        $action = $this->actionWith([false]);

        $this->assertSame(ActionTargeting::TARGET, $this->targeting->scopeOf($action));
        $this->assertFalse($this->targeting->canTargetSelf($action));
        $this->assertTrue($this->targeting->canTargetOther($action));
    }

    public function testMixedOutcomesIsBoth(): void
    {
        $action = $this->actionWith([true, false]);

        $this->assertSame(ActionTargeting::BOTH, $this->targeting->scopeOf($action));
        $this->assertTrue($this->targeting->canTargetSelf($action));
        $this->assertTrue($this->targeting->canTargetOther($action));
    }

    public function testNoOutcomesDefaultsToTarget(): void
    {
        $this->assertSame(ActionTargeting::TARGET, $this->targeting->scopeOf(new MeleeAction()));
    }

    /**
     * @param list<bool> $applyToSelfFlags
     */
    private function actionWith(array $applyToSelfFlags): MeleeAction
    {
        $action = new MeleeAction();
        foreach ($applyToSelfFlags as $flag) {
            $action->addOutcome((new ActionOutcome())->setApplyToSelf($flag));
        }

        return $action;
    }
}
