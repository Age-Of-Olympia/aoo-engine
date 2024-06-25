<?php

$destTbl = Forum::get_top_dest($topJson);

if (!in_array($player->id, $destTbl)) {
    exit('Accès refusé');
}

if (!empty($_POST['addDest'])) {
    Forum::add_dest($_POST['addDest'], $topJson, $destTbl);
    exit();
}

if (!empty($_POST['removeDest'])) {
    Forum::remove_dest($_POST['removeDest'], $topJson, $destTbl);
    exit();
}

echo '
<div class="dest-container">
    <div id="dest">
        Destinataires:
        ';

        foreach ($destTbl as $e) {


            $dest = new Player($e);
            $dest->get_data();

            $raceJson = json()->decode('races', $dest->data->race);

            echo '
            <span
                data-id="' . $dest->id . '"
                class="cartouche dest"
                style="background: ' . $raceJson->bgColor . '; color: ' . $raceJson->color . ';"
                >
                ' . $dest->data->name . '
            </span>
            ';
        }

        $blink = (count($destTbl) == 1) ? 'blink' : '';

        echo '
        <span
        id="add-dest"
        class="cartouche ' . $blink . '"
        style="background: #aaa;"
        >
            +Ajouter
        </span>
    </div>';


    $playersJson = json()->decode('players', 'list');


    if (!$playersJson) {

        Player::refresh_list();
        $playersJson = json()->decode('players', 'list');
    }

    echo '<select id="dest-list">
        <option disabled selected>Sélectionnez un personnage</option>';

    foreach ($playersJson as $e) {


        echo '<option value="' . $e->id . '">' . $e->name . ' (mat.' . $e->id . ')</option>';
    }

    echo '</select>';

echo '</div>';
?>

<script>
$(document).ready(function() {


    $('#add-dest').click(function(e) {


        $('#dest').hide();
        $('#dest-list').show();
    });


    $('#dest-list').on('change', function(e) {


        var dest = $(this).val();

        $.ajax({
            type: "POST",
            url: 'forum.php?topic=<?php echo $topJson->name ?>',
            data: {'addDest': dest},
            success: function(data) {
                document.location.reload();
            }
        });
    });


    $('.dest').click(function(e) {


        var dest = $(this).data('id');

        if (!confirm('Supprimer ce personnage de la conversation?')) {
            return false;
        }

        $.ajax({
            type: "POST",
            url: 'forum.php?topic=<?php echo $topJson->name ?>',
            data: {'removeDest': dest},
            success: function(data) {
                document.location.reload();
            }
        });
    });
});
</script>
