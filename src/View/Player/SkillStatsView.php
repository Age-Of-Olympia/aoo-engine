<?php

namespace App\View\Player;

/**
 * Compétences statistics: a summary row, action/passive adoption (how many
 * players own each, with coverage bars), and a per-player distribution (how
 * many actions/passives each player holds). Built on the shared admin
 * components plus a little skill-stats.css for the coverage bars.
 *
 * @phpstan-type Adoption array{key:string, label:string, count:int}
 * @phpstan-type PlayerRow array{id:int, name:string, actions:int, passives:int}
 */
final class SkillStatsView
{
    /**
     * @param array{players:int, avgActions:float, avgPassives:float} $summary
     * @param array<int, array{key:string, label:string, count:int}> $actionAdoption
     * @param array<int, array{key:string, label:string, count:int}> $passiveAdoption
     * @param array<int, array{id:int, name:string, actions:int, passives:int}> $players
     */
    public function render(array $summary, array $actionAdoption, array $passiveAdoption, array $players): string
    {
        return '<h1 class="mb-3">Compétences — statistiques</h1>'
            . $this->summary($summary)
            . '<div class="stats-grid">'
            . $this->adoption('Adoption des actions', $actionAdoption, $summary['players'])
            . $this->adoption('Adoption des passifs', $passiveAdoption, $summary['players'])
            . '</div>'
            . $this->players($players);
    }

    /**
     * @param array{players:int, avgActions:float, avgPassives:float} $s
     */
    private function summary(array $s): string
    {
        return '<div class="stats-summary mb-3">'
            . $this->stat('Joueurs réels', (string) $s['players'])
            . $this->stat('Actions / joueur', number_format($s['avgActions'], 1, ',', ' '))
            . $this->stat('Passifs / joueur', number_format($s['avgPassives'], 1, ',', ' '))
            . '</div>';
    }

    private function stat(string $label, string $value): string
    {
        return '<div class="stats-stat"><span class="stats-stat-value">' . $this->esc($value) . '</span>'
            . '<span class="stats-stat-label">' . $this->esc($label) . '</span></div>';
    }

    /**
     * @param array<int, array{key:string, label:string, count:int}> $rows
     */
    private function adoption(string $title, array $rows, int $players): string
    {
        $unused = count(array_filter($rows, static fn($r) => $r['count'] === 0));

        $body = '';
        foreach ($rows as $row) {
            $pct = $players > 0 ? (int) round(($row['count'] / $players) * 100) : 0;
            $zero = $row['count'] === 0 ? ' stats-row--zero' : '';
            $body .= '<tr class="' . trim($zero) . '">'
                . '<td>' . $this->esc($row['label']) . ' <code>' . $this->esc($row['key']) . '</code></td>'
                . '<td class="stats-count">' . $row['count'] . '</td>'
                . '<td class="stats-bar-cell"><span class="stats-bar"><span class="stats-bar-fill" style="width:' . $pct . '%"></span></span></td>'
                . '</tr>';
        }

        return '<section class="card stats-card">'
            . '<div class="card-header">' . $this->esc($title)
            . ' <span class="badge badge-secondary">' . $unused . ' inutilisée(s)</span></div>'
            . '<div class="stats-scroll"><table class="table table-hover"><tbody>' . $body . '</tbody></table></div>'
            . '</section>';
    }

    /**
     * @param array<int, array{id:int, name:string, actions:int, passives:int}> $players
     */
    private function players(array $players): string
    {
        $body = '';
        foreach ($players as $p) {
            $body .= '<tr>'
                . '<td>' . (int) $p['id'] . '</td>'
                . '<td>' . $this->esc($p['name']) . '</td>'
                . '<td class="stats-count">' . (int) $p['actions'] . '</td>'
                . '<td class="stats-count">' . (int) $p['passives'] . '</td>'
                . '<td><a class="btn btn-sm btn-outline-primary" href="/admin/player-skills.php?id=' . (int) $p['id'] . '">Compétences</a></td>'
                . '</tr>';
        }

        return '<h2 class="h5 mt-4 mb-2">Répartition par joueur</h2>'
            . '<table class="table table-striped table-hover">'
            . '<thead><tr><th>Matricule</th><th>Nom</th><th>Actions</th><th>Passifs</th><th></th></tr></thead>'
            . '<tbody>' . $body . '</tbody></table>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
