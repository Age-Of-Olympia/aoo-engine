<?php
use Classes\Db;

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
            /* Panneau HUD : retour sur la fiche, où l'histoire vit
             * désormais (le Profil du HUD n'a plus l'entrée) */
            if(window.hudOpenPanel){

                aooAlert('Votre Histoire a bien été changée!');
                window.hudOpenPanel('load_infos.php?targetId=<?php echo $player->id; ?>', 'Personnage');
                return;
            }

            alert('Votre Histoire a bien été changée!');

            document.location = 'account.php';
        }
    });
});
</script>
