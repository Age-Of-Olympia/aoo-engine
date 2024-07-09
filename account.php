<?php

require_once('config.php');


$player = new Player($_SESSION['playerId']);

$player->get_data();


$ui = new Ui('Options du Profil');


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

if(isset($_GET['uploads'])){

    include('scripts/account/uploads.php');
    exit();
}

define('OPTIONS', array(

    'changePortrait'=>"Changer de Portrait<br /><sup>Vous pouvez faire une demande de Portrait sur le forum</sup>",
    'changeAvatar'=>"Changer d'Avatar<br /><sup>Vous pouvez faire une demande d'Avatar sur le forum</sup>",
    'changeMdj'=>$player->data->text,
    'changeStory'=>$player->data->story,
    'raceHint'=>"Indice de Race<br /><sup>Affiche une bordure de couleur autour du personnage</sup>",
    'raceHintMax'=>"Indice de Race maximale<br /><sup>Colore également l'arrière plan du personnage</sup>",
    'noPrompt'=>"Désactiver le système anti-misslick<br /><sup>Vous n'aurez plus d'alertes pour confirmer vos Actions</sup>",
    'hideGrid'=>"Cacher le damier de la Vue<br /><sup>La grille ne s'affichera plus</sup>",
    'deleteAccount'=>"Demander la suppression du compte<br /><sup>Votre compte sera supprimé sous 7 jours</sup>",
    'reloadView'=>"Rafraichir la Vue<br /><sup>Si cette dernière est buguée</sup>",
    'hideTuto'=>"Cacher le Tutoriel<br /><sup>Décochez si vous souhaitez le relire</sup>",
    'manageUploads'=>"Gérer vos images téléversées"
));



if(!empty($_POST['option'])){


    if(!isset(OPTIONS[$_POST['option']])){

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
echo '<a href="index.php"><button>Changer Mot de Passe</button></a>';
echo '<a href="index.php"><button>Changer Mail</button></a>';


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
            elseif($k == 'manageUploads'){

                echo '
                <a href="account.php?uploads"><button style="width: 100%; height: 4em;">Gérer</button></a>
                ';
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


echo '
</table>
';

?>
<script>
$(document).ready(function(){

    $('button[data-change="name"]').click(function(e){

        if(<?php if($player->have_option('alreayChanged')) echo 1; else echo 0; ?>){

            alert('Vous avez déjà changé de nom une fois.\nDemandez à un Admin si vous souhaitez le modifier une fois de plus.');

            return false;
        }

        var name = prompt('Nouveau nom:');


        if(name == null || name.trim() == ''){

            return false;
        }


        var oldName = "<?php echo $player->data->name ?>";

        if(name == oldName){

            alert('Le nouveau nom est identique à l\'ancien nom.');

            return false;
        }

        $.ajax({
            type: "POST",
            url: 'account.php',
            data: {'changeName': name}, // serializes the form's elements.
            success: function(data)
            {
                htmlContent = $('<div>').html(data).find('#data').html();
                alert(htmlContent);
            }
        });
    });

    $('.option').click(function(e){

        e.preventDefault();

        var $box = $(this);

        if($(this).data('option') == 'reloadView'){

            $.ajax({
                type: "POST",
                url: 'refresh_view.php',
                data: {}, // serializes the form's elements.
                success: function(data)
                {
                    alert(data);

                    $box.prop('checked', true);
                }
            });

            return false;
        }


        $.ajax({
            type: "POST",
            url: 'account.php',
            data: {
                'option': $box.data('option')
            }, // serializes the form's elements.
            success: function(data)
            {

                // alert(data);
                alert('Changement effectué.');

                $box.prop('checked', !$box.prop('checked'));
            }
        });
    });
});
</script>
