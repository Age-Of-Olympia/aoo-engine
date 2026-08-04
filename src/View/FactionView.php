<?php

namespace App\View;

use App\Service\RaceService;

class FactionView
{
    public static function renderFaction($player,$facJson,$res,): void
    {


        echo '
    <table border="1" class="marbre" align="center">
    ';

        echo '
    <tr>
        <th></th>
        <th>Nom</th>
        <th>Peuple</th>
        <th>Xp</th>
        <th>Rang</th>
        <th>Territoire</th>
    </tr>
    ';

        $raceService = new RaceService();

        while ($row = $res->fetch_object()) {


            $raceJson = $raceService->getRaceData($row->race);

            $planJson = json()->decode('plans', $row->plan);

            if (!$planJson) {

                $planName = '?';
            } else {

                $planName = $planJson->name;
            }


            echo '
        <tr>
            <td>
                <img src="' . $row->avatar . '" />
            </td>
            <td>
                <a href="infos.php?targetId=' . $row->id . '">' . $row->name . '</a>
            </td>
            <td>
                ' . ($raceJson?->name ?? '???') . '
            </td>
            <td>
                ' . $row->xp . '
            </td>
            <td>
                ' . $facJson->role[$row->factionRole]->name . '
            </td>
            <td>
                ';


            // simulate target as a Player()
            $target = (object) array(
                'data' => (object) array(
                    'faction' => $_GET['faction'],
                    'secretFaction' => ""
                )
            );

            if ($player->check_share_factions($target)) {

                echo $planName;
            } else {

                echo '?';
            }

            echo '
            </td>
        </tr>
        ';
        }

        echo '
    </table>
    ';
    }

    /**
     * The faction's buildings — its assets, shown to its members only (the
     * caller applies that rule, the same one that hides the territory).
     * The playable ones will carry the "take command" gesture (L4b).
     *
     * @param array<int, array<string, mixed>> $buildings FactionService::buildingsOf() rows
     */
    public static function renderBuildings(array $buildings): void
    {
        if ($buildings === []) {
            return;
        }

        echo '
    <h2>Bâtiments</h2>
    <table border="1" class="marbre" align="center">
    <tr>
        <th>Nom</th>
        <th>Type</th>
        <th>État</th>
        <th>Territoire</th>
    </tr>
    ';

        foreach ($buildings as $b) {
            $state = match ($b['build_state']) {
                'construction' => 'En chantier'
                    . ($b['site_total'] !== null ? ' (' . $b['site_done'] . '/' . $b['site_total'] . ')' : ''),
                'ruin' => 'Ruine',
                default => 'Construit',
            };

            $planJson = json()->decode('plans', (string) $b['plan']);

            echo '
        <tr>
            <td><a href="infos.php?targetId=' . (int) $b['id'] . '">' . htmlspecialchars((string) $b['name'], ENT_QUOTES, 'UTF-8') . '</a></td>
            <td>' . htmlspecialchars((string) $b['label'], ENT_QUOTES, 'UTF-8')
                . ($b['playable'] ? ' <span class="ra ra-castle-flag" title="Pilotable par la faction"></span>' : '') . '</td>
            <td>' . $state . '</td>
            <td>' . htmlspecialchars((string) ($planJson->name ?? '?'), ENT_QUOTES, 'UTF-8')
                . ' (' . (int) $b['x'] . ', ' . (int) $b['y'] . ')</td>
        </tr>
        ';
        }

        echo '
    </table>
    ';
    }
}
