<?php

namespace App\View\Player;

/**
 * The Compétences landing: the full roster of characters (real players + PNJs),
 * filtered live by a search field (name or matricule), a Type filter
 * (Joueurs / PNJ) and a Statut filter (Actifs / Inactifs). Each row opens that
 * character's editor. All filters are client-side so they respond as you type.
 */
final class PlayerSearchView
{
    /**
     * @param array<int, array{id:int, name:string, race:string, player_type:string, xp:int, lastLoginTime:int, active:bool}> $players
     */
    public function render(array $players): string
    {
        return '<h1 class="mb-3">Compétences des joueurs</h1>'
            . $this->filters()
            . $this->table($players)
            . $this->script();
    }

    /**
     * The three filter controls: free-text search, Type and Statut selects.
     */
    private function filters(): string
    {
        return '<div class="d-flex flex-wrap mb-3" style="gap:.5rem">'
            . '<input type="search" id="skills-filter" class="form-control" style="max-width:22rem"'
            . ' placeholder="Filtrer par nom ou matricule…" autofocus autocomplete="off"'
            . ' aria-label="Filtrer les personnages">'
            . '<select id="skills-type" class="form-control" style="max-width:12rem" aria-label="Filtrer par type">'
            . '<option value="">Tous les types</option>'
            . '<option value="real">Joueurs</option>'
            . '<option value="npc">PNJ</option>'
            . '</select>'
            . '<select id="skills-status" class="form-control" style="max-width:12rem" aria-label="Filtrer par statut">'
            . '<option value="">Tous les statuts</option>'
            . '<option value="active">Actifs</option>'
            . '<option value="inactive">Inactifs</option>'
            . '</select>'
            . '</div>';
    }

    /**
     * @param array<int, array{id:int, name:string, race:string, player_type:string, xp:int, lastLoginTime:int, active:bool}> $players
     */
    private function table(array $players): string
    {
        $rows = '';
        foreach ($players as $player) {
            $needle = strtolower($player['name'] . ' ' . $player['id']);
            $isNpc = $player['player_type'] === 'npc';
            $active = !empty($player['active']);

            $rows .= '<tr data-filter="' . $this->esc($needle) . '"'
                . ' data-type="' . ($isNpc ? 'npc' : 'real') . '"'
                . ' data-active="' . ($active ? '1' : '0') . '">'
                . '<td>' . (int) $player['id'] . '</td>'
                . '<td>' . $this->esc($player['name']) . '</td>'
                . '<td>' . $this->typeBadge($isNpc) . '</td>'
                . '<td>' . $this->esc($player['race']) . '</td>'
                . '<td>' . $this->statusBadge($active) . '</td>'
                . '<td>' . (int) $player['xp'] . '</td>'
                . '<td><a class="btn btn-sm btn-outline-primary" href="/admin/player-skills.php?id='
                . (int) $player['id'] . '">Éditer les compétences</a></td>'
                . '</tr>';
        }

        return '<p class="text-muted mb-2"><span id="skills-count">' . count($players) . '</span> personnage(s)</p>'
            . '<table class="table table-striped table-hover" id="skills-table">'
            . '<thead><tr><th>Matricule</th><th>Nom</th><th>Type</th><th>Race</th>'
            . '<th>Statut</th><th>XP</th><th></th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>'
            . '<p class="text-muted" id="skills-empty" style="display:none">Aucun personnage ne correspond.</p>';
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

    private function script(): string
    {
        // Combines the three filters (text + type + status): a row shows only
        // when it matches every active criterion.
        return '<script>(function(){'
            . 'var input=document.getElementById("skills-filter");'
            . 'var typeSel=document.getElementById("skills-type");'
            . 'var statusSel=document.getElementById("skills-status");'
            . 'var rows=Array.prototype.slice.call(document.querySelectorAll("#skills-table tbody tr"));'
            . 'var count=document.getElementById("skills-count");'
            . 'var empty=document.getElementById("skills-empty");'
            . 'if(!input)return;'
            . 'function apply(){'
            . 'var q=input.value.trim().toLowerCase();'
            . 'var t=typeSel?typeSel.value:"";'
            . 'var s=statusSel?statusSel.value:"";'
            . 'var shown=0;'
            . 'rows.forEach(function(r){'
            . 'var okText=q===""||r.getAttribute("data-filter").indexOf(q)!==-1;'
            . 'var okType=t===""||r.getAttribute("data-type")===t;'
            . 'var active=r.getAttribute("data-active")==="1";'
            . 'var okStatus=s===""||(s==="active"?active:!active);'
            . 'var match=okText&&okType&&okStatus;'
            . 'r.style.display=match?"":"none";if(match)shown++;});'
            . 'if(count)count.textContent=shown;'
            . 'if(empty)empty.style.display=shown===0?"":"none";'
            . '}'
            . 'input.addEventListener("input",apply);'
            . 'if(typeSel)typeSel.addEventListener("change",apply);'
            . 'if(statusSel)statusSel.addEventListener("change",apply);'
            . '})();</script>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
