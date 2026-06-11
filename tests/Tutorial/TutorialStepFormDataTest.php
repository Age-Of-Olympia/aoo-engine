<?php

namespace Tests\Tutorial;

use App\Tutorial\TutorialStepFormData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * TutorialStepFormData maps a failed step-save $_POST back into the
 * DB-row shapes the step editor renders, so the form repopulates
 * instead of wiping the admin's input. Numeric fields are cast (they
 * are echoed raw by the template) and enums whitelisted.
 */
class TutorialStepFormDataTest extends TestCase
{
    #[Group('tutorial-step-form-data')]
    public function testStepMapsCoreFields(): void
    {
        $row = TutorialStepFormData::step([
            'version' => '2.0.0',
            'step_id' => 'first_movement',
            'next_step' => 'gather_wood',
            'step_number' => '1.5',
            'step_type' => 'movement',
            'title' => 'Bouger',
            'text' => 'Cliquez sur une <strong>case</strong>',
            'xp_reward' => '15',
            'is_active' => '1',
        ]);

        $this->assertSame('2.0.0', $row['version']);
        $this->assertSame('first_movement', $row['step_id']);
        $this->assertSame('gather_wood', $row['next_step']);
        $this->assertSame(1.5, $row['step_number']);
        $this->assertSame('movement', $row['step_type']);
        $this->assertSame('Bouger', $row['title']);
        $this->assertSame('Cliquez sur une <strong>case</strong>', $row['text']);
        $this->assertSame(15, $row['xp_reward']);
        $this->assertSame(1, $row['is_active']);
    }

    #[Group('tutorial-step-form-data')]
    public function testStepCastsNonNumericRawEchoedFields(): void
    {
        $row = TutorialStepFormData::step([
            'step_number' => '<script>alert(1)</script>',
            'xp_reward' => 'abc',
        ]);

        $this->assertNull($row['step_number']);
        $this->assertSame(0, $row['xp_reward']);
        $this->assertSame(0, $row['is_active']);
    }

    #[Group('tutorial-step-form-data')]
    public function testUiMapsFieldsAndCastsNumerics(): void
    {
        $row = TutorialStepFormData::ui([
            'target_selector' => '#show-caracs',
            'target_description' => 'Bouton caracs',
            'highlight_selector' => '.case',
            'tooltip_position' => 'top',
            'interaction_mode' => 'semi-blocking',
            'blocked_click_message' => 'Pas encore',
            'show_delay' => '500',
            'auto_advance_delay' => '',
            'allow_manual_advance' => '1',
            'highlight_padding' => '10',
            'caracs_panel_state' => 'open',
        ]);

        $this->assertSame('#show-caracs', $row['target_selector']);
        $this->assertSame('Bouton caracs', $row['target_description']);
        $this->assertSame('.case', $row['highlight_selector']);
        $this->assertSame('top', $row['tooltip_position']);
        $this->assertSame('semi-blocking', $row['interaction_mode']);
        $this->assertSame('Pas encore', $row['blocked_click_message']);
        $this->assertSame(500, $row['show_delay']);
        $this->assertNull($row['auto_advance_delay']);
        $this->assertSame(1, $row['allow_manual_advance']);
        $this->assertSame(0, $row['auto_close_card']);
        $this->assertSame(10, $row['highlight_padding']);
        $this->assertSame('open', $row['caracs_panel_state']);
    }

    #[Group('tutorial-step-form-data')]
    public function testUiWhitelistsCaracsPanelState(): void
    {
        $row = TutorialStepFormData::ui(['caracs_panel_state' => '"><script>']);

        $this->assertNull($row['caracs_panel_state']);
    }

