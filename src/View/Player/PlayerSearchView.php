<?php

namespace App\View\Player;

/**
 * The Compétences landing: the full roster of real players, filtered live by a
 * search field (name or matricule). Each row opens that player's editor.
 * The filter is client-side so it responds as you type.
 */
final class PlayerSearchView
{
    /**
     * @param array<int, array{id:int, name:string, race:string, player_type:string, xp:int}> $players
     */
    public function render(array $players): string
    {
        return '<h1 class="mb-3">Compétences des joueurs</h1>'
            . '<input type="search" id="skills-filter" class="form-control mb-3" style="max-width:24rem"'
            . ' placeholder="Filtrer par nom ou matricule…" autofocus autocomplete="off"'
            . ' aria-label="Filtrer les joueurs">'
            . $this->table($players)
            . $this->script();
    }

    /**
     * @param array<int, array{id:int, name:string, race:string, player_type:string, xp:int}> $players
     */
    private function table(array $players): string
    {
        $rows = '';
        foreach ($players as $player) {
            $needle = strtolower($player['name'] . ' ' . $player['id']);
            $rows .= '<tr data-filter="' . $this->esc($needle) . '">'
                . '<td>' . (int) $player['id'] . '</td>'
                . '<td>' . $this->esc($player['name']) . '</td>'
                . '<td>' . $this->esc($player['race']) . '</td>'
                . '<td>' . (int) $player['xp'] . '</td>'
                . '<td><a class="btn btn-sm btn-outline-primary" href="/admin/player-skills.php?id='
                . (int) $player['id'] . '">Éditer les compétences</a></td>'
                . '</tr>';
        }

        return '<p class="text-muted mb-2"><span id="skills-count">' . count($players) . '</span> joueur(s)</p>'
            . '<table class="table table-striped table-hover" id="skills-table">'
            . '<thead><tr><th>Matricule</th><th>Nom</th><th>Race</th><th>XP</th><th></th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>'
            . '<p class="text-muted" id="skills-empty" style="display:none">Aucun joueur ne correspond.</p>';
    }

    private function script(): string
    {
        return '<script>(function(){'
            . 'var input=document.getElementById("skills-filter");'
            . 'var rows=Array.prototype.slice.call(document.querySelectorAll("#skills-table tbody tr"));'
            . 'var count=document.getElementById("skills-count");'
            . 'var empty=document.getElementById("skills-empty");'
            . 'if(!input)return;'
            . 'input.addEventListener("input",function(){'
            . 'var q=input.value.trim().toLowerCase();var shown=0;'
            . 'rows.forEach(function(r){'
            . 'var match=q===""||r.getAttribute("data-filter").indexOf(q)!==-1;'
            . 'r.style.display=match?"":"none";if(match)shown++;});'
            . 'if(count)count.textContent=shown;'
            . 'if(empty)empty.style.display=shown===0?"":"none";'
            . '});})();</script>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
