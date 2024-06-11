<?php

echo '<div><a href="index.php"><button>Retour</button></a><a href="map.php"><button>Monde</button></a></div>';


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
