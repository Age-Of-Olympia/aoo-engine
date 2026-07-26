<?php
use App\Factory\PlayerFactory;
use App\Service\AccountDeletionService;
use App\View\AccountView;
use Classes\Ui;
use Classes\Str;
use Classes\File;
use Classes\Db;
use App\Tutorial\TutorialFeatureFlag;
use App\Tutorial\TutorialSessionManager;

require_once('config.php');


$player = PlayerFactory::legacy($_SESSION['playerId']);

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

// Reschedule window for the next turn (replaces the old "DLA glissante")
$nextTurnWindow = AccountView::nextTurnWindow($player);

/* Liste des options : SSOT dans AccountView::buildOptions(), partagée
 * avec le rendu en panneau du HUD (load_account.php). Elle sert ici à
 * la fois d'affichage ET de liste blanche du handler POST ci-dessous —
 * une copie locale qui dérive rend donc des options intogglables.
 * C'est exactement ce qui est arrivé à newHud et hideBoardCoords. */
$options = AccountView::buildOptions($player);

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

    /* nextTurn is not a toggleable option: it is handled by
       api/player/set_next_turn.php. */
    if($_POST['option']=='nextTurn'){

        exit('error option');
    }

    $player->refresh_view();

    /* Capture toggle direction BEFORE mutating the option set. */
    $wasEnabled = (bool) $player->have_option($_POST['option']);

    if($wasEnabled){

        $player->end_option($_POST['option']);
    }
    else{

        $player->add_option($_POST['option']);
    }

    /* deleteAccount is more than a preference: stamp the request date and
       alert the admin team so the 7-day deletion window can be honoured. */
    if($_POST['option'] === 'deleteAccount'){

        $deletionService = new AccountDeletionService();

        if($wasEnabled){

            $deletionService->cancelDeletion(
                $player->id,
                $player->data->plain_mail ?? null
            );
        }
        else{

            $deletionService->requestDeletion(
                $player->id,
                $player->data->name,
                $player->data->plain_mail ?? null
            );
        }
    }

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
            elseif($k == 'nextTurn'){

                if(!empty($player->data->nextTurnRescheduled)){

                    echo '<sup>À nouveau disponible après votre prochain tour</sup>';
                }
                else{

                    echo '
                    <input type="datetime-local" id="next-turn-input"
                        min="'. date('Y-m-d\TH:i', $nextTurnWindow['min']) .'"
                        max="'. date('Y-m-d\TH:i', $nextTurnWindow['max']) .'"
                        value="'. date('Y-m-d\TH:i', $nextTurnWindow['min']) .'" />
                    <button id="next-turn-apply">Appliquer</button>
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
    window.alreadyChanged = <?php if($player->have_option('alreadyChanged')) echo 1; else echo 0; ?>;
    window.oldName = "<?php echo $player->data->name ?>";
</script>
<script src="js/account.js?v=20260715"></script>


<?php

$content = ob_get_clean();
echo Str::minify($content);
