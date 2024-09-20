<?php

if(!empty($_POST['text'])){

    $sql = 'UPDATE players SET text = ? WHERE id = ?';

    $db = new Db();

    $db->exe($sql, array($_POST['text'], $player->id));

    $player->refresh_data();

    exit();
}

echo '<div><a href="account.php"><button id="cancel"><span class="ra ra-sideswipe"></span>Retour</button></a></div>';

echo '<textarea rows="20" class="tr-topic1" style="width: 100%;">'. $player->data->text .'</textarea>';

echo '<div><button id="validate">Valider</button></div>';

?>
<script>
$('#validate').click(function(e){

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

$('#cancel').click(function(e){
      document.location = 'account.php';
});
</script>
