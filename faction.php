<?php


require_once('config.php');


if(!empty($_GET['faction'])){

    $facJson = json()->decode('factions', $_GET['faction']);


    if(!$facJson){

        exit('error faction');
    }


    $ui = new Ui('Faction: '. $facJson->name);


    echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"> Retour</button></a></div>';


    echo '<h1>'. $facJson->name .'</h1>';

    echo '<div style="font-size: 5em;"><span class="ra '. $facJson->raFont .'"></span></div>';


    $sql = 'SELECT id,avatar,name,race,xp,rank FROM players WHERE nextTurnTime > ? AND faction = ? ORDER BY name';

    $db = new Db();

    $timeLimit =time() - INACTIVE_TIME;

    $res = $db->exe($sql, array($timeLimit, $_GET['faction']));


    echo '
    <table border="1" cellspacing="0" class="marbre" align="center">
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
                '. $row->name .'
            </td>
            <td>
                '. $raceJson->name .'
            </td>
            <td>
                '. $row->xp .'
            </td>
            <td>
                '. $row->rank .'
            </td>
        </tr>
        ';
    }

    echo '
    </table>
    ';

}
else{

    $ui = new Ui('Factions');
}





