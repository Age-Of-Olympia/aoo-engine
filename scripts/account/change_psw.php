<?php
use Classes\ActorInterface;
use Classes\Db;

echo '<div><a href="account.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';


// player
$player = new ActorInterface($_SESSION['playerId']);

$player->get_row();


// save new psw
if( !empty( $_POST['confirmChange'] ) ){


    if( empty( $_POST['old'] ) ) exit('Entrez le mot de passe actuel.');

    if( !password_verify($_POST['old'], $player->row->psw) ) exit('Le mot de passe actuel n\'est pas le bon.');

    if( empty( $_POST['new'] ) ) exit('Entrez le nouveau mot de passe.');

    if( empty( $_POST['new2'] ) ) exit('Entrez le mot de passe de confirmation.');

    if( $_POST['new'] != $_POST['new2'] ) exit('Le mot de passe de confirmation n\'est pas le même que le nouveau mot de passe.');

    $hashedPsw = password_hash( $_POST['new'], PASSWORD_DEFAULT );

    $sql = '
    UPDATE
    players
    SET
    psw = ?
    WHERE
    id = ?
    ';

    $db = new Db();

    $db->exe($sql, array($hashedPsw, $player->id));

    echo '
    <div id="account-change-psw">
        Mot de passe changé!<br />
    </div>';

    exit();
}

echo '
<div id="account-change-psw">

    <h1>Changement du mot de passe</h1>

    <form method="POST" action="account.php?changePsw">

        <input type="hidden" name="confirmChange" value="1" />

        <table border="1" align="center">
            <tr>
                <td align="right">Mot de passe actuel:</td>
                <td>
                    <input type="password" name="old" value="" />
                </td>
            </tr>
            <tr>
                <td align="right">Nouveau mot de passe:</td>
                <td>
                    <input type="password" name="new" value="" />
                </td>
            </tr>
            <tr>
                <td align="right">Confirmer nouveau mot de passe:</td>
                <td>
                    <input type="password" name="new2" value="" />
                </td>
            </tr>
            <tr>
                <td colspan="2" align="center">

                    <input type="submit" value="Changer le mot de passe" id="submit-button" />
                </td>
            </tr>
        </table>
    </form>
</div>
';


// fake textarea to disable shortcuts
echo '<textarea style="display: none;"></textarea>';


?>
<script>
$('#submit-button').click(function(e){

    e.preventDefault();


    var old = $('input[name="old"]').val();
    var newPsw = $('input[name="new"]').val();
    var newPsw2 = $('input[name="new2"]').val();

    if(old == '' || newPsw == '' || newPsw2 == ''){
        alert('Remplissez tous les champs.');
        return false;
    }

    if(newPsw != newPsw2){
        alert('Le mot de passe de confirmation n\'est pas le même que le nouveau mot de passe.');
        return false;
    }

    $('form').submit();

});
</script>
