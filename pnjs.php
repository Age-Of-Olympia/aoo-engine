<?php

require_once('config.php');

$ui = new Ui('Personnages secondaires');


echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';


Use App\Service\PlayerPnjService;
$playerPnjService = new PlayerPnjService();


$main = new Player($_SESSION['mainPlayerId']);


$playersTbl = array(
    $main->id=>$main
);

$hiddenPnjs = array();

$playerPnjs = $playerPnjService->getByPlayerId($main->id);
foreach($playerPnjs as $playerPnj ){
    if($playerPnj->isDisplayed()){
        $playersTbl[$playerPnj->getPnjId()] = new Player($playerPnj->getPnjId());
    }else{
        $hiddenPnjs[$playerPnj->getPnjId()] = new Player($playerPnj->getPnjId());
    }
}


if(!empty($_POST['switch'])){


    if(!isset($playersTbl[$_POST['switch']]) && !isset($hiddenPnjs[$_POST['switch']])){

        exit('error pnj');
    }

    $_SESSION['playerId'] = $_POST['switch'];

    // update lastLoginTime

    $sql = 'UPDATE players SET lastLoginTime = ? WHERE id = ?';

    $time = time();

    $db->exe($sql, array($time, $_SESSION['playerId']));

    exit();
}


echo '<section class="marbre pnj-container">';

Use App\Service\PlayerEffectService;
$playerEffectService = new PlayerEffectService();

foreach($playersTbl as $pnj){


    $pnj->get_data();

    $effectsTbl = array();

    $playerEffects = $playerEffectService->getEffectsByPlayerId($pnj->id);
    
    foreach ($playerEffects as $effect){
        
        $endTime = '(reposez-vous)';

        if(time() < $effect->getEndTime()){

            $endTime = Str::convert_time($effect->getEndTime()- time());
        }


        if(!$effect->getEndTime()){

            $endTime = '∞';
        }

        $effectsTbl[] = '<span class="ra '. EFFECTS_RA_FONT[$effect->getName()] .'"></span> <sup>'. $endTime .'</sup>';
    }
    
    $raceJson = json()->decode('races', $pnj->data->race);


    $mails = $pnj->get_new_mails();

    if($mails){


        $mails = '<div class="cartouche bulle blink" data-id="'. $pnj->id .'">'. $mails .'</div>';
    }
    else{


        $mails = '';
    }


    echo '
    <article class="pnj" style="cursor: pointer; position:relative;" data-id="'. $pnj->id .'">
        <div style="position: relative;">'. $mails .'
            <div class="infos-effects">'. implode('<br />', $effectsTbl) .'</div>
            <img class="portrait" src="'. $pnj->data->portrait .'" /><br />
            '. $pnj->data->name .'<br /><span style="font-size: 88%;">mat.'. $pnj->id .'<br />
            '. $raceJson->name .'<br />Rang '. $pnj->data->rank .'</span>
        </div>';
    if($pnj->id!=$_SESSION['originalPlayerId']){  
        echo '<div class="masquer-pnj" data-player-id="'. $_SESSION['originalPlayerId'] .'" data-id="'. $pnj->id .'" ><span class="ra ra-fall-down "/> masquer</div>';
    }
    echo '
    </article>
    ';
}


echo '
</section>

<section class="marbre pnj-container hidden-pnjs"><div id="display-hidden-pnjs" style="cursor:pointer"> + Afficher la liste des PNJs Masqués.</div>
<div id="hidden-pnjs-list">
';
foreach($hiddenPnjs as $hiddenPnj){
    $hiddenPnj->get_data();
    $raceJson = json()->decode('races', $hiddenPnj->data->race);  
    $mails = $hiddenPnj->get_new_mails();
    if($mails){
        $mails = '<span class="cartouche bulle-mini blink" data-id="'. $hiddenPnj->id .'">'. $mails .'</span>';
    }else{
        $mails = '';
    }
    echo '<div data-player-id="'. $_SESSION['playerId'] .'" data-id="'. $hiddenPnj->id .'" >'. $hiddenPnj->data->name .' - <span style="font-size: 88%;">mat.'. $hiddenPnj->id .' - 
            '. $raceJson->name .' - Rang '. $hiddenPnj->data->rank .' '.$mails.'
            <button class="showPnj" data-player-id="'. $_SESSION['originalPlayerId'] .'" data-id="'. $hiddenPnj->id .'">Afficher</button>
            <button class="impersonate" data-id="'. $hiddenPnj->id .'">Jouer</button>
            </div>';

}
echo '
</div></section>'

?>
<script src="js/pnjs.js"></script>
