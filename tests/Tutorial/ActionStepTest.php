<?php

namespace Tests\Tutorial;

use App\Tutorial\Steps\Actions\ActionStep;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * D4 Phase B — third step validator test in the Tutorial suite.
 *
 * ActionStep is DB-free across all branches (after MR !331 removed
 * the predictable-path debug log writes), so this MR can pin the
 * complete public surface, not just a subset.
 *
 * Coverage:
 *   validate(action_used)        — required-action match incl. the
 *                                  attaquer ↔ melee/distance aliases
 *                                  + the new/legacy config-format
 *                                  fallback chain.
 *   validate(action_available)   — current placeholder behaviour
 *                                  (always true, "can be enhanced
 *                                  later" per the source comment).
 *   validate(unknown_type)       — pinned to TRUE — DOCUMENTED
 *                                  divergence from MovementStep,
 *                                  which returns false. See test
 *                                  comment for rationale.
 *   getValidationHint            — custom hint passthrough +
 *                                  per-type default hint formatting.
 *
 * Pattern: instantiate-without-constructor + prime $config via
 * reflection. Same as TutorialPlaceholderServiceTest /
 * TutorialFeatureFlagTest / MovementStepTest.
 */
class ActionStepTest extends TestCase
{
    private function makeStepWithConfig(array $config): ActionStep
    {
        /** @var ActionStep $step */
        $step = (new ReflectionClass(ActionStep::class))
            ->newInstanceWithoutConstructor();

        $configProp = new ReflectionProperty($step, 'config');
        $configProp->setValue($step, $config);

        return $step;
    }

    /* -------------------------------------------------------------- *
     *  validate() — action_used branch                               *
     * -------------------------------------------------------------- */

    #[Group('action-step')]
    public function testActionUsedAcceptsExactMatch(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'action_used',
            'validation_params' => ['action_name' => 'fouiller'],
        ]);

        $this->assertTrue($step->validate(['action_name' => 'fouiller']));
    }

    #[Group('action-step')]
    public function testActionUsedRejectsDifferentAction(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'action_used',
            'validation_params' => ['action_name' => 'fouiller'],
        ]);

        $this->assertFalse($step->validate(['action_name' => 'attaquer']));
    }

    #[Group('action-step')]
    public function testActionUsedPassesThroughWhenNoActionRequired(): void
    {
        // No action_name configured → step is a "any action will do"
        // pass-through. Documented in source as "No specific action
        // required". Pinned because removing this branch would silently
        // break tutorial steps that intentionally don't constrain the
        // action.
        $step = $this->makeStepWithConfig(['validation_type' => 'action_used']);

        $this->assertTrue($step->validate(['action_name' => 'anything']));
        $this->assertTrue($step->validate([]));
    }

    #[Group('action-step')]
    public function testActionUsedReadsActionNameFromLegacyRootKey(): void
    {
        // Legacy format (action_name at root of config). New format
        // is validation_params.action_name. Both must keep working
        // for backward compat with admin-saved step configs.
        $step = $this->makeStepWithConfig([
            'validation_type' => 'action_used',
            'action_name'     => 'prier',
        ]);

        $this->assertTrue($step->validate(['action_name' => 'prier']));
    }

    #[Group('action-step')]
    public function testActionUsedPrefersNewFormatOverLegacy(): void
    {
        // When BOTH formats are present (admin migrated a step but
        // didn't clean the legacy key), the NEW key wins. Pinned so
        // a future "use the legacy key for compat" change can't
        // silently regress migrated steps.
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'action_used',
            'validation_params' => ['action_name' => 'fouiller'],
            'action_name'       => 'prier', // stale legacy value
        ]);

        $this->assertTrue($step->validate(['action_name' => 'fouiller']));
        $this->assertFalse($step->validate(['action_name' => 'prier']));
    }

    /* -------------------------------------------------------------- *
     *  validate() — attaquer ↔ melee/distance aliasing                *
     *                                                                 *
     *  The button has data-action="attaquer" but the backend resolves *
     *  to melee or distance based on equipped weapon. The validation  *
     *  must accept either direction — config can spell it either way. *
     * -------------------------------------------------------------- */

    #[Group('action-step')]
    public function testAttaquerRequiredAcceptsMeleeUsed(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'action_used',
            'validation_params' => ['action_name' => 'attaquer'],
        ]);

        $this->assertTrue($step->validate(['action_name' => 'melee']));
    }

    #[Group('action-step')]
    public function testAttaquerRequiredAcceptsDistanceUsed(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'action_used',
            'validation_params' => ['action_name' => 'attaquer'],
        ]);

        $this->assertTrue($step->validate(['action_name' => 'distance']));
    }

    #[Group('action-step')]
    public function testMeleeRequiredAcceptsAttaquerUsed(): void
    {
        // Reverse direction of the alias: config spelled the specific
        // backend type ("melee") but the button still emits "attaquer".
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'action_used',
            'validation_params' => ['action_name' => 'melee'],
        ]);

        $this->assertTrue($step->validate(['action_name' => 'attaquer']));
    }

    #[Group('action-step')]
    public function testDistanceRequiredAcceptsAttaquerUsed(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'action_used',
            'validation_params' => ['action_name' => 'distance'],
        ]);

        $this->assertTrue($step->validate(['action_name' => 'attaquer']));
    }

    /* -------------------------------------------------------------- *
     *  validate() — action_available branch + unknown branch          *
     * -------------------------------------------------------------- */

    #[Group('action-step')]
    public function testActionAvailablePlaceholderAlwaysReturnsTrue(): void
    {
        // Documented as "would need to check player's available
        // actions from database. For now, assume validation passes
        // (can be enhanced later)". Pinning the placeholder so a
        // future implementation MR knows it's changing behaviour
        // (the test will fail, signaling the contract change).
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'action_available',
            'validation_params' => ['action_name' => 'fouiller'],
        ]);

        $this->assertTrue($step->validate([]));
    }

    #[Group('action-step')]
    public function testUnknownValidationTypePassesByDesign(): void
    {
        // ActionStep DIVERGES from MovementStep here: unknown types
        // PASS rather than fail. Source says "Unknown validation
        // type - pass by default" — opt-in safety for tutorial steps
        // whose validation_type was renamed in a step-id rename
        // refactor. Pinning the asymmetry so a "consistency" MR
        // cannot silently flip the default to false and break those
        // steps.
        $step = $this->makeStepWithConfig(['validation_type' => 'invented_type']);

        $this->assertTrue($step->validate(['action_name' => 'whatever']));
    }

    #[Group('action-step')]
    public function testDefaultsToActionUsedWhenTypeMissing(): void
    {
        // Missing validation_type → treated as action_used (the same
        // KISS pattern as MovementStep defaulting to any_movement).
        $step = $this->makeStepWithConfig([
            'validation_params' => ['action_name' => 'fouiller'],
        ]);

        $this->assertTrue($step->validate(['action_name' => 'fouiller']));
        $this->assertFalse($step->validate(['action_name' => 'autre']));
    }

    /* -------------------------------------------------------------- *
     *  getValidationHint                                              *
     * -------------------------------------------------------------- */

    #[Group('action-step')]
    public function testValidationHintUsesCustomWhenProvided(): void
    {
        // Custom hint short-circuits the per-type default formatting.
        $step = $this->makeStepWithConfig([
            'validation_type' => 'action_used',
            'validation_hint' => 'Cliquez sur le bouton vert.',
        ]);

        $this->assertSame('Cliquez sur le bouton vert.', $step->getValidationHint());
    }

    #[Group('action-step')]
    public function testValidationHintForActionUsedIncludesActionName(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'action_used',
            'validation_params' => ['action_name' => 'fouiller'],
        ]);

        $this->assertSame(
            "Utilisez l'action fouiller pour continuer.",
            $step->getValidationHint()
        );
    }

    #[Group('action-step')]
    public function testValidationHintForActionAvailableIncludesActionName(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'action_available',
            'validation_params' => ['action_name' => 'attaquer'],
        ]);

        $this->assertSame(
            "Vous devez avoir l'action attaquer disponible.",
            $step->getValidationHint()
        );
    }

    #[Group('action-step')]
    public function testValidationHintFallsBackToGenericWhenActionNameMissing(): void
    {
        // No action_name in config → the formatted hint substitutes
        // the literal word "action" rather than leaving the
        // placeholder syntax visible.
        $step = $this->makeStepWithConfig(['validation_type' => 'action_used']);

        $this->assertSame(
            "Utilisez l'action action pour continuer.",
            $step->getValidationHint()
        );
    }

    #[Group('action-step')]
    public function testValidationHintForUnknownTypeReturnsGeneric(): void
    {
        $step = $this->makeStepWithConfig(['validation_type' => 'invented_type']);

        $this->assertSame(
            'Effectuez l\'action requise pour continuer.',
            $step->getValidationHint()
        );
    }
}
