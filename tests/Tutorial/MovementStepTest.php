<?php

namespace Tests\Tutorial;

use App\Tutorial\Steps\Movement\MovementStep;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * D4 Phase B — second step validator test in the Tutorial suite.
 *
 * MovementStep::validate() has five branches keyed by validation_type:
 *
 *   any_movement         — checks $data['action'] === 'move'
 *   specific_count       — checks $data['move_count'] >= required_moves
 *   movements_depleted   — DB-touching (TutorialHelper::loadActivePlayer)
 *   position             — DB-touching (TutorialHelper::loadActivePlayer + getCoords)
 *   adjacent_to_position — DB-touching (same)
 *
 * The first two branches are pure functions of the data array + config,
 * which is exactly the validation logic future refactors are most
 * likely to silently break (the DB-touching branches have an obvious
 * reason to be touched and tend to get manual smoke-tested; the pure
 * branches don't).
 *
 * The DB-touching branches require the Tests\Tutorial\Mock\* harness
 * deferred to D4 Phase C. They are exercised end-to-end by Cypress
 * tutorial-production-ready in the meantime.
 *
 * Pattern: instantiate-without-constructor + prime $config via
 * reflection, established in TutorialPlaceholderServiceTest /
 * TutorialFeatureFlagTest. No TutorialContext stub needed because
 * the tested branches don't read $this->context.
 */
class MovementStepTest extends TestCase
{
    /**
     * Build a MovementStep with the given $config primed via reflection.
     * Skips the constructor (which would require a real TutorialContext).
     */
    private function makeStepWithConfig(array $config): MovementStep
    {
        /** @var MovementStep $step */
        $step = (new ReflectionClass(MovementStep::class))
            ->newInstanceWithoutConstructor();

        $configProp = new ReflectionProperty($step, 'config');
        $configProp->setValue($step, $config);

        return $step;
    }

    #[Group('movement-step')]
    public function testRequiresValidationAlwaysTrue(): void
    {
        $step = $this->makeStepWithConfig([]);

        $this->assertTrue($step->requiresValidation());
    }

    #[Group('movement-step')]
    public function testAnyMovementAcceptsMoveAction(): void
    {
        $step = $this->makeStepWithConfig(['validation_type' => 'any_movement']);

        $this->assertTrue($step->validate(['action' => 'move']));
    }

    #[Group('movement-step')]
    public function testAnyMovementRejectsNonMoveAction(): void
    {
        $step = $this->makeStepWithConfig(['validation_type' => 'any_movement']);

        $this->assertFalse($step->validate(['action' => 'attack']));
    }

    #[Group('movement-step')]
    public function testAnyMovementIsTheDefaultValidationType(): void
    {
        // Empty config → defaults to any_movement. Pinning this matters
        // because the admin step editor allows leaving validation_type
        // unset and the rendered tutorial still has to behave.
        $step = $this->makeStepWithConfig([]);

        $this->assertTrue($step->validate(['action' => 'move']));
        $this->assertFalse($step->validate([]));
    }

    #[Group('movement-step')]
    public function testSpecificCountAcceptsExactMatch(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type' => 'specific_count',
            'required_moves'  => 3,
        ]);

        $this->assertTrue($step->validate(['move_count' => 3]));
    }

    #[Group('movement-step')]
    public function testSpecificCountAcceptsOvershoot(): void
    {
        // Documented contract: >= required_moves, not == . So a player
        // who moves more than required still satisfies the step.
        $step = $this->makeStepWithConfig([
            'validation_type' => 'specific_count',
            'required_moves'  => 2,
        ]);

        $this->assertTrue($step->validate(['move_count' => 5]));
    }

    #[Group('movement-step')]
    public function testSpecificCountRejectsUndershoot(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type' => 'specific_count',
            'required_moves'  => 3,
        ]);

        $this->assertFalse($step->validate(['move_count' => 2]));
    }

    #[Group('movement-step')]
    public function testSpecificCountDefaultsToOneRequiredMove(): void
    {
        // No required_moves in config → defaults to 1.
        $step = $this->makeStepWithConfig(['validation_type' => 'specific_count']);

        $this->assertTrue($step->validate(['move_count' => 1]));
        $this->assertFalse($step->validate(['move_count' => 0]));
    }

    #[Group('movement-step')]
    public function testSpecificCountTreatsMissingMoveCountAsZero(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type' => 'specific_count',
            'required_moves'  => 1,
        ]);

        $this->assertFalse($step->validate([]));
    }

    #[Group('movement-step')]
    public function testUnknownValidationTypeRejects(): void
    {
        // Defensive: a typo in the admin step editor must not silently
        // pass validation. The default branch returns false.
        $step = $this->makeStepWithConfig(['validation_type' => 'flying']);

        $this->assertFalse($step->validate(['action' => 'move', 'move_count' => 99]));
    }

    #[Group('movement-step')]
    public function testValidationHintForSpecificCountIncludesRequiredCount(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type' => 'specific_count',
            'required_moves'  => 4,
        ]);

        $this->assertSame(
            'Déplacez-vous 4 fois pour continuer.',
            $step->getValidationHint()
        );
    }

    #[Group('movement-step')]
    public function testValidationHintForPositionUsesPlaceholdersWhenCoordsMissing(): void
    {
        // No validation_params at all → hint substitutes "?" for the
        // missing coords rather than leaving the placeholder syntax
        // visible to the player.
        $step = $this->makeStepWithConfig(['validation_type' => 'position']);

        $this->assertSame(
            'Déplacez-vous sur la case (?,?) marquée en jaune.',
            $step->getValidationHint()
        );
    }

    #[Group('movement-step')]
    public function testValidationHintForAdjacentToPositionUsesConfigHintWhenProvided(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type' => 'adjacent_to_position',
            'validation_hint' => 'Approchez l\'arbre par le sud.',
        ]);

        $this->assertSame(
            'Approchez l\'arbre par le sud.',
            $step->getValidationHint()
        );
    }
}
