<?php

if(!empty($_POST['text'])){

    $sql = 'UPDATE players SET text = ? WHERE id = ?';

    $db = new Db();

    $db->exe($sql, array($_POST['text'], $player->id));

    $player->refresh_data();

    exit();
}

echo '<div><a href="account.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';

echo '<textarea style="height: 150px; width: 500px;">'. $player->data->text .'</textarea>';

echo '<div><button>Valider</button></div>';

?>
<script>
$('button').click(function(e){

    let text = $('textarea').val();

    $.ajax({
        type: "POST",
        url: 'account.php?mdj',
        data: {'text':text}, // serializes the form's elements.
        success: function(data)
        {
            alert('Votre Message du jour a bien été changé!');

            document.location = 'account.php';
        }
    });
});
</script>
