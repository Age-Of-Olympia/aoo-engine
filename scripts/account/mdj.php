<?php
use Classes\Db;
use Classes\Log;

$ErrorMessageChangeSession = "Changement de personnage avant sauvegarde mdj";

/* isset et non !empty : un message du jour VIDE est un choix — ne rien
 * afficher au-dessus de son personnage. Avec !empty, l'effacement était
 * impossible : le formulaire retombait sur l'affichage et l'ancien
 * texte restait en base. */
if(isset($_POST['text'])){
    if ($_POST['author-id']!=$player->id) {
        exit($ErrorMessageChangeSession);
    }
    $db = new Db();

    /* Edit = a new entry stamped with the original time, the old one
     * retyped mdj_edited: out of every feed, kept as history. Only the
     * author's CURRENT message qualifies, the past stays as written. */
    $edited = null;
    $editId = (int) ($_POST['edit-id'] ?? 0);
    if ($editId > 0) {
        $edited = $db->exe(
            // Same order as the feed: an edit keeps its original time,
            // so the current message is the latest by time, not by id
            'SELECT id, time FROM players_logs WHERE player_id = ? AND type = ? ORDER BY time DESC, id DESC LIMIT 1',
            array($player->id, 'mdj')
        )->fetch_object();
        if ($edited === null || (int) $edited->id !== $editId) {
            exit('Message introuvable.');
        }
    }

    $db->exe('UPDATE players SET text = ? WHERE id = ?', array($_POST['text'], $player->id));
    $player->refresh_data();

    $log = trim((string) $_POST['text']) === ''
        ? 'Effacement du message du jour.'
        : 'Changement de message du jour.';

    $details = '<div class="action-details">'.$_POST['text'].'</div>';

    Log::put($player, $player, $log, type:"mdj", hiddenText:$details, logTime: $edited?->time ?? '');

    if ($edited !== null) {
        $db->exe('UPDATE players_logs SET type = ? WHERE id = ?', array('mdj_edited', $edited->id));
    }

    // Un changement de message du jour ne déplace rien et ne modifie aucun
    // pixel de la carte : il n'a pas d'image à lui. On le rattache donc au
    // fichier d'events de la dernière capture, dont l'état visuel est encore
    // celui qui vaut. Au montage il devient une bulle sur cette image.
    try {
        (new \App\Service\ScreenshotService())->attachEventToLastCapture([
            'type'      => 'mdj',
            'at'        => time(),
            'player_id' => (int) $player->id,
            'text'      => $_POST['text'],
        ], $player);
    } catch (Throwable $e) {
        error_log('Rattachement du mdj a la derniere capture impossible : ' . $e->getMessage());
    }

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
            if(data.includes('<?= $ErrorMessageChangeSession;?>')){
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
