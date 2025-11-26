<?php
use Classes\Player;
use Classes\Ui;
use Classes\Str;
use Classes\File;
use Classes\Db;
use App\Tutorial\TutorialFeatureFlag;
use App\Tutorial\TutorialSessionManager;

require_once('config.php');


$player = new Player($_SESSION['playerId']);

$player->get_data();

$ui = new Ui('Options du Profil', true);


ob_start();


if(isset($_GET['portraits'])){

    include('scripts/account/portraits.php');
    exit();
}

if(isset($_GET['avatars'])){

    include('scripts/account/avatars.php');
    exit();
}

if(isset($_GET['mdj'])){

    include('scripts/account/mdj.php');
    exit();
}

if(isset($_GET['story'])){

    include('scripts/account/story.php');
    exit();
}

if(isset($_GET['changeMail'])){
    include('scripts/account/change_mail.php');
    exit();
}

if(isset($_GET['changePsw'])){
    $player->get_data();
    include('scripts/account/change_psw.php');
    exit();
}

if(isset($_POST['changeName'])){

    include('scripts/account/change_name.php');
    exit();
}

// Build options array dynamically
$options = array(
    'changeMail'=>"Changer Mail<br /><sup>" .
        (!empty($player->data->plain_mail) ? htmlspecialchars($player->data->plain_mail) : "") . "</sup>",
    'changePortrait'=>"Changer de Portrait<br /><sup>Vous pouvez faire une demande de Portrait sur le <a href='https://age-of-olympia.net/forum.php?topic=1725177169' target='_blank'>forum</a></sup>",
    'changeAvatar'=>"Changer d'Avatar<br /><sup>Vous pouvez faire une demande d'Avatar sur le <a href='https://age-of-olympia.net/forum.php?topic=1725177169' target='_blank'>forum</a></sup>",
    'changeMdj'=>"",
    'changeStory'=>"",
    'raceHint'=>"Indice de Race<br /><sup>Affiche une bordure de couleur autour du personnage</sup>",
    'raceHintMax'=>"Indice de Race maximale<br /><sup>Colore également l'arrière plan du personnage</sup>",
    // 'noPrompt'=>"Désactiver le système anti-misslick<br /><sup>Vous n'aurez plus d'alertes pour confirmer vos Actions</sup>",
    'hideGrid'=>"Cacher le damier de la Vue<br /><sup>La grille ne s'affichera plus</sup>",
    'noMask'=>"Désactiver les masques<br /><sup>Les effets de brumes et de pluie ne s'afficheront plus</sup>",
    'showActionDetails'=>"Afficher les détails des Actions<br /><sup>Affiche les calculs et les jets</sup>",
    'noTrain'=>"Interdire les entraînements<br />",
    'dlag'=>"DLA glissante<br /><sup>Décale l'heure du prochain tour</sup>",
    'deleteAccount'=>"Demander la suppression du compte<br /><sup>Votre compte sera supprimé sous 7 jours</sup>",
    'reloadView'=>"Rafraichir la Vue<br /><sup>Si cette dernière est buguée</sup>",
    'incognitoMode'=>"Mode Incognito (PNJ)<br /><sup>Invisible sur la carte et dans les évènements</sup>",
    'anonymeMode'=>"Mode Incognito/Anonyme (PNJ)<br /><sup>Invisible dans les destinataires d'échanges ou de missives</sup>",
);

// Conditionally add tutorial replay option
// Show for players who have completed tutorial before (regardless of feature flag)
$db = new Db();
$sessionManager = new TutorialSessionManager($db);
$hasCompletedTutorial = $sessionManager->hasCompletedBefore($player->id);

// Always show replay option if player has completed tutorial
// The feature flag will determine which tutorial system to use (new vs old)
if ($hasCompletedTutorial || !TutorialFeatureFlag::isEnabledForPlayer($player->id)) {
    $options['showTuto'] = "Rejouer le tutoriel";
}

define('OPTIONS', $options);



