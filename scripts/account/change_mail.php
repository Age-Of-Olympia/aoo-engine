<?php

echo '<div><a href="account.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';

// player
$player = new Player($_SESSION['playerId']);
$player->get_data();

// save new email
if(!empty($_POST['confirmChange'])){
    if(empty($_POST['new'])) exit('Entrez le nouvel email.');

    if(!Str::check_mail($_POST['new'])){
        exit('Email invalide');
    }

    // Hash the new email
    $mail = password_hash($_POST['new'], PASSWORD_DEFAULT);

    $db = new Db();

    // Give XP bonus if first time setting email
    if(empty($player->data->plain_mail) && !$player->data->email_bonus) {
        $player->put_xp(20);
        $sql = 'UPDATE players SET mail = ?, plain_mail = ?, email_bonus = TRUE WHERE id = ?';
        $db->exe($sql, array($mail, $_POST['new'], $player->id));
        echo '<div id="data">Mail changé avec succès!<br/>Bonus de 20 XP reçu pour avoir renseigné votre email!</div>';
    } else {
        $sql = 'UPDATE players SET mail = ?, plain_mail = ? WHERE id = ?';
        $db->exe($sql, array($mail, $_POST['new'], $player->id));
        echo '<div id="data">Mail changé avec succès!</div>';
    }
    exit();
}

echo '
<div id="account-change-mail">
    <h1>Changement de l\'email</h1>
    <form method="POST" action="account.php?changeMail">
        <input type="hidden" name="confirmChange" value="1" />
        <table border="1" align="center">
            <tr>
                <td align="right">Email actuel:</td>
                <td>
                    '. (empty($player->data->plain_mail) ? 'vide' : htmlspecialchars($player->data->plain_mail)) .'
                </td>
            </tr>
            <tr>
                <td align="right">Nouvel email:</td>
                <td>
                    <input type="email" name="new" value="" />
                </td>
            </tr>
            <tr>
                <td colspan="2" align="center">
                    <input type="submit" value="Changer l\'email" id="submit-button" />
                </td>
            </tr>
        </table>';

if(empty($player->data->plain_mail) && !$player->data->email_bonus) {
    echo '<div style="text-align: center; margin-top: 10px;"><strong>Bonus de 20 XP offert pour avoir renseigné votre email!</strong></div>';
}

echo '</form>
</div>

<textarea style="display: none;"></textarea>';
?>

<script>
$(document).ready(function() {
    $("#submit-button").click(function(e) {
        e.preventDefault();

        var newEmail = $("input[name='new']").val();

        if(newEmail == "") {
            alert("Entrez le nouvel email.");
            return false;
        }

        if(!isEmail(newEmail)) {
            alert("L\'email n\'est pas valide.");
            return false;
        }

        $("form").submit();
    });
});

function isEmail(email) {
    var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
    return regex.test(email);
}
</script>
