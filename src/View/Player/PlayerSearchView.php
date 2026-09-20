<?php

namespace App\View\Player;

use App\Trait\EscapesHtmlTrait;

final class PlayerSearchView
{
    use EscapesHtmlTrait;

    public function render(array $players): string
    {
        $rows = '';
        foreach ($players as $player) {
            $isNpc = $player['player_type'] === 'npc';
            $active = !empty($player['active']);

            $rows .= '<tr data-type="' . ($isNpc ? 'PNJ' : 'Joueur') . '"'
                . ' data-race="' . $this->esc(ucfirst($player['race'])) . '"'
                . ' data-status="' . ($active ? 'Actif' : 'Inactif') . '">'
                . '<td>' . (int) $player['id'] . '</td>'
                . '<td>' . $this->esc($player['name']) . '</td>'
                . '<td>' . $this->typeBadge($isNpc) . '</td>'
                . '<td>' . $this->esc($player['race']) . '</td>'
                . '<td>' . $this->statusBadge($active) . '</td>'
                . '<td>' . (int) $player['xp'] . '</td>'
                . '<td><a class="btn btn-sm btn-outline-primary" href="/admin/player-skills.php?id='
                . (int) $player['id'] . '">Éditer les compétences</a> '
                . '<a class="btn btn-sm btn-outline-secondary" href="/admin/player-edit.php?id='
                . (int) $player['id'] . '">Éditer la fiche</a></td>'
                . '</tr>';
        }

        return '<h1 class="mb-3">Compétences des joueurs</h1>'
            . '<table class="table table-striped table-hover" data-admin-list data-page-size="50"'
            . ' data-search-placeholder="Nom ou matricule…" data-facets="type:Type,status:Statut,race:Race">'
            . '<thead><tr><th>Matricule</th><th>Nom</th><th>Type</th><th>Race</th>'
            . '<th>Statut</th><th>XP</th><th></th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>';
    }

    private function typeBadge(bool $isNpc): string
    {
        return $isNpc
            ? '<span class="badge badge-secondary">PNJ</span>'
            : '<span class="badge badge-primary">Joueur</span>';
    }

    private function statusBadge(bool $active): string
    {
        return $active
            ? '<span class="badge badge-success">Actif</span>'
            : '<span class="badge badge-warning">Inactif</span>';
    }
}
