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
        <th>Territoire</th>
    </tr>
    ';

while($row = $res->fetch_object()){


    $raceJson = json()->decode('races', $row->race);

    $planJson = json()->decode('plans', $row->plan);

    if(!$planJson){

        $planName = '?';
    }
    else{

        $planName = $planJson->name;
    }


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
            <td>
                ';


                // simulate target as a Player()
                $target = (object) array(
                    'data'=>(object) array(
                        'faction'=>$_GET['faction'],
                        'secretFaction'=>""
                                     )
                                   );

                if($player->check_share_factions($target)){

                    echo $planName;
                }
                else{

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



