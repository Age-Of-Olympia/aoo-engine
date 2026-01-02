/**
 * Tutorial Step Templates
 * Pre-configured patterns for common tutorial steps
 */

const TUTORIAL_TEMPLATES = {
    'info_manual_advance': {
        name: 'Info Step (Manual Advance)',
        icon: '📝',
        description: 'Informational step with "Suivant" button',
        config: {
            step_type: 'ui_interaction',
            requires_validation: true,
            validation_type: 'ui_interaction',
            element_clicked: 'tutorial_next',
            interaction_mode: 'blocking',
            tooltip_position: 'center',
            show_delay: 0,
            xp_reward: 0
        }
    },

    'movement_basic': {
        name: 'Movement Step',
        icon: '🚶',
        description: 'Make player move to any adjacent tile',
        config: {
            step_type: 'movement',
            requires_validation: true,
            validation_type: 'any_movement',
            interaction_mode: 'semi-blocking',
            tooltip_position: 'right',
            mvt_required: 1,
            auto_restore: true,
            xp_reward: 5
        }
    },

    'movement_position': {
        name: 'Move to Specific Position',
        icon: '🎯',
        description: 'Make player move to exact coordinates',
        config: {
            step_type: 'movement',
            requires_validation: true,
            validation_type: 'position',
            interaction_mode: 'semi-blocking',
            tooltip_position: 'right',
            mvt_required: 1,
            auto_restore: true,
            xp_reward: 5
            // User must fill: target_x, target_y
        }
    },

    'action_use': {
        name: 'Use Action',
        icon: '⚡',
        description: 'Make player use a specific action',
        config: {
            step_type: 'action',
            requires_validation: true,
            validation_type: 'action_used',
            interaction_mode: 'semi-blocking',
            tooltip_position: 'right',
            pa_required: 1,
            auto_restore: true,
            xp_reward: 10
            // User must fill: action_name
        }
    },

    'ui_open_panel': {
        name: 'Open UI Panel',
        icon: '🖼️',
        description: 'Make player open a specific panel',
        config: {
            step_type: 'ui_interaction',
            requires_validation: true,
            validation_type: 'ui_panel_opened',
            interaction_mode: 'open',
            tooltip_position: 'left',
            xp_reward: 5
            // User must fill: panel_id, target_selector
        }
    },

    'combat_basic': {
        name: 'Combat Step',
        icon: '⚔️',
        description: 'Make player attack an enemy',
        config: {
            step_type: 'combat',
            requires_validation: true,
            validation_type: 'action_used',
            action_name: 'attaquer',
            interaction_mode: 'semi-blocking',
            tooltip_position: 'right',
            pa_required: 1,
            auto_restore: true,
            xp_reward: 15
        }
    }
};
