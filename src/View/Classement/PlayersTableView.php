<?php

namespace App\View\Classement;

use App\Service\RaceService;
use Classes\Str;

/**
 * Table de classement des joueurs — l'ex-fonction globale
 * print_players() de classements.php, promue en vue partagée :
 * le corps des classements (scripts/classements/body.php) et les
 * sous-classements (Fortunes, Réputations) l'utilisent tous.
 *
 * @phpstan-type ClassementRow object{id: int, name: string, race: string,
 *     pr: int, xp: int, rank: int, display_id?: int, gold?: int,
 *     showReput?: bool}
 */
final class PlayersTableView
{
    /** @param array<int, object> $list lignes triées, déjà filtrées */
    public static function render(array $list): void
    {
        echo '
        <table border="1" align="center" class="marbre" cellspacing="0">
        <tr>
            <th>#</th>
            <th>Nom</th>
            <th>Mat.</th>
            <th>Réputation</th>
            <th>Xp</th>
            <th>Rang</th>
            ';

            if(isset($list[0]->gold)){

                echo '<th>Or</th>';
            }
            elseif(isset($list[0]->showReput)){

                echo '<th>Pr</th>';
            }

            echo '
        </tr>
        ';

        $n = 1;
        $raceService = new RaceService();

        foreach($list as $player){

            $raceJson = $raceService->getRaceData($player->race);

            $reput = Str::get_reput(floor($player->pr/COEFFICIENT_PR));

            echo '
            <tr style="color: '. $raceJson->color .'; background: '. $raceJson->bgColor .'">
                <td align="center">'. $n .'</td>
                <td style="white-space: nowrap;">'. $player->name .'</td>
                <td align="center"><a href="infos.php?targetId='. $player->id .'">mat.'. ($player->display_id ?? $player->id) .'</a></td>
                <td><a href="infos.php?targetId='. $player->id .'&reputation">'. $reput .'</a></td>
                <td align="center">'. $player->xp .'</td>
                <td align="center">'. $player->rank .'</td>
                ';

                if(isset($player->gold)){

                    echo '<td align="center">'. $player->gold .'</td>';
                }
                elseif(isset($list[0]->showReput)){

                    echo '<td align="center">'. floor($player->pr/COEFFICIENT_PR) .'</td>';
                }

                echo '
            </tr>
            ';

            $n++;
        }

        echo '
        </table>
        ';
    }
}
