<?php


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
    </tr>
    ';

while($row = $res->fetch_object()){


    $raceJson = json()->decode('races', $row->race);

    echo '
        <tr>
            <td>
                <img src="'. $row->avatar .'" />
            </td>
            <td>
                <a href="infos.php?targetId='.$row->id.'">'. $row->name .'</a>
            </td>
            <td>
                '. $raceJson->name .'
            </td>
            <td>
                '. $row->xp .'
            </td>
            <td>
                '. $facJson->role[$row->factionRole]->name .'
            </td>
        </tr>
        ';
}

echo '
    </table>
    ';



