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

//this way allow single sql querry, the count of incognito should be small enough to not be a problem
$incognitos = array();
$incognitosSQL="SELECT player_id FROM `players_options` WHERE name='incognitoMode'";
$resIncognito = $db->exe($incognitosSQL);
while($row = $resIncognito->fetch_object()){
    $incognitos[$row->player_id] = true ;
}
$width=11;
if(!empty($planJson->size)){

    $width=$planJson->size;
}
    
echo '
<svg
    xmlns="http://www.w3.org/2000/svg"
    xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1"
    baseProfile="full"

    width="'. $width * 10 .'"
    height="'. $width * 10 .'"

    style="background: url(img/ui/map/'. $player->coords->plan .'.png) center center no-repeat;"
    >
    ';

    while($row = $res->fetch_object()){
        if($row->id != $player->id && isset($incognitos[$row->id])){
            continue;
        }
        $raceJson = json()->decode('races', $row->race);


        $x = ($row->x + $width) * 5;
        $y = (-$row->y + $width) * 5;

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
AND p.id NOT IN (
SELECT 
`players_options`.`player_id` 
FROM `players_options` 
INNER JOIN players_options as po 
ON po.player_id = p.id 
WHERE `players_options`.`name`="incognitoMode" 
)
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



$sql = '
SELECT x,y,z,m2.params,m3.name
FROM coords
INNER JOIN map_elements AS m1
INNER JOIN map_triggers AS m2
INNER JOIN map_tiles AS m3
ON m1.coords_id = coords.id
AND m1.coords_id = m2.coords_id
AND m1.coords_id = m3.coords_id
WHERE
m1.name = "flag_red"
AND
plan = ?
';

$res = $db->exe($sql, $player->coords->plan);

if(!$res->num_rows){

    echo '<p><font color="red">Impossible de voyager <b>depuis</b> ce Territoire.</font></p>';
}
else{


    echo '<h2>Routes</h2>';

    echo '<table border="1" class="marbre" align="center">';

    echo '<tr><th></th><th>Direction</th><th>Position</th></tr>';

    while($row = $res->fetch_object()){


        echo '<tr><td><img style="background: url(img/tiles/'. $row->name .'.png);" src="img/elements/flag_red.webp" width="35" /></td><td align="center">'. strtoupper($row->params) .'<td>'. $row->x .','. $row->y .','. $row->z .'</td></tr>';
    }


    echo '</table>';
}
