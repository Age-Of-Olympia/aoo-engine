<?php

namespace Tests\Tutorial;

use App\Tutorial\Steps\UIInteractionStep;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * D4 Phase B — fourth (and final) step validator test.
 *
 * UIInteractionStep validates client-reported UI events. It has six
 * validation_type branches plus the default-false safety branch:
 *
 *   ui_panel_opened       — restricted to 3 known panel names
 *   ui_button_clicked     — string equality
 *   ui_setting_changed    — setting name + value match
 *   ui_element_hidden     — element name + is_hidden flag
 *   ui_element_visible    — element name + is_visible flag
 *   ui_interaction        — element_clicked match (generic)
 *
 * All branches are pure functions of the data array + config (no DB
 * touch). The validation runs entirely against client-reported state,
 * which is exactly why the per-branch input-shape contract MUST be
 * pinned: a malicious client could otherwise spoof completion of any
 * step by sending fields that don't actually correspond to the
 * intended UI event.
 *
 * Same reflection-priming pattern as the other Phase B tests.
 */
class UIInteractionStepTest extends TestCase
{
    private function makeStepWithConfig(array $config): UIInteractionStep
    {
        /** @var UIInteractionStep $step */
        $step = (new ReflectionClass(UIInteractionStep::class))
            ->newInstanceWithoutConstructor();

        $configProp = new ReflectionProperty($step, 'config');
        $configProp->setValue($step, $config);

        return $step;
    }

    /* -------------------------------------------------------------- *
     *  ui_panel_opened — restricted-allowlist branch                  *
     * -------------------------------------------------------------- */

