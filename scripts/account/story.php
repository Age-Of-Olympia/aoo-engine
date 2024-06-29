<?php

if(!empty($_POST['text'])){

    $sql = 'UPDATE players SET story = ? WHERE id = ?';

    $db = new Db();

    $db->exe($sql, array($_POST['text'], $player->id));

    $player->refresh_data();

    exit();
}

echo '<div><a href="account.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';

echo '<textarea rows="20" class="tr-topic1" style="width: 100%;">'. $player->data->story .'</textarea>';

echo '<div><button>Valider</button></div>';

?>
<script>
$('button').click(function(e){

    let text = $('textarea').val();

    $.ajax({
        type: "POST",
        url: 'account.php?story',
        data: {'text':text}, // serializes the form's elements.
        success: function(data)
        {
            alert('Votre Histoire a bien été changée!');

            document.location = 'account.php';
        }
    });
});
</script>
