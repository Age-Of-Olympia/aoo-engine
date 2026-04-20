<?php

declare(strict_types=1);

namespace App\Tutorial;

/**
 * Single source of truth for tutorial context-change and next-step-preparation
 * keys.
 *
 * These keys are the dispatch keys used by:
 *  - {@see \App\Tutorial\Steps\AbstractStep::applyContextChanges()} (CONTEXT_CHANGES)
 *  - {@see \App\Tutorial\TutorialContext::prepareForNextStep()} (NEXT_PREPARATIONS)
 *
 * The admin step editor and {@see \App\Service\TutorialStepValidationService}
 * read from here so authors cannot save a typo that the runtime silently
 * ignores (e.g. `restore_movements` instead of `restore_mvt`).
 */
final class TutorialContextKeys
{
    public const CONTEXT_CHANGES = [
        'unlimited_mvt'     => 'Unlimited movements (boolean)',
        'unlimited_actions' => 'Unlimited actions (boolean)',
        'consume_movements' => 'Consume MVT on move (boolean)',
        'set_mvt_limit'     => 'Set exact MVT limit (int, -1 for race max)',
        'set_action_limit'  => 'Set exact action limit (int)',
    ];

    public const NEXT_PREPARATIONS = [
        'restore_mvt'     => 'Restore MVT for next step (int, -1 for race max)',
        'restore_actions' => 'Restore actions for next step (int)',
        'spawn_enemy'     => 'Spawn enemy type for next step (string)',
        'spawn_item'      => 'Spawn item type for next step (string)',
        'remove_enemy'    => 'Remove enemy entity after step (id/type)',
        'remove_item'     => 'Remove item entity after step (id/type)',
    ];

    /**
     * @return list<string>
     */
    public static function contextChangeKeys(): array
    {
        return array_keys(self::CONTEXT_CHANGES);
    }

    /**
     * @return list<string>
     */
    public static function nextPreparationKeys(): array
    {
        return array_keys(self::NEXT_PREPARATIONS);
    }
}
