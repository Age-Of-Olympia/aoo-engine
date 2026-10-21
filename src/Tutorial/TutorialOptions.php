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
        'info'           => 'Information',
        'welcome'        => 'Accueil',
        'dialog'         => 'Dialogue',
        'movement'       => 'Déplacement',
        'movement_limit' => 'Limite de déplacement',
        'action'         => 'Action',
        'action_intro'   => 'Présentation d\'une action',
        'ui_interaction' => 'Clic dans l\'interface',
        'combat'         => 'Combat',
        'combat_intro'   => 'Présentation du combat',
        'exploration'    => 'Exploration',
    ];

    public const VALIDATION_TYPES = [
        'any_movement'         => 'Un déplacement quelconque',
        'movements_depleted'   => 'Mouvements épuisés',
        'position'             => 'Position exacte (X, Y)',
        'adjacent_to_position' => 'À côté d\'une position',
        'action_used'          => 'Action utilisée',
        'ui_panel_opened'      => 'Panneau ouvert',
        'ui_element_hidden'    => 'Élément masqué',
        'ui_interaction'       => 'Clic sur un élément',
        'specific_count'       => 'Nombre de déplacements',
    ];

    public const INTERACTION_MODES = [
        'blocking'      => 'Bloquant (voile sur tout l\'écran)',
        'semi-blocking' => 'Semi-bloquant (certains éléments cliquables)',
        'open'          => 'Libre (sans voile)',
    ];

    public const TOOLTIP_POSITIONS = [
        'top'           => 'En haut',
        'bottom'        => 'En bas',
        'left'          => 'À gauche',
        'right'         => 'À droite',
        'center'        => 'Au centre',
        'center-top'    => 'Au centre, en haut',
        'center-bottom' => 'Au centre, en bas',
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
