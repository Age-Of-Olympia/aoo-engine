<?php

namespace App\View\Player;

use App\Trait\EscapesHtmlTrait;

/**
 * Roster of players who own a given action or passive ("Qui a ça ?"). Reached
 * from the ownership count on the Actions / Passifs pages; each row links to
 * that player's Compétences editor.
 */
final class SkillOwnersView
{
    use EscapesHtmlTrait;

    /**
     * @param array<int, array{id:int, name:string, race:string}> $players
     */
    public function render(array $players): string
    {
        if ($players === []) {
            return '<p class="text-muted">Aucun joueur ne possède cette compétence.</p>';
        }

        $rows = '';
        foreach ($players as $player) {
            $rows .= '<tr>'
                . '<td>' . (int) $player['id'] . '</td>'
                . '<td>' . $this->esc($player['name']) . '</td>'
                . '<td>' . $this->esc($player['race']) . '</td>'
                . '<td><a class="btn btn-sm btn-outline-primary" href="/admin/player-skills.php?id='
                . (int) $player['id'] . '">Compétences</a></td>'
                . '</tr>';
        }

        return '<p class="text-muted mb-2">' . count($players) . ' joueur(s)</p>'
            . '<table class="table table-striped table-hover">'
            . '<thead><tr><th>Matricule</th><th>Nom</th><th>Race</th><th></th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>';
    }

}
