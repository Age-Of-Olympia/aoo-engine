<?php

if(!empty($_SESSION['playerId'])){


    $playerJson = json()->decode('players', $_SESSION['playerId']);

    if(!$playerJson){

        $player = new Player($_SESSION['playerId']);

        $playerJson = $player->get_data();
    }


    $raceJson = json()->decode('races', $playerJson->race);


    echo '
    <table border="0" align="center">
        <tr>
            <td>
                <a href="pnjs.php"><img src="'. $playerJson->avatar .'" /></a>
            </td>
            <td align="left">
                '. $playerJson->name .' (mat.'. $playerJson->id .')<br />
                <sup>'. $raceJson-> name .' Rang '. $playerJson->rank .'</sup>
            </td>
        </tr>
    </table>
    ';
}

else{


    echo "Vous n'êtes pas connecté.";
}
