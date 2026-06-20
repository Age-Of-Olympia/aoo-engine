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
    public function __construct(private readonly SimulationReport $report)
    {
    }

    public function render(): string
    {
        $html = '<div class="card mt-3" style="max-width:560px">'
            . '<div class="card-header"><h3 class="card-title">Distribution (×' . $this->report->runs . ')</h3></div>'
            . '<div class="card-body"><p>'
            . 'Réussite : <strong>' . round($this->report->successRate() * 100) . '%</strong> &nbsp; '
            . 'Touche : <strong>' . round($this->report->hitRate() * 100) . '%</strong> &nbsp; '
            . 'Dégâts moyens (sur touche) : <strong>' . round($this->report->averageDamageOnHit, 1) . '</strong>'
            . '</p></div></div>';

        if ($this->report->sample !== null) {
            $logs = $this->report->sample->getLogsArray();
            $html .= '<div class="card mt-3" style="max-width:560px">'
                . '<div class="card-header"><h3 class="card-title">Exemple détaillé</h3></div>'
                . '<div class="card-body">'
                . (new ActionResultsView($this->report->sample))->getActionResults()
                . '<hr><strong>Logs</strong>'
                . '<p class="text-muted">Acteur : ' . $this->esc($logs['actor'] ?? '') . '</p>'
                . '<p class="text-muted">Cible : ' . $this->esc($logs['target'] ?? '') . '</p>'
                . '</div></div>';
        }

        return $html;
    }

    public static function unavailable(string $message): string
    {
        $escaped = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        return '<div class="alert alert-warning mt-3">Cette action ne peut pas être entièrement simulée '
            . '(elle dépend de l\'état réel du monde, ex. la carte) : ' . $escaped . '</div>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
