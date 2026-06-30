<?php

namespace App\View\Player;

/**
 * The skills entry point: search a character by matricule or name, then open
 * their skills. Uses the shared admin components (search form + striped table)
 * so it reads like every other admin list page (see admin/actions.php).
 */
final class PlayerSearchView
{
    /**
     * @param array<int, array{id:int, name:string, race:string, player_type:string, xp:int}> $players
     */
    public function render(string $term, array $players): string
    {
        return '<h1 class="mb-3">Compétences des joueurs</h1>'
            . $this->searchForm($term)
            . $this->results($term, $players);
    }

    private function searchForm(string $term): string
    {
        return '<form method="get" action="/admin/players.php" class="form-inline mb-3" role="search">'
            . '<input type="text" name="q" class="form-control mr-2" style="min-width:18rem"'
            . ' placeholder="Matricule ou nom…" value="' . $this->esc($term) . '"'
            . ' autofocus autocomplete="off" aria-label="Matricule ou nom du joueur">'
            . ' <button type="submit" class="btn btn-primary">Rechercher</button>'
            . '</form>';
    }

    /**
     * @param array<int, array{id:int, name:string, race:string, player_type:string, xp:int}> $players
     */
    private function results(string $term, array $players): string
    {
        if ($term === '') {
            return '<p class="text-muted">Recherchez un joueur par matricule ou par nom.</p>';
        }

        if ($players === []) {
            return '<p class="text-muted">Aucun joueur ne correspond à « ' . $this->esc($term) . ' ».</p>';
        }

        $rows = '';
        foreach ($players as $player) {
            $rows .= '<tr>'
                . '<td>' . (int) $player['id'] . '</td>'
                . '<td>' . $this->esc($player['name']) . '</td>'
                . '<td>' . $this->esc($player['race']) . '</td>'
                . '<td><span class="badge badge-info">' . $this->esc($player['player_type']) . '</span></td>'
                . '<td>' . (int) $player['xp'] . '</td>'
                . '<td><a class="btn btn-sm btn-outline-primary" href="/admin/player-skills.php?id='
                . (int) $player['id'] . '">Éditer les compétences</a></td>'
                . '</tr>';
        }

        return '<p class="text-muted mb-2">' . count($players) . ' joueur(s)</p>'
            . '<table class="table table-striped table-hover">'
            . '<thead><tr><th>Matricule</th><th>Nom</th><th>Race</th><th>Type</th><th>XP</th><th></th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
