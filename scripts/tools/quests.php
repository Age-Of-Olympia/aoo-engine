<?php
use Classes\Ui;
use Classes\Db;
use Classes\ActorInterface;
use Classes\Quest;

$actions = array(
    'newQuest',
    'addPlayer',
    'editName',
    'editText'
);


if(!empty($_POST['action'])){

    if(!in_array($_POST['action'], $actions)){

        exit('error action');
    }


    ob_start();

    switch($_POST['action']){


        case 'newQuest':
            ?>
            <script>

            </script>
            <?php
        break;

        case 'addPlayer':
            ?>
            <script>

            </script>
            <?php
        break;

        default:

            echo 'error action';
        break;
    }

    $data = ob_get_clean();

    echo '<div id="data">'. $data .'</div>';

    exit();
}


$ui = new Ui('Quêtes');


$sql = '
SELECT
*,
pq.status AS pStatus
FROM
quests AS q
LEFT JOIN
players_quests AS pq
ON
q.id = pq.quest_id
';

$db = new Db();

$res = $db->exe($sql);


echo '
<style>
    .quest-actions .action{ width: 100%;}
</style>
';


echo '
<div id="data">
<table border="1" class="marbre" align="center">
<tr>
    <th colspan="2">Quêtes</th>
    <th>Joueurs</th>
';

while($row = $res->fetch_object()){


    if(!empty($row->player_id)){

        ob_start();

        echo '<div>';

        $player = new ActorInterface($row->player_id);

        $player->get_data();

        echo '
        <div data-player-id="'. $player->id .'">
            <a href="#" class="player">#'. $player->id .' '. $player->data->name .'</a>
            (<a href="#" class="status">'. $row->status .'</a>)
        </div>
        ';

        echo '</div>';

        $text = ob_get_clean();
    }

    else{

        $text = 'aucun joueur';
    }


    if(!isset($qCursor) || $qCursor != $row->quest_id){


        $qCursor = $row->quest_id;


            echo '
        </tr>
        ';

        echo '
        <tr data-quest-id="'. $row->id .'">
            ';

            echo '
            <th>'. $row->name .'<br /><sup>'. $row->text .'</sup>
            <div class="quest-actions">
                <button class="action">addPlayer</button><br />
                <button class="action">editName</button><br />
                <button class="action">editText</button><br />
                <button class="action">deleteQuest</button>
            </div>
            </th>
            ';


            echo '
            <td><img src="'. Quest::get_img($row->name) .'" /></td>
            ';

            echo '
            <td align="left">
            ';

            echo $text;



            continue;
    }


    echo $text;


    echo '
    </td>
    ';
}

echo '
</tr>
</table>
</div>
';

echo '<br />';


// foreach($actions as $e){

    echo '<button class="action">newQuest</button>';
// }

?>
<script>
function force_cmd(text, cmd){

    if(text){

        var value = prompt(text);
        if(!value) return false;
    }


    create_console();

    if(text){

        $('#input-line').val(cmd +' "'+ value +'"').focus();
    }
    else{

        $('#input-line').val(cmd).focus();
    }

    submit_cmd();

    return false;
}

$(document).ready(function(){

    var actions = ['<?php echo implode("','", $actions) ?>'];

    $('.action').click(function(e){


        var action = $(this).html();

        if(!actions.includes(action)){

            return false;
        }


        var questId = $(this).closest('tr').data('quest-id');


        if(action == 'newQuest'){


            let text = 'Nom de la Quête';

            let cmd = 'quest create';

            return force_cmd(text, cmd);
        }

        if(action == 'addPlayer'){


            let text = 'Id ou Nom du Player';
            var value = prompt(text);
            if(!value) return false;

            let cmd = 'quest player "'+ value +'" start '+ questId;

            create_console();

            $('#input-line').val(cmd).focus();

            submit_cmd();

            return false;
        }

        if(action == 'editName'){


            let text = 'Nom de la Quête';

            let cmd = 'quest edit '+ questId +' name';

            return force_cmd(text, cmd);
        }

        if(action == 'editText'){


            let text = 'Sous-titre de la Quête';

            let cmd = 'quest edit '+ questId +' text';

            return force_cmd(text, cmd);
        }


        $.ajax({
            type: "POST",
            url: 'tools.php?quests',
            data: {
                'action':action
            }, // serializes the form's elements.
            success: function(data)
            {

                var content = $('<div>').html(data).find('#data').html();
                $('#data').html(content);
                // document.location.reload();
            }
        });
    });


    $('.player').click(function(e){


        e.preventDefault();

        var playerId = $(this).closest('div').data('player-id');

        let text = false;

        let cmd = 'quest player '+ playerId;

        return force_cmd(text, cmd);
    });


    $('.status').click(function(e){


        e.preventDefault();

        var questId = $(this).closest('tr').data('quest-id');

        var playerId = $(this).closest('div').data('player-id');

        let text = false;

        let cmd = 'quest player '+ playerId +' infos '+ questId;

        return force_cmd(text, cmd);
    });
});
</script>
