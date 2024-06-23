<?php


require_once('config.php');


if(!isset($_GET['targetId']) || !is_numeric($_GET['targetId'])){

    exit('error target id');
}


$target = new Player($_GET['targetId']);
$target->get_data();

$ui = new Ui($target->data->name);


echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';



echo '
<table border="1" align="center" cellspacing="0" class="marbre">
<tr>
    <td width="210">
        <img src="'. $target->data->portrait .'" height="330" />
    </td>
    <td valign="top">
        ';


        echo '<h1>'. $target->data->name .'</h1>';


        $raceJson = json()->decode('races', $target->data->race);


        echo '<div>'. $raceJson->name .' Rang '. $target->data->rank .'</div>';


        echo '<img src="'. $target->data->avatar .'" />';

        echo '
    </td>
</tr>
<tr>
    <td colspan="2" align="left">
        '. nl2br($target->data->text) .'
    </td>
</table>
';
