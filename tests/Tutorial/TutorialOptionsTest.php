<?php

declare(strict_types=1);

namespace Tests\Tutorial;

use App\Service\TutorialStepValidationService;
use App\Tutorial\TutorialOptions;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Characterization test for TutorialOptions.
 *
 * Pins the exact option keys for step types, validation types, interaction
 * modes, and tooltip positions. Admin editor dropdowns and server-side
 * validation both read from TutorialOptions, so a drift here (dropped value,
 * typo, misspelled rename) fails in CI instead of shipping a silent UX bug.
 *
 * `specific_count` is explicitly pinned: it was present in the validator but
 * missing from the editor dropdown prior to this refactor.
 */
#[Group('tutorial')]
class TutorialOptionsTest extends TestCase
{
    public function testStepTypeKeysArePinned(): void
    {
        self::assertSame(
            [
                'info',
                'welcome',
                'dialog',
                'movement',
                'movement_limit',
                'action',
                'action_intro',
                'ui_interaction',
                'combat',
                'combat_intro',
                'exploration',
            ],
            TutorialOptions::stepTypeKeys()
        );
    }

    public function testValidationTypeKeysArePinnedAndIncludeSpecificCount(): void
    {
        $keys = TutorialOptions::validationTypeKeys();

        self::assertSame(
            [
                'any_movement',
                'movements_depleted',
                'position',
                'adjacent_to_position',
                'action_used',
                'ui_panel_opened',
                'ui_element_hidden',
                'ui_interaction',
                'specific_count',
            ],
            $keys
        );

        self::assertContains(
            'specific_count',
            $keys,
            'specific_count must stay in the dropdown — it was silently missing pre-refactor.'
        );
    }

    public function testInteractionModeKeysArePinned(): void
    {
        self::assertSame(
            ['blocking', 'semi-blocking', 'open'],
            TutorialOptions::interactionModeKeys()
        );
    }

    public function testTooltipPositionKeysArePinned(): void
    {
        self::assertSame(
            ['top', 'bottom', 'left', 'right', 'center', 'center-top', 'center-bottom'],
            TutorialOptions::tooltipPositionKeys()
        );
    }

    public function testEveryOptionHasANonEmptyLabel(): void
    {
        $maps = [
            'STEP_TYPES'         => TutorialOptions::STEP_TYPES,
            'VALIDATION_TYPES'   => TutorialOptions::VALIDATION_TYPES,
            'INTERACTION_MODES'  => TutorialOptions::INTERACTION_MODES,
            'TOOLTIP_POSITIONS'  => TutorialOptions::TOOLTIP_POSITIONS,
        ];

        foreach ($maps as $name => $map) {
            foreach ($map as $value => $label) {
                self::assertNotSame('', trim($label), $name . '[' . $value . '] must have a label');
            }
        }
    }

    public function testValidationServiceAcceptsEveryAdvertisedStepType(): void
    {
        $service = new TutorialStepValidationService();
        foreach (TutorialOptions::stepTypeKeys() as $key) {
            self::assertSame($key, $service->validateStepType($key));
        }
    }

    public function testValidationServiceAcceptsEveryAdvertisedValidationType(): void
    {
        $service = new TutorialStepValidationService();
        foreach (TutorialOptions::validationTypeKeys() as $key) {
            self::assertSame($key, $service->validateValidationType($key));
        }
    }

    public function testValidationServiceAcceptsEveryAdvertisedInteractionMode(): void
    {
        $service = new TutorialStepValidationService();
        foreach (TutorialOptions::interactionModeKeys() as $key) {
            self::assertSame($key, $service->validateInteractionMode($key));
        }
    }

    public function testValidationServiceAcceptsEveryAdvertisedTooltipPosition(): void
    {
        $service = new TutorialStepValidationService();
        foreach (TutorialOptions::tooltipPositionKeys() as $key) {
            self::assertSame($key, $service->validateTooltipPosition($key));
        }
    }

    public function testValidationServiceRejectsUnknownStepType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new TutorialStepValidationService())->validateStepType('not_a_real_type');
    }
}
