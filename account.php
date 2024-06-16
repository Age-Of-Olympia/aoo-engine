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


define('OPTIONS', array(

    'changePortrait'=>"Changer de Portrait<br /><sup>Vous pouvez faire une demande de Portrait sur le forum</sup>",
    'changeAvatar'=>"Changer d'Avatar<br /><sup>Vous pouvez faire une demande d'Avatar sur le forum</sup>",
    'changeMdj'=>$player->data->text,
    'raceHint'=>"Indice de Race<br /><sup>Affiche une bordure de couleur autour du personnage</sup>",
    'raceHintMax'=>"Indice de Race maximale<br /><sup>Colore également l'arrière plan du personnage</sup>",
    'noPrompt'=>"Désactiver le système anti-misslick<br /><sup>Vous n'aurez plus d'alertes pour confirmer vos Actions</sup>",
    'hideGrid'=>"Cacher le damier de la Vue<br /><sup>La grille ne s'affichera plus</sup>",
    'deleteAccount'=>"Demander la suppression du compte<br /><sup>Votre compte sera supprimé sous 7 jours</sup>",
    'reloadView'=>"Rafraichir la Vue<br /><sup>Si cette dernière est buguée</sup>",
    'hideTuto'=>"Cacher le Tutoriel<br /><sup>Décochez si vous souhaitez le relire</sup>"
));


echo '<a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a>';
echo '<a href="index.php"><button>Changer Nom</button></a>';
echo '<a href="index.php"><button>Changer Mot de Passe</button></a>';
echo '<a href="index.php"><button>Changer Mail</button></a>';


echo '
<table class="box-shadow marbre" border="1" align="center">';


echo '<tr><th>Options du Profil</th><th></th></tr>';

foreach(OPTIONS as $k=>$e){


    echo '
    <tr>
        <td>
            ';

            if($k == 'changeMdj'){

                echo explode("\n", $player->data->text)[0] .' [...]';
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
                <a href="account.php?portraits"><img src="'. $player->data->portrait .'" width="75" /></a>
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
            else{

                echo '
                <input type="checkbox" class="option" data-option="'. $k .'" />
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

    $('.option').click(function(e){

        e.preventDefault();

        var $box = $(this);

        if($(this).data('option') == 'reloadView'){

            $.ajax({
                type: "POST",
                url: 'scripts/account/refresh_view.php',
                data: {}, // serializes the form's elements.
                success: function(data)
                {
                    alert(data);

                    $box.prop('checked', true);
                }
            });
        }
    });
});
</script>
