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

    width="110"
    height="110"

    style="background: url(img/ui/map/'. $player->coords->plan .'.png) center center no-repeat;"
    >
    ';

    while($row = $res->fetch_object()){


        $raceJson = json()->decode('races', $row->race);


        $x = ($row->x + 11) * 5;
        $y = (-$row->y + 11) * 5;

        $color = ($row->id == $player->id) ? 'magenta' : $raceJson->bgColor;

        echo '
        <rect
            x="' . $x . '"
            y="' . $y . '"

            width="5"
            height="5"

            fill="'. $color .'"
            />
        ';
    }


    echo '
</svg>
';


$sql = '
SELECT
    faction,
    SUM(xp) AS xp
FROM
    players AS p
INNER JOIN
    coords AS c
ON
    c.id = p.coords_id
WHERE
    c.plan = ?
GROUP BY faction
';

$res = $db->exe($sql, $player->coords->plan);

$data = [];
while ($row = $res->fetch_object()) {
    $data[$row->faction] = $row->xp;
}


echo '
<table border="1" class="marbre" align="center">
    <tr>
        <th colspan="'. count($data) .'">
            Forces en présence
        </th>
    </tr>
    <tr>
';



foreach ($data as $k => $e) {
    $factionJson = json()->decode('factions', $k);
    echo '<td>' . $factionJson->name . '</td>';
}

echo '
    </tr>
    <tr>
';

$xpTotal = array_sum($data);

$pcts = [];
$sumPct = 0;
$index = 0;
$lastIndex = count($data) - 1;

foreach ($data as $e) {
    if ($index == $lastIndex) {
        // Pour la dernière faction, calculer le pourcentage restant pour que la somme soit 100%
        $pct = 100 - $sumPct;
    } else {
        $pct = ($e / $xpTotal) * 100;
        $pct = floor($pct); // Utiliser floor pour éviter les erreurs d'arrondi
        $sumPct += $pct; // Ajouter au total des pourcentages
    }
    $pcts[] = $pct;
    $index++;
}

$index = 0;
foreach ($data as $e) {
    echo '<td>' . $e . 'Xp <sup>' . $pcts[$index] . '%</sup></td>';
    $index++;
}

echo '
    </tr>
    <tr>
        <td colspan="'. count($data) .'">
            Total: '. $xpTotal .'Xp <sup>100%</sup>
        </td>
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



