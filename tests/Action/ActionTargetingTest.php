<?php

namespace Tests\Action;

use App\Action\BuffAction;
use App\Action\HealAction;
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

    public function testPureBuffIsSelfEvenWithATargetOutcome(): void
    {
        // Self-buffs are stored with apply_to_self = 0; the buff rule overrides it.
        $buff = new BuffAction();
        $buff->addOutcome($this->successOutcome(false));

        $this->assertSame(ActionTargeting::SELF, $this->targeting->scopeOf($buff));
    }

    public function testHealWithATargetSuccessOutcomeTargetsTheTarget(): void
    {
        // The "barbier" case: a heal aimed at an ally must not be self-only.
        $heal = new HealAction();
        $heal->addOutcome($this->successOutcome(false));

        $this->assertSame(ActionTargeting::TARGET, $this->targeting->scopeOf($heal));
        $this->assertFalse($this->targeting->canTargetSelf($heal));
        $this->assertTrue($this->targeting->canTargetOther($heal));
    }

    public function testHealWithASelfSuccessOutcomeTargetsSelf(): void
    {
        $heal = new HealAction();
        $heal->addOutcome($this->successOutcome(true));

        $this->assertSame(ActionTargeting::SELF, $this->targeting->scopeOf($heal));
    }

    public function testAFailureOutcomeOnSelfDoesNotMakeAnAttackSelfTargetable(): void
    {
        // The "attaque sautée" case: target damage on success, self-damage on a
        // missed jump. The failure outcome must not add self-aim.
        $attack = new MeleeAction();
        $attack->addOutcome($this->successOutcome(false));
        $attack->addOutcome((new ActionOutcome())->setApplyToSelf(true)->setOnSuccess(false));

        $this->assertSame(ActionTargeting::TARGET, $this->targeting->scopeOf($attack));
        $this->assertFalse($this->targeting->canTargetSelf($attack));
    }

    public function testOnlySelfSuccessOutcomesIsSelf(): void
    {
        $action = new MeleeAction();
        $action->addOutcome($this->successOutcome(true));

        $this->assertSame(ActionTargeting::SELF, $this->targeting->scopeOf($action));
    }

    public function testMixedSuccessOutcomesIsBoth(): void
    {
        $action = new MeleeAction();
        $action->addOutcome($this->successOutcome(true));
        $action->addOutcome($this->successOutcome(false));

        $this->assertSame(ActionTargeting::BOTH, $this->targeting->scopeOf($action));
    }

    public function testNoSuccessOutcomesIsNoneForANonBuff(): void
    {
        // A no-success-outcome, non-buff action (e.g. a technique modifier) must
        // not surface a button anywhere.
        $this->assertSame(ActionTargeting::NONE, $this->targeting->scopeOf(new MeleeAction()));
        $this->assertFalse($this->targeting->canTargetSelf(new MeleeAction()));
        $this->assertFalse($this->targeting->canTargetOther(new MeleeAction()));
    }

    private function successOutcome(bool $applyToSelf): ActionOutcome
    {
        return (new ActionOutcome())->setApplyToSelf($applyToSelf)->setOnSuccess(true);
    }
}
