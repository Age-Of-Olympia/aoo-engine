<?php

declare(strict_types=1);

namespace App\Tutorial;

/**
 * Pre-configured step templates for the admin editor's "Quick Start" picker.
 *
 * Each template is a bundle of editor-form field values the author typically
 * wants for a given step pattern (info, basic movement, targeted movement,
 * action use, UI panel, combat). Selecting one from the picker prefills the
 * form; fields not included in `config` stay empty for the author to fill.
 *
 * The editor renders the dropdown options from here AND bootstraps
 * `window.TUTORIAL_TEMPLATES` from the same array via `json_encode` — single
 * source of truth.
 */
final class TutorialTemplates
{
    /**
     * @var array<string, array{
     *     name: string,
     *     icon: string,
     *     description: string,
     *     config: array<string, scalar|bool>
     * }>
     */
    public const ALL = [
        'info_manual_advance' => [
            'name'        => 'Info Step (Manual Advance)',
            'icon'        => '📝',
            'description' => 'Informational step with "Suivant" button',
            'config'      => [
                'step_type'           => 'ui_interaction',
                'requires_validation' => true,
                'validation_type'     => 'ui_interaction',
                'element_clicked'     => 'tutorial_next',
                'interaction_mode'    => 'blocking',
                'tooltip_position'    => 'center',
                'show_delay'          => 0,
                'xp_reward'           => 0,
            ],
        ],
        'movement_basic' => [
            'name'        => 'Movement Step',
            'icon'        => '🚶',
            'description' => 'Make player move to any adjacent tile',
            'config'      => [
                'step_type'           => 'movement',
                'requires_validation' => true,
                'validation_type'     => 'any_movement',
                'interaction_mode'    => 'semi-blocking',
                'tooltip_position'    => 'right',
                'mvt_required'        => 1,
                'auto_restore'        => true,
                'xp_reward'           => 5,
            ],
        ],
        'movement_position' => [
            'name'        => 'Move to Specific Position',
            'icon'        => '🎯',
            'description' => 'Make player move to exact coordinates (fill target_x / target_y)',
            'config'      => [
                'step_type'           => 'movement',
                'requires_validation' => true,
                'validation_type'     => 'position',
                'interaction_mode'    => 'semi-blocking',
                'tooltip_position'    => 'right',
                'mvt_required'        => 1,
                'auto_restore'        => true,
                'xp_reward'           => 5,
            ],
        ],
        'action_use' => [
            'name'        => 'Use Action',
            'icon'        => '⚡',
            'description' => 'Make player use a specific action (fill action_name)',
            'config'      => [
                'step_type'           => 'action',
                'requires_validation' => true,
                'validation_type'     => 'action_used',
                'interaction_mode'    => 'semi-blocking',
                'tooltip_position'    => 'right',
                'pa_required'         => 1,
                'auto_restore'        => true,
                'xp_reward'           => 10,
            ],
        ],
        'ui_open_panel' => [
            'name'        => 'Open UI Panel',
            'icon'        => '🖼️',
            'description' => 'Make player open a specific panel (fill panel_id, target_selector)',
            'config'      => [
                'step_type'           => 'ui_interaction',
                'requires_validation' => true,
                'validation_type'     => 'ui_panel_opened',
                'interaction_mode'    => 'open',
                'tooltip_position'    => 'left',
                'xp_reward'           => 5,
            ],
        ],
        'combat_basic' => [
            'name'        => 'Combat Step',
            'icon'        => '⚔️',
            'description' => 'Make player attack an enemy',
            'config'      => [
                'step_type'           => 'combat',
                'requires_validation' => true,
                'validation_type'     => 'action_used',
                'action_name'         => 'attaquer',
                'interaction_mode'    => 'semi-blocking',
                'tooltip_position'    => 'right',
                'pa_required'         => 1,
                'auto_restore'        => true,
                'xp_reward'           => 15,
            ],
        ],
    ];

    /**
     * Dropdown options formatted as [templateId => "icon name"] for rendering
     * via renderSelectOptions().
     *
     * @return array<string, string>
     */
    public static function dropdownOptions(): array
    {
        $out = [];
        foreach (self::ALL as $id => $template) {
            $out[$id] = $template['icon'] . ' ' . $template['name'];
        }
        return $out;
    }
}
