<?php
use Classes\ActorInterface;
use Classes\Db;
use Classes\Ui;
require_once('config.php');

$ui = new Ui('Deck');


$db = new Db();

$sql = 'SELECT * FROM players_deck WHERE player_id = ?';

$res = $db->exe($sql, $_SESSION['playerId']);

while($row = $res->fetch_object()){


    $target = new ActorInterface($row->target_id);

    $target->get_data();


    $dataName = '<a href="infos.php?targetId='. $target->id .'">'. $target->data->name .'</a>';

    $raceJson = json()->decode('races', $target->data->race);

    $factionJson = json()->decode('factions', $target->data->faction);

    $data = (object) array(
        'bg'=>$target->data->portrait,
        'name'=>$dataName,
        'img'=>'',
        'type'=>$raceJson->name,
        'text'=>'<textarea spellcheck="false"></textarea>',
        'race'=>$target->data->race,
        'faction'=>'<a href="faction.php?faction='. $target->data->faction .'"><span class="ra '. $factionJson->raFont .'"></span></a>',
        'noClose'=>1
    );


    $card = Ui::get_card($data);

    echo $card;
}
