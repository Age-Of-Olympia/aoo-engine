<?php


echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';

// generate new psw
if(!empty($_GET['psw'])){


    $sql = '
    SELECT * FROM
    players_psw
    WHERE
    uniqid = ?
    ';

    $db = new Db();

    $result = $db->exe($sql, $_GET['psw']);

    // no result
    if(!$result->num_rows){
        exit('Erreur code de génération');
    }

    $row = $result->fetch_assoc();


    // generate psw
    $psw = bin2hex(openssl_random_pseudo_bytes(4));

    // hash
    $hashedPsw = password_hash( $psw, PASSWORD_DEFAULT );


    // change psw
    $sql = '
    UPDATE
    players
    SET
    psw = ?
    WHERE
    id = ?
    ';

    // update db
    $db->exe($sql, $values=array($hashedPsw, $row['player_id']));


    // delete reset psw

    // delete values
    $values = array(
        'player_id'=>$row['player_id']
    );

    // delete db
    $db->delete('players_psw', $values);


    // result
    echo '
    Votre mot de passe a été changé pour le suivant:<br />
    <br />
    '. $psw .'
    ';


    exit();
}


// rec
if(!empty($_POST['name']) && !empty($_POST['mail'])){


    // db link
    $db = new Db();


    // firewall
    include('config/firewall.php');


    // mat
    if(is_numeric($_POST['name'])){
        $player = new Player($_POST['name']);
    }

    // name
    else{

        $json = new Json();
        $listPlayerJson = $json->decode('players', 'list');

        foreach($listPlayerJson as $e){

            if($e->name == $_POST['name']){
                $player = new Player($e->id);
            }
        }
    }


    // error player
    if(!isset($player)){
        exit('Erreur: aucun personnage associé à cet identifiant.');
    }


    $player->get_row();


    // error mail
    if(!password_verify(strtolower($_POST['mail']), $player->row->mail)){

        // on vérifie si l'user n'avait pas mis une maj à la première lettre de son mail avant le patch
        if(!password_verify(ucfirst(strtolower($_POST['mail'])), $player->row->mail)){

            exit('Erreur: l\'adresse mail n\'est pas la bonne.');
        }
    }


    // check reset psw time
    $result = $db->get_single_player_id('players_psw', $player->id);

    // mail already sent
    if($result->num_rows){

        // check time cooler
        $row = $result->fetch_assoc();

         // (5min)
        if($row['sentTime'] > time() - 300){
            exit('Veuillez attendre 5 minutes avant de réessayer.');
        }

        // > 5min

        // delete old reset psw

        // delete values
        $values = array(
            'player_id'=>$row['player_id']
        );

        // delete db
        $db->delete('players_psw', $values);
    }


    // end
    echo '
    Un mail a été envoyé à l\'adresse:<br />
    '. $_POST['mail'] .'<br />
    <sup>Pensez à vérifier dans vos spams</sup>
    ';


    // generate uniqid
    $uniqid = uniqid();

    // store uniqid

    // insert values
    $values = array(
        'player_id'=>$player->id,
        'uniqid'=>$uniqid,
        'sentTime'=>time()
    );

    // insert db
    $db->insert('players_psw', $values);


    $to      = $_POST['mail'];
    $subject = 'Récupération de mot de passe';
    $message = 'Copiez-coller le lien suivant dans votre navigateur pour générer un nouveau mot de passe: https://age-of-olympia.net/index.php?resetPsw&psw='. $uniqid .'';
    $headers = 'From: admin@age-of-olympia.net'       . "\r\n" .
                 'Reply-To: admin@age-of-olympia.net' . "\r\n" .
                 'X-Mailer: PHP/' . phpversion();

    mail($to, $subject, $message, $headers);



    // firewall block
    include('config/firewall_block.php');


    exit();
}


// title
echo '
<h1>Récupération du mot de passe</h1>
';

echo '
<form method="POST" action="index.php?resetPsw" id="reset_psw">

    <table align="center">
        <tr>
            <td align="right">Précisez votre matricule ou nom</td>
            <td><input type="text" name="name" /></td>
        </tr>
        <tr>
            <td align="right">Précisez votre adresse mail</td>
            <td><input type="text" name="mail" /></td>
        </tr>
    </table>

    <input type="submit" value="Envoyer un lien" id="submit" />

</form>
';

?>
<script>
function isEmail(email) {
    var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
    return regex.test(email);
}

function isValidName(name) {
    var regex = /^[a-z'àâçéèêëîïôûùü -]*$/i;
    return regex.test(name);
}

function isNumeric(str) {
  if (typeof str != "string") return false // we only process strings!
  return !isNaN(str) && // use type coercion to parse the _entirety_ of the string (`parseFloat` alone does not do this)...
         !isNaN(parseFloat(str)) // ...and ensure strings of whitespace fail
}

$('#submit').click(function(e){

    var email = $('input[name="mail"]').val();
    var name = $('input[name="name"]').val();

    if(!isNumeric(name) && !isValidName(name)){

        alert('Nom invalide.');
        return false;
    }
    else if(!isEmail(email)){

        alert('Email invalide.');
        return false;
    }
});
</script>