    #[Group('ui-interaction-step')]
    public function testPanelOpenedAcceptsCharacteristics(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'ui_panel_opened',
            'validation_params' => ['panel' => 'characteristics'],
        ]);

        $this->assertTrue($step->validate([
            'panel'         => 'characteristics',
            'panel_visible' => true,
        ]));
    }

    #[Group('ui-interaction-step')]
    public function testPanelOpenedAcceptsActions(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'ui_panel_opened',
            'validation_params' => ['panel' => 'actions'],
        ]);

        $this->assertTrue($step->validate(['panel' => 'actions', 'panel_visible' => true]));
    }

    #[Group('ui-interaction-step')]
    public function testPanelOpenedAcceptsInventory(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'ui_panel_opened',
            'validation_params' => ['panel' => 'inventory'],
        ]);

        $this->assertTrue($step->validate(['panel' => 'inventory', 'panel_visible' => true]));
    }

    #[Group('ui-interaction-step')]
    public function testPanelOpenedRejectsUnknownPanelNames(): void
    {
        // Allowlist of 3 panels. A malicious client cannot satisfy a
        // tutorial step by reporting an arbitrary panel name; nor can
        // a step config typo accidentally accept anything.
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'ui_panel_opened',
            'validation_params' => ['panel' => 'tutorial_complete'],
        ]);

        $this->assertFalse($step->validate([
            'panel'         => 'tutorial_complete',
            'panel_visible' => true,
        ]));
    }

    #[Group('ui-interaction-step')]
    public function testPanelOpenedRejectsWrongPanelInData(): void
    {
        // Required panel = characteristics, client reports actions →
        // false. Pins the strict-equality contract.
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'ui_panel_opened',
            'validation_params' => ['panel' => 'characteristics'],
        ]);

        $this->assertFalse($step->validate(['panel' => 'actions', 'panel_visible' => true]));
    }

    #[Group('ui-interaction-step')]
    public function testPanelOpenedRejectsHiddenPanel(): void
    {
        // Right panel name but panel_visible=false → must reject.
        // Otherwise a client could satisfy the step just by NAVIGATING
        // to a panel without actually opening it.
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'ui_panel_opened',
            'validation_params' => ['panel' => 'inventory'],
        ]);

        $this->assertFalse($step->validate(['panel' => 'inventory', 'panel_visible' => false]));
    }

    /* -------------------------------------------------------------- *
     *  ui_button_clicked                                              *
     * -------------------------------------------------------------- */

    #[Group('ui-interaction-step')]
    public function testButtonClickedAcceptsMatch(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'ui_button_clicked',
            'validation_params' => ['button' => 'commencer-tutoriel'],
        ]);

        $this->assertTrue($step->validate(['button' => 'commencer-tutoriel']));
    }

    #[Group('ui-interaction-step')]
    public function testButtonClickedRejectsMissingRequiredConfig(): void
    {
        // No required button → validation must NOT auto-pass (unlike
        // ActionStep's "no required action means pass". UI clicks
        // must always specify what was expected, otherwise any click
        // would satisfy the step).
        $step = $this->makeStepWithConfig(['validation_type' => 'ui_button_clicked']);

        $this->assertFalse($step->validate(['button' => 'anything']));
    }

    #[Group('ui-interaction-step')]
    public function testButtonClickedRejectsNonMatch(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'ui_button_clicked',
            'validation_params' => ['button' => 'next'],
        ]);

        $this->assertFalse($step->validate(['button' => 'cancel']));
    }

    /* -------------------------------------------------------------- *
     *  ui_setting_changed — two-key match (name AND value)            *
     * -------------------------------------------------------------- */

    #[Group('ui-interaction-step')]
    public function testSettingChangedRequiresBothNameAndValue(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'ui_setting_changed',
            'validation_params' => ['setting' => 'invisibleMode', 'value' => '0'],
        ]);

        $this->assertTrue($step->validate(['setting' => 'invisibleMode', 'setting_value' => '0']));
        // Wrong setting name:
        $this->assertFalse($step->validate(['setting' => 'noTrain', 'setting_value' => '0']));
        // Right name, wrong value:
        $this->assertFalse($step->validate(['setting' => 'invisibleMode', 'setting_value' => '1']));
    }

    /* -------------------------------------------------------------- *
     *  ui_element_hidden / ui_element_visible                         *
     * -------------------------------------------------------------- */

    #[Group('ui-interaction-step')]
    public function testElementHiddenAcceptsHiddenFlag(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'ui_element_hidden',
            'validation_params' => ['element' => '#welcome-banner'],
        ]);

        $this->assertTrue($step->validate(['element' => '#welcome-banner', 'is_hidden' => true]));
        $this->assertFalse($step->validate(['element' => '#welcome-banner', 'is_hidden' => false]));
    }

    #[Group('ui-interaction-step')]
    public function testElementVisibleAcceptsVisibleFlag(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'ui_element_visible',
            'validation_params' => ['element' => '#tutorial-tooltip'],
        ]);

        $this->assertTrue($step->validate(['element' => '#tutorial-tooltip', 'is_visible' => true]));
        $this->assertFalse($step->validate(['element' => '#tutorial-tooltip', 'is_visible' => false]));
    }

    /* -------------------------------------------------------------- *
     *  ui_interaction — generic element click                         *
     * -------------------------------------------------------------- */

    #[Group('ui-interaction-step')]
    public function testInteractionAcceptsMatchingElementClick(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'ui_interaction',
            'validation_params' => ['element_clicked' => 'tutorial_next'],
        ]);

        $this->assertTrue($step->validate(['element_clicked' => 'tutorial_next']));
    }

    #[Group('ui-interaction-step')]
    public function testInteractionRejectsMissingRequiredConfig(): void
    {
        // Same safety as ui_button_clicked: no required element → no
        // generic auto-pass.
        $step = $this->makeStepWithConfig(['validation_type' => 'ui_interaction']);

        $this->assertFalse($step->validate(['element_clicked' => 'whatever']));
    }

    /* -------------------------------------------------------------- *
     *  Defaults + safety                                              *
     * -------------------------------------------------------------- */

    #[Group('ui-interaction-step')]
    public function testDefaultsToPanelOpenedWhenTypeMissing(): void
    {
        // Missing validation_type → ui_panel_opened (the most-used
        // tutorial step type).
        $step = $this->makeStepWithConfig(['validation_params' => ['panel' => 'inventory']]);

        $this->assertTrue($step->validate(['panel' => 'inventory', 'panel_visible' => true]));
    }

    #[Group('ui-interaction-step')]
    public function testUnknownValidationTypeRejects(): void
    {
        // UIInteractionStep follows MovementStep's stricter posture
        // (false on unknown), NOT ActionStep's permissive one. Pinned
        // explicitly because the asymmetry surprises readers.
        $step = $this->makeStepWithConfig(['validation_type' => 'ui_telepathy']);

        $this->assertFalse($step->validate([]));
    }

    #[Group('ui-interaction-step')]
    public function testRequiresValidationDefaultsToTrue(): void
    {
        $step = $this->makeStepWithConfig([]);

        $this->assertTrue($step->requiresValidation());
    }

    #[Group('ui-interaction-step')]
    public function testRequiresValidationHonorsConfigOverride(): void
    {
        // Some informational tutorial steps don't need validation —
        // config can opt out.
        $step = $this->makeStepWithConfig(['requires_validation' => false]);

        $this->assertFalse($step->requiresValidation());
    }

    /* -------------------------------------------------------------- *
     *  getValidationHint                                              *
     * -------------------------------------------------------------- */

    #[Group('ui-interaction-step')]
    public function testHintForPanelOpenedIncludesPanelName(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'ui_panel_opened',
            'validation_params' => ['panel' => 'inventory'],
        ]);

        $this->assertSame('Ouvrez inventory pour continuer.', $step->getValidationHint());
    }

    #[Group('ui-interaction-step')]
    public function testHintForButtonClickedIncludesButtonName(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'ui_button_clicked',
            'validation_params' => ['button' => 'fouiller'],
        ]);

        $this->assertSame('Cliquez sur fouiller pour continuer.', $step->getValidationHint());
    }

    #[Group('ui-interaction-step')]
    public function testHintForElementVisibleUsesCustomWhenProvided(): void
    {
        // ui_element_visible and ui_interaction both let a custom hint
        // override the default — useful for player-friendly messages
        // ("Cherchez l'arbre marqué d'une étoile") rather than the
        // generic fallback.
        $step = $this->makeStepWithConfig([
            'validation_type' => 'ui_element_visible',
            'validation_hint' => "Attendez que l'arbre apparaisse.",
        ]);

        $this->assertSame("Attendez que l'arbre apparaisse.", $step->getValidationHint());
    }

    #[Group('ui-interaction-step')]
    public function testHintForElementVisibleFallsBackWhenNoCustom(): void
    {
        $step = $this->makeStepWithConfig(['validation_type' => 'ui_element_visible']);

        $this->assertSame("Attendez que l'élément apparaisse.", $step->getValidationHint());
    }

    #[Group('ui-interaction-step')]
    public function testHintForInteractionFallsBackWhenNoCustom(): void
    {
        $step = $this->makeStepWithConfig(['validation_type' => 'ui_interaction']);

        $this->assertSame(
            "Cliquez sur l'élément indiqué pour continuer.",
            $step->getValidationHint()
        );
    }
}
