<?php

namespace Tests\Action;

use App\Action\BuffAction;
use App\Action\HealAction;
use App\Action\MeleeAction;
use App\Entity\ActionOutcome;
use App\Enum\OutcomeTarget;
use App\Service\Action\ActionTargeting;
use PHPUnit\Framework\TestCase;

final class ActionTargetingTest extends TestCase
{
    private ActionTargeting $targeting;

    protected function setUp(): void
    {
        $this->targeting = new ActionTargeting();
    }

    public function testABothOutcomeMakesTheActionUsableOnSelfAndOnATarget(): void
    {
        // The "coup précis" case: a support buff castable on yourself or an ally.
        $buff = new BuffAction();
        $buff->addOutcome($this->successOutcome(OutcomeTarget::Both));

        $this->assertSame(ActionTargeting::BOTH, $this->targeting->scopeOf($buff));
        $this->assertTrue($this->targeting->canTargetSelf($buff));
        $this->assertTrue($this->targeting->canTargetOther($buff));
    }

    public function testABuffWithASelfOutcomeStaysSelfOnly(): void
    {
        // The stealth-buff case (camouflage, discrétion): apply_to says self.
        $buff = new BuffAction();
        $buff->addOutcome($this->successOutcome(OutcomeTarget::Self));

        $this->assertSame(ActionTargeting::SELF, $this->targeting->scopeOf($buff));
        $this->assertFalse($this->targeting->canTargetOther($buff));
    }

    public function testHealWithATargetSuccessOutcomeTargetsTheTarget(): void
    {
        // The "barbier" case: a heal aimed at an ally must not be self-only.
        $heal = new HealAction();
        $heal->addOutcome($this->successOutcome(OutcomeTarget::Target));

        $this->assertSame(ActionTargeting::TARGET, $this->targeting->scopeOf($heal));
        $this->assertFalse($this->targeting->canTargetSelf($heal));
        $this->assertTrue($this->targeting->canTargetOther($heal));
    }

    public function testHealWithASelfSuccessOutcomeTargetsSelf(): void
    {
        $heal = new HealAction();
        $heal->addOutcome($this->successOutcome(OutcomeTarget::Self));

        $this->assertSame(ActionTargeting::SELF, $this->targeting->scopeOf($heal));
    }

    public function testAFailureOutcomeOnSelfDoesNotMakeAnAttackSelfTargetable(): void
    {
        // The "attaque sautée" case: target damage on success, self-damage on a
        // missed jump. The failure outcome must not add self-aim.
        $attack = new MeleeAction();
        $attack->addOutcome($this->successOutcome(OutcomeTarget::Target));
        $attack->addOutcome((new ActionOutcome())->setApplyTo(OutcomeTarget::Self)->setOnSuccess(false));

        $this->assertSame(ActionTargeting::TARGET, $this->targeting->scopeOf($attack));
        $this->assertFalse($this->targeting->canTargetSelf($attack));
    }

    public function testOnlySelfSuccessOutcomesIsSelf(): void
    {
        $action = new MeleeAction();
        $action->addOutcome($this->successOutcome(OutcomeTarget::Self));

        $this->assertSame(ActionTargeting::SELF, $this->targeting->scopeOf($action));
    }

    public function testMixedSuccessOutcomesIsBoth(): void
    {
        $action = new MeleeAction();
        $action->addOutcome($this->successOutcome(OutcomeTarget::Self));
        $action->addOutcome($this->successOutcome(OutcomeTarget::Target));

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

    public function testABuffWithNoSuccessOutcomesFallsBackToSelf(): void
    {
        // Missing data, not an override: a buff row with no success outcomes yet
        // still acts on its caster.
        $this->assertSame(ActionTargeting::SELF, $this->targeting->scopeOf(new BuffAction()));
    }

    private function successOutcome(OutcomeTarget $applyTo): ActionOutcome
    {
        return (new ActionOutcome())->setApplyTo($applyTo)->setOnSuccess(true);
    }
}
