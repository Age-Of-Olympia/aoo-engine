<?php

echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a><a href="map.php"><button>Monde</button></a><a href="map.php?local"><button>'. $planJson->name .'</button></a></div>';


// plan at war
$colored = '';

if(!empty($planJson->war)){

    $colored = 'colored-red';

    echo '<font color="red">Ce territoire est en guerre!</font>';
}


echo '<h1><font style="font-family: goudy">'. $planJson->name .'</font></h1>';


// echo '<div class="map-local"><img src="img/ui/map/'. $player->coords->plan .'.png" /></div>';

// mini map
$sql = '
SELECT
p.id,
name,
race,
x,
y
FROM
players AS p
INNER JOIN
coords AS c
ON
p.coords_id = c.id
WHERE
z = ?
AND
plan = ?
';

$db = new Db();

$res = $db->exe($sql, array($player->coords->z, $player->coords->plan));

echo '
<svg
    xmlns="http://www.w3.org/2000/svg"
    xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1"
    baseProfile="full"

    width="100"
    height="100"

    style="background: url(img/ui/map/'. $player->coords->plan .'.png) center center no-repeat;"
    >
    ';

    while($row = $res->fetch_object()){


        $raceJson = json()->decode('races', $row->race);


        $x = ($row->x + 10) * 5;
        $y = (-$row->y + 10) * 5;

        echo '
        <rect
            x="' . $x . '"
            y="' . $y . '"

            width="5"
            height="5"

            fill="'. $raceJson->bgColor .'"
            />
        ';
    }


    echo '
</svg>
';


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



