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


        $_POST['name'] = trim($_POST['name']);


        if(strlen($_POST['name']) < 3){

            exit('error 3 char min');
        }

        if(strlen($_POST['name']) > 30){

            exit('error 30 char max');
        }


        $nameTbl = explode(' ', $_POST['name']);

        foreach($nameTbl as $k=>$e){


            $nameTbl[$k] = ucfirst($e);
        }

        $_POST['name'] = implode(' ', $nameTbl);


        $sql = 'SELECT COUNT(*) AS n FROM players WHERE name = ?';

        $db = new Db();

        $res = $db->exe($sql, $_POST['name']);

        $row = $res->fetch_object();

        if($row->n){

            exit('Ce nom de personnage est déjà pris.');
        }


        $playerId = Player::put_player($_POST['name'], $_POST['race']);

        $player = new Player($playerId);

        $player->get_data();


        // hash
        $hashedPsw = password_hash($_POST['psw1'], PASSWORD_DEFAULT);
        $hashedMail = password_hash($_POST['mail'], PASSWORD_DEFAULT);


        $sql = '
        UPDATE
        players
        SET
        psw = ?,
        mail = ?
        WHERE
        id = ?
        ';

        $db->exe($sql, array($hashedPsw, $hashedMail, $player->id));


        Player::refresh_list();


        // landing welcome msg
        $data = file_get_contents('datas/private/players/welcome.msg.html');

        File::write('datas/private/players/'. $player->id .'.msg.html', $data);


        echo 'Personnage '. $player->data->name .' (matricule '. $player->id .') créé avec succès!<br />';

        echo 'Vous pouvez désormais <a href="index.php?login='. $player->id .'">vous connecter</a> en utilisant son nom ou son matricule.';

        exit();
    }


    $name = (!empty($_POST['name'])) ? $_POST['name'] : '';


    echo '<table border="0" align="center">';
    echo '<tr><td>Nom</td><td><input class="field" type="text" name="name" id="name" value="'. $name .'" /></td></tr>';

    echo '<tr>';
    echo '<td>Race</td>';

    echo '<td><select name="race" class="field">';

    foreach(RACES as $e){


        $raceJson = json()->decode('races', $e);

        $selected = ($e == $_POST['race']) ? 'selected' : '';

        echo '<option value="'. $e .'" '. $selected .'>'. $raceJson->name .'</option>';
    }

    echo '</td>';
    echo '</tr>';

    echo '<tr><td>Mot de passe</td><td><input class="field" type="password" name="psw1" value="" /></td></tr>';
    echo '<tr><td>Confirmez</td><td><input class="field" type="password" name="psw2" value="" /></td></tr>';
    echo '<tr><td>Mail</td><td><input class="field" type="text" name="mail" value="" /></td></tr>';
    echo '<tr><td colspan="2"><label>J\'ai lu et j\'accepte <a href="https://age-of-olympia.net/wiki/doku.php?id=about:cgu" target="_new">les CGU</a> <input type="checkbox" id="cgu" /></label></td></tr>';

    echo '<tr><td colspan="2"><button id="submit">Valider</button></td></tr>';

    echo '</table>';


    ?>
    <script>
    $(document).ready(function(){

        $('#submit').click(function(e){


            var name = $('#name').val();
            var race = $('[name="race"]').val();
            var psw1 = $('[name="psw1"]').val();
            var psw2 = $('[name="psw2"]').val();
            var mail = $('[name="mail"]').val();


            function isEmail(email) {
                var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
                return regex.test(email);
            }

            function isValidName(name) {
                var regex = /^[a-z'àâçéèêëîïôûùü -]*$/i;
                return regex.test(name);
            }


            var checkFields = true;

            $('.field').each(function(){

                if($(this).val() == ''){
                    alert('Merci de remplir tous les champs.');
                    checkFields = false;
                    return false;
                }

            });


            if(!checkFields)
                return false;


            if($('#cgu').prop('checked') == false){
                alert('Merci de cocher la case des CGU.');
                return false;
            }


            if(psw1 != psw2){
                alert('Les mots de passe ne correspondent pas.');
                return false;
            }


            if(!isValidName(name)){
                alert('Le nom de votre personnage ne doit contenir ni chiffre ni caractère spécial.');
                return false;
            }


            if(name.length > 30){
                alert('Le nom de votre personnage doit faire moins de 30 charactères.');
                return false;
            }


            if(name.length < 3){
                alert('Le nom de votre personnage doit faire au moins 3 charactères.');
                return false;
            }


            if(!isEmail(mail)){
                alert('Indiquez un mail valide.');
                return false;
            }


            $('#noderegister').html('Veuillez patienter...');


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


echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';


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
