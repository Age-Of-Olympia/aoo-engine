<?php

namespace Tests\Tutorial;

use App\Tutorial\Steps\TutorialStep;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * The step_type picks the validation family, whatever validation_type says.
 */
class TutorialStepFamilyTest extends TestCase
{
    /** @param array<string, mixed> $config */
    private function step(string $stepType, array $config): TutorialStep
    {
        $step = (new ReflectionClass(TutorialStep::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($step, 'config'))->setValue($step, $config);
        (new ReflectionProperty($step, 'stepType'))->setValue($step, $stepType);

        return $step;
    }

    public function testATextOnlyTypeIgnoresItsValidationType(): void
    {
        // Steps 27 (combat) and 3 (info) are configured this way on prod
        $combat = $this->step('combat', ['validation_type' => 'action_used', 'action_name' => 'melee']);
        $info = $this->step('info', ['validation_type' => 'ui_panel_opened', 'validation_params' => ['panel' => 'actions']]);

        $this->assertTrue($combat->validate(['action_name' => 'fouiller']));
        $this->assertTrue($info->validate([]));
    }

    public function testEachFamilyHasItsOwnValidationDefault(): void
    {
        $this->assertTrue($this->step('movement_intro', ['requires_validation' => false])->requiresValidation());
        $this->assertTrue($this->step('ui_interaction', [])->requiresValidation());
        $this->assertFalse($this->step('action', [])->requiresValidation());
        $this->assertFalse($this->step('dialog', [])->requiresValidation());
    }

    public function testTheActionFamilyChecksTheActionUsed(): void
    {
        $step = $this->step('action', ['validation_type' => 'action_used', 'action_name' => 'melee']);

        $this->assertFalse($step->validate(['action_name' => 'fouiller']));
        $this->assertTrue($step->validate(['action_name' => 'melee']));
    }
}
