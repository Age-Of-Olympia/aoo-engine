<?php

namespace App\View\Action;

use App\Interface\ActionInterface;
use App\Service\ActionService;
use App\Service\EffectService;

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
        $spans = [];$effectService = new EffectService();

        foreach ($this->actionService->getCostParts(null, $action) as$part) {
            /** @var mixed $rawText */
            $rawText =$part['text'];

            // 1. Cas où la valeur arrive sous forme de tableau PHP natif
            if (is_array($rawText)) {$val = 0;
                foreach ($rawText as$item) {
                    if (is_array($item) && isset($item[0], $item[1]) &&$item[0] === 'none') {
                        $val =$item[1];
                        break;
                    }
                }
                // On reconstruit une chaîne du type "6 PM"
                $rawText = $val . ' ' . strtoupper($part['trait']);
            } 
            // 2. Cas où la valeur est une chaîne contenant du JSON (ex: '[["maitre_lame",4],["none",6]] PM')
            elseif (is_string($rawText) && preg_match('/(\[\[.*?\]\])/', $rawText,$matches) === 1) {
                $jsonArr = json_decode($matches[1], true);
                if (is_array($jsonArr)) {
                    foreach ($jsonArr as$item) {
                        if (is_array($item) && isset($item[0], $item[1]) &&$item[0] === 'none') {
                            // On remplace le bloc JSON moche par la valeur (ex: 6)
                            $rawText = str_replace($matches[1], (string)$item[1],$rawText);
                            break;
                        }
                    }
                }
            }

            $text = htmlspecialchars((string)$rawText, ENT_QUOTES, 'UTF-8');
            $effect =$part['effect'] ?? null;
            if ($effect !== null && $effectService->exists($effect)) {
                // "(+1)" is the effect's stack count — show its icon, as the
                // WarSchool legend does ("coûts basés sur l'Imposture").
                $text = str_replace(
                    '(+1)',
                    '(<i class="ra ' . $effectService->getIcon($effect) . '"></i>+1)',
                    $text
                );
            }
            $hex = self::TRAIT_COLORS[$part['trait']] ?? null;
            $spans[] =$hex !== null
                ? '<span style="color: ' . $hex . ';">' . $text . '</span>'
                : $text;
        }

        return implode(', ', $spans);
    }
}