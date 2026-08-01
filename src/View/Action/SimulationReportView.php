<?php

namespace App\View\Action;

use App\Service\Action\SimulationReport;
use App\View\ActionResultsView;

/**
 * Renders a SimulationReport: the aggregate distribution card plus the detailed
 * sample run (reusing ActionResultsView). Display only.
 */
final class SimulationReportView
{
    use EscapesHtmlTrait;

    public function __construct(private readonly SimulationReport $report)
    {
    }

    public function render(): string
    {
        $html = '<div class="sim-distribution">'
            . '<span class="sim-dist-runs">×' . $this->report->runs . '</span>'
            . '<span>Réussite <strong>' . round($this->report->successRate() * 100) . '%</strong></span>'
            . '<span>Touche <strong>' . round($this->report->hitRate() * 100) . '%</strong></span>'
            . '<span>Dégâts moyens (sur touche) <strong>' . round($this->report->averageDamageOnHit, 1) . '</strong></span>'
            . '</div>';

        if ($this->report->sample !== null) {
            $logs = $this->report->sample->getLogsArray();
            $html .= '<div class="sim-detail">';
            $html .= '<div class="card mt-3 sim-sample">'
                . '<div class="card-header"><h3 class="card-title">Exemple détaillé</h3></div>'
                . '<div class="card-body">'
                . (new ActionResultsView($this->report->sample))->getActionResults()
                . '<hr><strong>Logs</strong>'
                . '<p class="text-muted">Acteur : ' . $this->esc($logs['actor'] ?? '') . '</p>'
                . '<p class="text-muted">Cible : ' . $this->esc($logs['target'] ?? '') . '</p>'
                . '</div></div>';
            $html .= $this->diceBreakdown();
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Simulator-only panel: every die rolled during the detailed sample run, in
     * order, so you can see exactly what produced the totals above.
     */
    private function diceBreakdown(): string
    {
        if ($this->report->sampleRolls === []) {
            return '';
        }

        $rows = '';
        foreach ($this->report->sampleRolls as $roll) {
            $faces = array_map('intval', $roll['faces']);
            $rows .= '<li><span class="sim-dice-spec">' . count($faces) . 'd' . (int) $roll['sides'] . '</span>'
                . ' → [' . implode(', ', $faces) . '] = <strong>' . array_sum($faces) . '</strong></li>';
        }

        return '<div class="card mt-3 sim-dice">'
            . '<div class="card-header"><h3 class="card-title">Calcul des dés (exemple)</h3></div>'
            . '<div class="card-body">'
            . '<p class="text-muted">Tous les jets de l\'exemple détaillé, dans l\'ordre (acteur puis cible).</p>'
            . '<ol class="sim-dice-list">' . $rows . '</ol>'
            . '</div></div>';
    }

    public static function unavailable(string $message): string
    {
        $escaped = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        return '<div class="alert alert-warning mt-3">Cette action ne peut pas être entièrement simulée '
            . '(elle dépend de l\'état réel du monde, ex. la carte) : ' . $escaped . '</div>';
    }

}