    #[Group('tutorial-step-form-data')]
    public function testValidationMapsFieldsIncludingNegativeCoordinates(): void
    {
        $row = TutorialStepFormData::validation([
            'requires_validation' => '1',
            'validation_type' => 'adjacent_to_position',
            'validation_hint' => 'Approchez-vous de l\'arbre',
            'target_x' => '-2',
            'target_y' => '0',
            'movement_count' => '',
            'panel_id' => 'ui-card',
            'element_selector' => '#inventory',
            'element_clicked' => '.btn-go',
            'action_name' => 'fouiller',
            'action_charges_required' => '2',
            'combat_required' => '1',
            'dialog_id' => 'npc_intro',
        ]);

        $this->assertSame(1, $row['requires_validation']);
        $this->assertSame('adjacent_to_position', $row['validation_type']);
        $this->assertSame('Approchez-vous de l\'arbre', $row['validation_hint']);
        $this->assertSame(-2, $row['target_x']);
        $this->assertSame(0, $row['target_y']);
        $this->assertNull($row['movement_count']);
        $this->assertSame('ui-card', $row['panel_id']);
        $this->assertSame('#inventory', $row['element_selector']);
        $this->assertSame('.btn-go', $row['element_clicked']);
        $this->assertSame('fouiller', $row['action_name']);
        $this->assertSame(2, $row['action_charges_required']);
        $this->assertSame(1, $row['combat_required']);
        $this->assertSame('npc_intro', $row['dialog_id']);
    }

    #[Group('tutorial-step-form-data')]
    public function testPrerequisitesMapsFields(): void
    {
        $row = TutorialStepFormData::prerequisites([
            'mvt_required' => '-1',
            'pa_required' => '',
            'auto_restore' => '1',
            'consume_movements' => '1',
            'spawn_enemy' => 'gobelin',
            'ensure_harvestable_tree_x' => '0',
            'ensure_harvestable_tree_y' => '1',
        ]);

        $this->assertSame(-1, $row['mvt_required']);
        $this->assertNull($row['pa_required']);
        $this->assertSame(1, $row['auto_restore']);
        $this->assertSame(1, $row['consume_movements']);
        $this->assertSame(0, $row['unlimited_mvt']);
        $this->assertSame(0, $row['unlimited_pa']);
        $this->assertSame('gobelin', $row['spawn_enemy']);
        $this->assertSame(0, $row['ensure_harvestable_tree_x']);
        $this->assertSame(1, $row['ensure_harvestable_tree_y']);
    }

    #[Group('tutorial-step-form-data')]
    public function testFeaturesMapsFields(): void
    {
        $row = TutorialStepFormData::features([
            'celebration' => '1',
            'redirect_delay' => '3000',
        ]);

        $this->assertSame(1, $row['celebration']);
        $this->assertSame(0, $row['show_rewards']);
        $this->assertSame(3000, $row['redirect_delay']);
    }

    #[Group('tutorial-step-form-data')]
    public function testSelectorListsSkipBlanksAndKeepShape(): void
    {
        $post = ['interactions' => ['.case', '', '#btn'], 'highlights' => ['']];

        $this->assertSame(
            [['selector' => '.case'], ['selector' => '#btn']],
            TutorialStepFormData::selectorList($post, 'interactions')
        );
        $this->assertSame([], TutorialStepFormData::selectorList($post, 'highlights'));
        $this->assertSame([], TutorialStepFormData::selectorList([], 'interactions'));
    }

    #[Group('tutorial-step-form-data')]
    public function testKeyValueListsPairKeysWithValues(): void
    {
        $post = [
            'context_keys' => ['set_mvt_limit', ''],
            'context_values' => ['3', 'ignored'],
            'prep_keys' => ['restore_mvt'],
            'prep_values' => [],
        ];

        $this->assertSame(
            [['context_key' => 'set_mvt_limit', 'context_value' => '3']],
            TutorialStepFormData::keyValueList($post, 'context_keys', 'context_values', 'context_key', 'context_value')
        );
        $this->assertSame(
            [['preparation_key' => 'restore_mvt', 'preparation_value' => '']],
            TutorialStepFormData::keyValueList($post, 'prep_keys', 'prep_values', 'preparation_key', 'preparation_value')
        );
    }
}
