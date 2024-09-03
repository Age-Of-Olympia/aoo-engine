<?php

require_once('config.php');


$player = new Player($_SESSION['playerId']);

$player->get_data();


$ui = new Ui('Options du Profil');


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

if(isset($_POST['changeName'])){

    include('scripts/account/change_name.php');
    exit();
}

if(isset($_GET['changePsw'])){

    include('scripts/account/change_psw.php');
    exit();
}

if(isset($_POST['changeMail'])){

    include('scripts/account/change_mail.php');
    exit();
}


define('OPTIONS', array(

    'changePortrait'=>"Changer de Portrait<br /><sup>Vous pouvez faire une demande de Portrait sur le forum</sup>",
    'changeAvatar'=>"Changer d'Avatar<br /><sup>Vous pouvez faire une demande d'Avatar sur le forum</sup>",
    'changeMdj'=>$player->data->text,
    'changeStory'=>$player->data->story,
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
    'showTuto'=>"Rejouer le tutoriel",
    'incognitoMode'=>"Mode Incognito<br /><sup>Vous ne serez plus visible sur la carte ni dans les evenements</sup>",
));



if(!empty($_POST['option'])){


    if(!isset(OPTIONS[$_POST['option']])){

        exit('error option');
    }

    if($_POST['option']=='incognitoMode')
    {
       if(!$player->have_option('isAdmin'))
           exit('error option');
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
echo '<a href="#" class="change-mail"><button>Changer Mail</button></a>';


echo '
<table class="box-shadow marbre" border="1" align="center">';


echo '<tr><th>Options du Profil</th><th></th></tr>';


$checked = array();


foreach(OPTIONS as $k=>$e){


    $checked[$k] = '';
}

foreach($player->get_options() as $e){


    $checked[$e] = 'checked';
}


foreach(OPTIONS as $k=>$e){


    echo '
    <tr>
        <td>
            ';

            if($k == 'changeMdj'){

                echo explode("\n", $player->data->text)[0] .' [...]';
            }
            elseif($k == 'changeStory'){

                echo explode("\n", $player->data->story)[0] .' [...]';
            }
            elseif($k == 'manageUploads'){

                echo $e .'<br />';

                // drag and drop for upload img
                $uploadedN = count(File::get_uploaded($player));
                $uploadMax = File::get_uploaded_max($player);

                echo '<sup>Vous avez uploadé '. $uploadedN .'/'. $uploadMax .' images</sup>';
            }
            else if($k=='incognitoMode')
            {
                if($player->have_option('isAdmin'))
                echo $e;
            }
            else{

                echo $e;
            }

            echo '
        </td>
        <td>
            ';

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

                echo '
                <a href="index.php?tutorial"><button style="width: 100%;">Tutoriel</button></a>
                ';
            }
            else if($k =='incognitoMode')
            {
                if($player->have_option('isAdmin'))
                {
                    echo '
                    <input type="checkbox" class="option" data-option="'. $k .'" '. $checked[$k] .' />
                    ';
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
        <td>Ouvrir la console</td>
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

echo Str::minify(ob_get_clean());
