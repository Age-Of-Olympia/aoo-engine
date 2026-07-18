<?php

namespace App\View\Action;

use App\Interface\ActionInterface;
use App\Service\ActionService;

/**
 * The single place that turns an action's cost into HTML. The cost comes from
 * {@see ActionService::getCostParts()} — derived from the action's conditions,
 * the same parameters the executor charges — instead of the hand-maintained
 * `actions.cost` column, so a cost changed in the action editor shows up
 * everywhere for free. Each resource keeps the colour players already know
 * from the WarSchool pages.
 */
final class ActionCostView
{
    /** Resource colour per carac key; anything else renders uncoloured. */
    private const TRAIT_COLORS = [
        'a'   => '#8e44ad',
        'pm'  => '#2980b9',
        'mvt' => '#27ae60',
        'pv'  => '#c0392b',
    ];

    public function __construct(private ActionService $actionService)
    {
    }

    public function forAction(ActionInterface $action): string
    {
        $spans = [];
        foreach ($this->actionService->getCostParts(null, $action) as $part) {
            $text = htmlspecialchars($part['text'], ENT_QUOTES, 'UTF-8');
            $effect = $part['effect'] ?? null;
            if ($effect !== null && (new \App\Service\EffectService())->exists($effect)) {
                // "(+1)" is the effect's stack count — show its icon, as the
                // WarSchool legend does ("coûts basés sur l'Imposture").
                $text = str_replace(
                    '(+1)',
                    '(<i class="ra ' . (new \App\Service\EffectService())->getIcon($effect) . '"></i>+1)',
                    $text
                );
            }
            $hex = self::TRAIT_COLORS[$part['trait']] ?? null;
            $spans[] = $hex !== null
                ? '<span style="color: ' . $hex . ';">' . $text . '</span>'
                : $text;
        }

        return implode(', ', $spans);
    }
}
