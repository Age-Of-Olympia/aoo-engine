<?php

declare(strict_types=1);

namespace Tests\Tutorial;

use App\Tutorial\TutorialOptions;
use App\Tutorial\TutorialTemplates;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Characterization test for TutorialTemplates.
 *
 * Pins the template ids and — critically — asserts every template's `config`
 * references only values that the rest of the tutorial system already knows
 * about (step types, validation types, interaction modes, tooltip positions).
 * A typo like `step_type => 'movmnt'` in a template would silently misconfigure
 * every step authored from it; this test catches that at CI time.
 */
#[Group('tutorial')]
class TutorialTemplatesTest extends TestCase
{
    public function testTemplateIdsArePinned(): void
    {
        self::assertSame(
            [
                'info_manual_advance',
                'movement_basic',
                'movement_position',
                'action_use',
                'ui_open_panel',
                'combat_basic',
            ],
            array_keys(TutorialTemplates::ALL)
        );
    }

    public function testEveryTemplateHasRequiredMetadataAndNonEmptyConfig(): void
    {
        foreach (TutorialTemplates::ALL as $id => $tpl) {
            self::assertNotSame('', trim($tpl['name']), $id . ' name');
            self::assertNotSame('', trim($tpl['icon']), $id . ' icon');
            self::assertNotSame('', trim($tpl['description']), $id . ' description');
            self::assertGreaterThan(0, count($tpl['config']), $id . ' config must not be empty');
        }
    }

    public function testTemplateStepTypesAreKnown(): void
    {
        $valid = TutorialOptions::stepTypeKeys();
        foreach (TutorialTemplates::ALL as $id => $tpl) {
            self::assertContains(
                $tpl['config']['step_type'],
                $valid,
                "Template '$id' references unknown step_type '{$tpl['config']['step_type']}'"
            );
        }
    }

    public function testTemplateValidationTypesAreKnown(): void
    {
        $valid = TutorialOptions::validationTypeKeys();
        foreach (TutorialTemplates::ALL as $id => $tpl) {
            self::assertContains(
                $tpl['config']['validation_type'],
                $valid,
                "Template '$id' references unknown validation_type '{$tpl['config']['validation_type']}'"
            );
        }
    }

    public function testTemplateInteractionModesAreKnown(): void
    {
        $valid = TutorialOptions::interactionModeKeys();
        foreach (TutorialTemplates::ALL as $id => $tpl) {
            self::assertContains(
                $tpl['config']['interaction_mode'],
                $valid,
                "Template '$id' references unknown interaction_mode '{$tpl['config']['interaction_mode']}'"
            );
        }
    }

    public function testTemplateTooltipPositionsAreKnown(): void
    {
        $valid = TutorialOptions::tooltipPositionKeys();
        foreach (TutorialTemplates::ALL as $id => $tpl) {
            self::assertContains(
                $tpl['config']['tooltip_position'],
                $valid,
                "Template '$id' references unknown tooltip_position '{$tpl['config']['tooltip_position']}'"
            );
        }
    }

    public function testDropdownOptionsMatchAllTemplates(): void
    {
        $options = TutorialTemplates::dropdownOptions();
        self::assertSame(array_keys(TutorialTemplates::ALL), array_keys($options));
        foreach (TutorialTemplates::ALL as $id => $tpl) {
            $expected = $tpl['icon'] . ' ' . $tpl['name'];
            self::assertSame($expected, $options[$id]);
        }
    }

    public function testJsonEncodeDoesNotBlowUp(): void
    {
        // The editor bootstraps `window.TUTORIAL_TEMPLATES` from
        // json_encode(TutorialTemplates::ALL) so invalid UTF-8 or a non-
        // serializable value would break the picker at runtime.
        $encoded = json_encode(TutorialTemplates::ALL, JSON_THROW_ON_ERROR);
        self::assertNotFalse($encoded);
        self::assertJson($encoded);
        $decoded = json_decode($encoded, true);
        self::assertSame(array_keys(TutorialTemplates::ALL), array_keys($decoded));
    }
}
