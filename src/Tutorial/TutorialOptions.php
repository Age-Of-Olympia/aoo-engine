<?php

declare(strict_types=1);

namespace App\Tutorial;

/**
 * Single source of truth for tutorial enum-like option lists.
 *
 * Both the admin UI (dropdowns in admin/tutorial-step-editor.php) and the
 * server-side validator (App\Service\TutorialStepValidationService) read from
 * here so they cannot drift. Keys are the stored values; values are the human
 * labels shown in the editor.
 */
final class TutorialOptions
{
    public const STEP_TYPES = [
        'info'           => 'Info',
        'welcome'        => 'Welcome',
        'dialog'         => 'Dialog',
        'movement'       => 'Movement',
        'movement_limit' => 'Movement Limit',
        'action'         => 'Action',
        'action_intro'   => 'Action Intro',
        'ui_interaction' => 'UI Interaction',
        'combat'         => 'Combat',
        'combat_intro'   => 'Combat Intro',
        'exploration'    => 'Exploration',
    ];

    public const VALIDATION_TYPES = [
        'any_movement'         => 'Any Movement',
        'movements_depleted'   => 'Movements Depleted',
        'position'             => 'Position (exact X, Y)',
        'adjacent_to_position' => 'Adjacent to Position',
        'action_used'          => 'Action Used',
        'ui_panel_opened'      => 'UI Panel Opened',
        'ui_element_hidden'    => 'UI Element Hidden',
        'ui_interaction'       => 'UI Interaction (element clicked)',
        'specific_count'       => 'Specific Count',
    ];

    public const INTERACTION_MODES = [
        'blocking'      => 'Blocking (full overlay)',
        'semi-blocking' => 'Semi-blocking (allow specific elements)',
        'open'          => 'Open (no overlay)',
    ];

    public const TOOLTIP_POSITIONS = [
        'top'           => 'Top',
        'bottom'        => 'Bottom',
        'left'          => 'Left',
        'right'         => 'Right',
        'center'        => 'Center (Middle)',
        'center-top'    => 'Center (Top)',
        'center-bottom' => 'Center (Bottom)',
    ];

    /**
     * @return list<string>
     */
    public static function stepTypeKeys(): array
    {
        return array_keys(self::STEP_TYPES);
    }

    /**
     * @return list<string>
     */
    public static function validationTypeKeys(): array
    {
        return array_keys(self::VALIDATION_TYPES);
    }

    /**
     * @return list<string>
     */
    public static function interactionModeKeys(): array
    {
        return array_keys(self::INTERACTION_MODES);
    }

    /**
     * @return list<string>
     */
    public static function tooltipPositionKeys(): array
    {
        return array_keys(self::TOOLTIP_POSITIONS);
    }
}