if(!empty($_POST['option'])){


    if(!isset(OPTIONS[$_POST['option']])){

        exit('error option');
    }

    if($_POST['option']=='incognitoMode' || $_POST['option']=='anonymeMode')
    {
       if($player->id>=0)
           exit('error option for pnj');
    }

    $player->refresh_view();


    if($player->have_option($_POST['option'])){


        $player->end_option($_POST['option']);

        exit();
    }

    $player->add_option($_POST['option']);

    exit();
}


echo '<a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a>';
echo '<button data-change="name">Changer Nom</button>';
echo '<a href="account.php?changePsw"><button>Changer Mot de Passe</button></a>';

echo '
<table border="1" align="center" class="marbre">';

echo '<tr><th colspan="2" align="center">Options du Profil</th></tr>';


$checked = array();


foreach(OPTIONS as $k=>$e){


    $checked[$k] = '';
}

foreach($player->get_options() as $e){


    $checked[$e] = 'checked';
}


foreach(OPTIONS as $k=>$e){


    if(($k =='incognitoMode' || $k =='anonymeMode' ) && $player->id>=0)
    { //Option non disponible pour les PJ
        continue;
    }
    else{
        echo '<tr>';    
    }
    echo '
    
        <td>
            ';

            if($k == 'changeMdj'){

                echo "Modifier son MDJ<br /><sup>";
                echo explode("\n", $player->data->text)[0] .' [...]';
                echo '</sup>';
            }
            elseif($k == 'changeStory'){

                echo "Modifier son Histoire<br /><sup>";
                echo explode("\n", $player->data->story)[0] .' [...]';
                echo '</sup>';
            }
            elseif($k == 'manageUploads'){

                echo $e .'<br />';

                // drag and drop for upload img
                $uploadedN = count(File::get_uploaded($player));
                $uploadMax = File::get_uploaded_max($player);

                echo '<sup>Vous avez uploadé '. $uploadedN .'/'. $uploadMax .' images</sup>';
            }
            else{

                echo $e;
            }

            echo '
        </td>
        <td>';

            if($k == 'changePortrait'){

                echo '
                <a href="account.php?portraits"><img src="'. $player->data->mini .'" /></a>
                ';
            }
            elseif($k == 'changeAvatar'){

                echo '
                <a href="account.php?avatars"><img src="'. $player->data->avatar .'" width="50" /></a>
                ';
            }
            elseif($k == 'changeMdj'){

                echo '
                <a href="account.php?mdj"><button>Changer</button></a>
                ';
            }
            elseif($k == 'changeStory'){

                echo '
                <a href="account.php?story"><button>Changer</button></a>
                ';
            }
            elseif($k == 'showTuto'){
                // Feature flag determines which tutorial system to use
                if (TutorialFeatureFlag::isEnabledForPlayer($player->id)) {
                    // New tutorial system - use URL parameter to trigger replay
                    echo '
                    <a href="index.php?replay_tutorial=1"><button style="width: 100%;">Tutoriel</button></a>
                    ';
                } else {
                    // Old tutorial system
                    echo '
                    <a href="index.php?tutorial"><button style="width: 100%;">Tutoriel</button></a>
                    ';
                }
            }
            elseif($k == 'changeMail'){
                // Disable email change for PNJs
                if($player->id > 0) {
                    echo '<a href="account.php?changeMail"><button style="width: 100%;">Changer</button></a>';
                } else {
                    echo '<button style="width: 100%; opacity: 0.5; cursor: not-allowed;" disabled>PNJ - Non disponible</button>';
                }
            }
            else{

                echo '
                <input type="checkbox" class="option" data-option="'. $k .'" '. $checked[$k] .' />
                ';
            }

            echo '
        </td>
    </tr>
    ';

}



if($player->have_option('isAdmin')){


    echo '
    <tr>
        <td>Ouvrir la console (admin)</td>
        <td><input type="button" OnClick="create_console(); document.getElementById(\'input-line\').focus()" value="Ouvrir" style="width: 100%;" /></td>
    </tr>
    ';
}


echo '
</table>
';

?>
<script>
    window.alreadyChanged = <?php if($player->have_option('alreayChanged')) echo 1; else echo 0; ?>;
    window.oldName = "<?php echo $player->data->name ?>";
</script>
<script src="js/account.js"></script>


<?php

$content = ob_get_clean();
echo Str::minify($content);
