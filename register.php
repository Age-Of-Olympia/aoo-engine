<?php


define('NO_LOGIN', true);


require_once('config.php');


if(!empty($_POST['race'])){


    if(
        !empty($_POST['name'])
        &&
        !empty($_POST['psw1'])
        &&
        !empty($_POST['psw2'])
        &&
        !empty($_POST['mail'])
    ){


        if(!in_array($_POST['race'], RACES)){

            exit('error race');
        }

        if($_POST['psw1'] != $_POST['psw2']){

            exit('error psw');
        }

        if(!Str::check_name($_POST['name'])){

            exit('error name');
        }


        if(!Str::check_mail($_POST['mail'])){

            exit('error mail');
        }


        $playerId = Player::put_player($_POST['name'], $_POST['race']);

        $player = new Player($playerId);

        $player->get_data();

        echo 'Personnage '. $player->data->name .' (matricule '. $player->id .') créé avec succès!<br />';

        echo 'Vous pouvez désormais <a href="index.php?login='. $player->id .'">vous connecter</a> en utilisant son nom ou son matricule.';

        exit();
    }


    $name = (!empty($_POST['name'])) ? $_POST['name'] : '';


    echo '<table border="0" align="center">';
    echo '<tr><td>Nom</td><td><input type="text" name="name" value="'. $name .'" /></td></tr>';

    echo '<tr>';
    echo '<td>Race</td>';

    echo '<td><select name="race">';

    foreach(RACES as $e){


        $raceJson = json()->decode('races', $e);

        $selected = ($e == $_POST['race']) ? 'selected' : '';

        echo '<option value="'. $e .'" '. $selected .'>'. $raceJson->name .'</option>';
    }

    echo '</td>';
    echo '</tr>';

    echo '<tr><td>Mot de passe</td><td><input type="password" name="psw1" value="" /></td></tr>';
    echo '<tr><td>Confirmez</td><td><input type="password" name="psw2" value="" /></td></tr>';
    echo '<tr><td>Mail</td><td><input type="text" name="mail" value="" /></td></tr>';

    echo '<tr><td colspan="2"><button id="submit">Valider</button></td></tr>';

    echo '</table>';


    ?>
    <script>
    $(document).ready(function(){

        $('#submit').click(function(e){


            var name = $('[name="name"]').val();
            var race = $('[name="race"]').val();
            var psw1 = $('[name="psw1"]').val();
            var psw2 = $('[name="psw2"]').val();
            var mail = $('[name="mail"]').val();

            $.ajax({
            type: "POST",
            url: 'register.php',
            data: {
                'name': name,
                'race': race,
                'psw1': psw1,
                'psw2': psw2,
                'mail': mail
            }, // serializes the form's elements.
            success: function(data)
            {
                $('#noderegister').html(data);
            }
        });
        });
    });
    </script>
    <?php


    exit();
}


$ui = new Ui('Inscription');


if(!empty($_SESSION['playerId'])){


    echo '<div><font color="red">Attention!<br />Il faut une autorisation spéciale pour créer un second personnage.</font><br /><sup>Si vous êtes plusieurs à jouer sur le même terminal, merci de prévenir un Admin.</div>';
}


echo '<h1>Inscription</h1>';

echo '<div>L\'inscription est gratuite et immédiate!<br /><sup>Le multi-compte est interdit.</sup></div>';


$player = new Player(1);


$options = array(
    'name'=>'Gaïa',
    'avatar'=>'img/dialogs/bg/gaia.jpeg',
    'dialog'=>'register',
    'text'=>''
);

echo Ui::get_dialog($player, $options);



?>
<script>
$(document).ready(function(){

    $('.node-option[data-go="register"]').click(function(e){

        // $('#ui-dialog').css({'filter':'blur(0.5em)', 'transition':'filter 0.5s'}).fadeOut('slow');

        var name = $('input[type="text"]').val();

        var race = window.race;

        $.ajax({
            type: "POST",
            url: 'register.php',
            data: {
                'name': name,
                'race': race
            }, // serializes the form's elements.
            success: function(data)
            {
                $('#noderegister').html(data);
            }
        });
    });
});
</script>
