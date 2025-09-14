<?php
use Classes\Db;
use Classes\Log;

$ErrorMessageChangeSession = "Changement de personnage avant sauvegarde mdj";

if(!empty($_POST['text'])){
    if ($_POST['author-id']!=$player->id) {
        exit($ErrorMessageChangeSession);
    }
    $sql = 'UPDATE players SET text = ? WHERE id = ?';

    $db = new Db();

    $db->exe($sql, array($_POST['text'], $player->id));

    $player->refresh_data();

    $log = 'Changement de message du jour.';

    $details = '<div class="action-details">'.$_POST['text'].'</div>';

    Log::put($player, $player, $log, type:"mdj", hiddenText:$details);

    exit();
}

echo '<div><a href="account.php"><button id="cancel"><span class="ra ra-sideswipe"></span>Retour</button></a></div>';

echo '<textarea rows="20" class="tr-topic1" style="width: 100%;">'. $player->data->text .'</textarea>';

echo '
    <div id="validation-mdj">
        <div class="portrait">
            <input type="hidden" id="id-auteur-mdj" value="'.$player->id.'"/>
            <img src="'. $player->data->portrait .'" />
        </div>

        <div>
            <button id="validate">Valider</button>
        </div>
    </div>';

?>
<script>
$('#validate').click(function(e){

    let text = $('textarea').val();
    let authorId = $('#id-auteur-mdj').val();

    $.ajax({
        type: "POST",
        url: 'account.php?mdj',
        data: {'text':text,
            'author-id':authorId
        }, // serializes the form's elements.
        success: function(data)
        {
            if(data.includes('<?echo $ErrorMessageChangeSession;?>')){
                alert('Erreur lors de la sauvegarde du Mdj, changement de personnage possible, veuillez retenter.')
            }else{
                alert('Votre Message du jour a bien été changé!');
            }

            document.location = 'account.php';
        }
    });
});

$('#cancel').click(function(e){
      document.location = 'account.php';
});
</script>
