<?php

echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a><a href="map.php"><button>Monde</button></a><a href="map.php?local"><button>'. $planJson->name .'</button></a></div>';


// plan at war
$colored = '';

if(!empty($planJson->war)){

    $colored = 'colored-red';

    echo '<font color="red">Ce territoire est en guerre!</font>';
}


echo '<h1><font style="font-family: goudy">'. $planJson->name .'</font></h1>';


echo '<div class="map-local"><img src="img/ui/map/'. $player->coords->plan .'.png" /></div>';


echo '
<table border="1" class="marbre" align="center">

    <tr>
        <th colspan="'. count(RACES) .'">
            Forces en présence
        </th>
    </tr>
    <tr>
        ';

        $sql = '
        SELECT
        race,
        SUM(xp) AS xp
        FROM
        players AS p
        INNER JOIN
        coords AS c
        ON
        c.id = p.coords_id
        WHERE
        c.plan = ?
        AND
        race IN("'. implode('","', RACES) .'")
        GROUP BY race
        ';

        $db = new Db();

        $res = $db->exe($sql, $player->coords->plan);

        while($row = $res->fetch_object()){

            $data[$row->race] = $row->xp;
        }

        foreach($data as $k=>$e){

            $raceJson = json()->decode('races', $k);

            echo '
            <td>'. $raceJson->name .'s</td>
            ';
        }

        echo '
    </tr>
    <tr>
        ';

        foreach($data as $e){

            echo '<td>'. $e .'Xp</td>';
        }

        echo '
    </tr>
</table>
';


if(!empty($planJson->pnj)){


    $pnj = new Player($planJson->pnj);

    $pnj->get_data();

    $raceJson = json()->decode('races', $pnj->data->race);

    echo '
    <h2>PNJ</h2>
    <table border="1" align="center" class="marbre">
        <tr>
            ';

            echo '<td><img src="'. $pnj->data->avatar .'" /></td>';

            echo '<td align="left"><a href="infos.php?targetId='. $pnj->id .'">'. $pnj->data->name .'</a><br /><font style="font-size: 88%;">'. $raceJson->name .', rang '. $pnj->data->rank .'</font></td>';

            echo '
        </tr>
    </table>
    ';
}

else{

    echo '<p><font color="red">Il n\'y a pas de PNJ assigné à ce Territoire.</font></p>';
}
