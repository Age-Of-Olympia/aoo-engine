<?php

namespace App\Action\Combat;

/**
 * Renders a RollDetail to the tooltip markup shown in the action result. This is
 * the display half that used to be built inline inside the compute conditions'
 * roll math.
 */
final class RollDetailView
{
    public function renderActor(RollDetail $detail): string
    {
        $other = $this->effectsTooltip($detail->positiveEffect, $detail->negativeEffect) . $this->bonusTooltip($detail->bonus);
        $distance = $detail->distanceMalus ? ' - ' . $detail->distanceMalus . ' (Distance)' : '';
        $tooltip = $distance . ($distance ? ', ' . $other : $other) . self::advantageTooltip($detail->advantage);

        return 'Jet ' . $detail->name . ' = '
            . '<span style="text-decoration: underline;" flow="up" tooltip="' . $tooltip . '">' . $detail->total . '</span>';
    }

    public function renderTarget(RollDetail $detail): string
    {
        $totalOther = $detail->bonus + $detail->positiveEffect - $detail->negativeEffect + $detail->esquive;
        $tooltip = $this->effectsTooltip($detail->positiveEffect, $detail->negativeEffect)
            . $this->esquiveTooltip($detail->esquive)
            . $this->bonusTooltip($detail->bonus);

        $hasOther = $detail->esquive != 0 || $detail->bonus != 0 || $detail->negativeEffect != 0 || $detail->positiveEffect != 0;
        $other = $hasOther
            ? ($totalOther < 0 ? ' - ' . abs($totalOther) : ' + ' . $totalOther)
                . ' (<span style="text-decoration: underline;" flow="up" tooltip="' . $tooltip . '">Autre</span>)'
            : '';

        $malus = ($detail->malus != 0) ? ' - ' . $detail->malus . ' (Malus)' : '';
        $totalTxt = $detail->malus ? ' = ' . $detail->total : '';

        $advantage = self::advantageTooltip($detail->advantage);
        $rollSum = $advantage === ''
            ? (string) $detail->rollSum
            : '<span style="text-decoration: underline;" flow="up" tooltip="' . $advantage . '">' . $detail->rollSum . '</span>';

        return 'Jet ' . $detail->name . ' = ' . $rollSum . $other . $malus . $totalTxt;
    }

    /** Contenu de tooltip avantage/désavantage à concaténer, vide si rien n'a joué. */
    public static function advantageTooltip(?AdvantageRoll $roll): string
    {
        return ($roll !== null && $roll->isModified()) ? $roll->describe() : '';
    }

    private function effectsTooltip(int $positive, int $negative): string
    {
        if ($positive === 0 && $negative === 0) {
            return '';
        }

        return 'Effets :'
            . ($positive ? ' ' . $positive : '')
            . ($negative ? ' - ' . $negative : '')
            . ' ';
    }

    private function esquiveTooltip(int $esquive): string
    {
        if ($esquive === 0) {
            return '';
        }

        return 'Esquive : ' . ($esquive < 0 ? ' - ' . abs($esquive) : $esquive) . ' ';
    }

    private function bonusTooltip(int $bonus): string
    {
        return $bonus ? 'Bonus de compétence : ' . $bonus . ' ' : '';
    }
}
