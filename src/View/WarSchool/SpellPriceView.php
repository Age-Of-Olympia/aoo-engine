<?php

namespace App\View\WarSchool;

use App\Service\WarSchool\SpellPriceService;
use App\Trait\EscapesHtmlTrait;

/**
 * La facture d'un sort, en ressources : une ligne par ressource due, chacune
 * avec son menu de choix.
 *
 * Le barème dit « une rare », jamais « de l'étain » — le menu porte donc
 * toute la famille, et ce que le joueur en possède, pour qu'il décide sur
 * pièce. Ce qu'il n'a pas reste visible mais inchoisissable : la famille se
 * lit en entier, c'est ce qui dit où aller chercher.
 */
final class SpellPriceView
{
    use EscapesHtmlTrait;

    /**
     * @param array<string, int> $owned objet => nombre porté
     */
    public function render(string $skillName, int $level, array $owned): string
    {
        $lines = '';
        foreach (SpellPriceService::slots($level) as $index => $tier) {
            $lines .= '<label class="ws-cost-line">'
                . '<span class="ws-cost-tier">' . $this->esc(SpellPriceService::TIER_LABELS[$tier]) . '</span>'
                . $this->select($tier, $owned, $skillName, $index)
                . '</label>';
        }

        return '<div class="ws-cost">' . $lines . '</div>';
    }

    /** @param array<string, int> $owned */
    private function select(string $tier, array $owned, string $skillName, int $index): string
    {
        $labels = SpellPriceService::labels();
        $family = SpellPriceService::TIERS[$tier];

        /* Présélection sur la ressource la mieux pourvue : le choix par
           défaut est celui qui a le plus de chances d'être payable, et le
           joueur n'a à toucher au menu que s'il veut préserver un stock. */
        $richest = null;
        foreach ($family as $name) {
            if (($owned[$name] ?? 0) > ($owned[$richest] ?? 0)) {
                $richest = $name;
            }
        }

        $options = '';
        foreach ($family as $name) {
            $n = $owned[$name] ?? 0;
            $options .= '<option value="' . $this->esc($name) . '"'
                . ($n < 1 ? ' disabled' : '')
                . ($name === $richest ? ' selected' : '')
                . '>' . $this->esc($labels[$name] ?? $name) . ' (' . $n . ')</option>';
        }

        return '<select class="ws-resource" data-skill="' . $this->esc($skillName) . '"'
            . ' aria-label="' . $this->esc(SpellPriceService::TIER_LABELS[$tier]) . ' ' . ($index + 1) . '">'
            . $options
            . '</select>';
    }
}