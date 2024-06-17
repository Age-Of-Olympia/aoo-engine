<?php

require_once('config.php');


if(!isset($_POST['coords'])){

    exit('error coords');
}


$coords = explode(',', $_POST['coords']);

$x = $coords[0];
$y = $coords[1];


if(!is_numeric($x) || !is_numeric($y)){

    exit('error coords numeric');
}


$player = new Player($_SESSION['playerId']);

$coords = $player->get_coords();


$db = new Db();


$sql = '
SELECT
p.id AS id,
name
FROM
map_elements AS p
INNER JOIN
coords AS c
ON
p.coords_id = c.id
WHERE
c.x = ?
AND
c.y = ?
AND
c.z = ?
AND
c.plan = ?
';

$res = $db->exe($sql, array($x, $y, $coords->z, $coords->plan));


if($res->num_rows){


    while($row = $res->fetch_object()){


        echo '
        <div class="case-infos">
            ';


            if(!file_exists('img/elements/'. $row->name .'.png')){

                echo '<img src="img/elements/'. $row->name .'.webp" />';
            }
            else{

                echo '<img src="img/elements/'. $row->name .'.png" />';
            }

            echo '
            <div class="text">
                Élement ('. $row->name .')<br />
                ';

                if(!empty(EFFECTS_RA_FONT[$row->name])){

                    echo 'Effet: <span class="ra '. EFFECTS_RA_FONT[$row->name] .'"></span>';
                }
                else{

                    echo 'Aucun effet.';
                }

                echo '
            </div>
        </div>
        ';
    }

}

$sql = '
SELECT
p.id AS id,
name
FROM
players AS p
INNER JOIN
coords AS c
ON
p.coords_id = c.id
WHERE
c.x = ?
AND
c.y = ?
AND
c.z = ?
AND
c.plan = ?
';

$res = $db->exe($sql, array($x, $y, $coords->z, $coords->plan));


if($res->num_rows){


    while($row = $res->fetch_object()){


        $target = new Player($row->id);

        $targetJson = json()->decode('players', $target->id);


        $dataName = $target->row->name;

        foreach($target->get_effects() as $e){

            $dataName .= ' <span class="ra '. EFFECTS_RA_FONT[$e] .'"></span>';
        }


        $dataImg = '';

        foreach($player->get_actions() as $e){


            $actionJson = json()->decode('actions', $e);


            if($player->id == $target->id){

                if($actionJson->targetType != 'self'){

                    continue;
                }
            }
            elseif($actionJson->targetType == 'self'){

                continue;
            }


            $dataImg .= '<button
                class="action"

                data-target-id="'. $target->id .'"
                data-action="'. $e .'"
                >
                <span class="ra '. $actionJson->raFont .'"></span>
                <span class="action-name">'. $actionJson->name .'</span>
                </button><br/>';
        }


        if($target->have_option('isMerchant')){

            $dataImg .= '<a href="merchant.php?targetId='. $target->id .'"><button class="action"><span class="ra ra-ammo-bag"></span> Marchander</button></a>';
        }


        $raceJson = json()->decode('races', $target->row->race);

        $dataType = 'Personnage - <i>'. $raceJson->name .'</i>';


        $data = (object) array(
            'bg'=>$targetJson->portrait,
            'name'=>$dataName,
            'img'=>$dataImg,
            'type'=>$dataType,
            'text'=>$targetJson->text
        );

        $card = Ui::get_card($data);
    }
}

else{


    // no player

    $sql = '
    SELECT
    p.id AS id,
    name,
    damages
    FROM
    map_walls AS p
    INNER JOIN
    coords AS c
    ON
    p.coords_id = c.id
    WHERE
    c.x = ?
    AND
    c.y = ?
    AND
    c.z = ?
    AND
    c.plan = ?
    ';

    $res = $db->exe($sql, array($x, $y, $coords->z, $coords->plan));


    if($res->num_rows){


        while($row = $res->fetch_object()){


            echo '
            <div class="case-infos">
                <img src="img/walls/'. $row->name .'.png" title="#'. $row->id .'"/>

                <div class="text">
                    Structure non-passable.<br />
                    ';

                    if(!empty(WALLS_PV[$row->name])){

                        echo 'Destructible ('. Str::get_status($row->damages, WALLS_PV[$row->name]) .').';
                    }
                    else{

                        echo 'Indestructible.';
                    }

                    echo '<br />';

                    $sql = 'SELECT * FROM altars WHERE wall_id = ?';

                    $res = $db->exe($sql, $row->id);

                    if($res->num_rows){

                        $row = $res->fetch_object();

                        $god = new Player($row->player_id);

                        echo 'Altar du Dieu '. $god->row->name .'.';

                        // card
                        $god->get_data();

                        $actions = '';

                        if($god->id != $player->row->godId){

                            $actions = '
                            <button
                                class="action"
                                data-url="venerate.php"
                                data-action="venerate"
                                data-target-id="'. $row->wall_id .'"
                            ><span class="ra ra-candle"></span> Vénérer
                            </button>';
                        }


                        $dataText = "Description de l'Altar.";

                        $data = (object) array(
                            'bg'=>$god->data->portrait,
                            'name'=>'Altar du Dieu '. $god->row->name,
                            'img'=>$actions,
                            'type'=>'Altar',
                            'text'=>$dataText
                        );

                        $card = Ui::get_card($data);

                    }

                    echo '
                </div>
            </div>
            ';
        }

    }
    else{


        // no wall

        $coordsArround = View::get_coords_arround($player->coords, $p=1);

        if(in_array($x .','. $y, $coordsArround)){

            $player->get_caracs();

            $i = ($x - $player->coords->x + $player->caracs->p) * 50;
            $j = (-$y + $player->coords->y + $player->caracs->p) * 50;

            ?>
            <script>
            $('#go-rect')
                .show()
                .attr({'x':<?php echo $i ?>, 'y':<?php echo $j ?>})
                .data('coords', '<?php echo $x .','. $y ?>');

            $('#go-img').show().attr({'x':<?php echo $i ?>, 'y':<?php echo $j -20 ?>});
            </script>
            <?php
        }
        else{

            ?>
            <script>
            $('#go-rect').hide();
            $('#go-img').hide();
            </script>
            <?php
        }
    }


    // dialogs
    $sql = '
    SELECT
    params
    FROM
    map_dialogs AS p
    INNER JOIN
    coords AS c
    ON
    p.coords_id = c.id
    WHERE
    c.x = ?
    AND
    c.y = ?
    AND
    c.z = ?
    AND
    c.plan = ?
    ';

    $res = $db->exe($sql, array($x, $y, $coords->z, $coords->plan));

    if($res->num_rows){


        $row = $res->fetch_object();


        echo Ui::print_dialog($row->params);
    }
}


// coords
echo '<div id="case-coords"><button>x'. $x .',y'. $y .',z'. $coords->z .'</button></div>';


if(!empty($card)){

    echo $card;

    ?>
    <script>
    $(document).ready(function(){

        $('#go-rect').hide();
        $('#go-img').hide();

        $('.action').click(function(e){

            $('.action').prop('disabled', true);
            $('#action-data').hide().html();

            let url = 'action.php';

            if($(this).data('url')){

                url = $(this).data('url');
            }

            let targetId = $(this).data('target-id');
            let action = $(this).data('action');

            if(action == 'close-card'){

                $('#ui-card').hide();
                return false;
            }


            $.ajax({
                type: "POST",
                url: url,
                data: {'action':action, 'targetId':targetId}, // serializes the form's elements.
                success: function(data)
                {
                    // alert(data);
                    $('#action-data').html(data).fadeIn();
                    $('.action').prop('disabled', false);
                    // document.location.reload();
                }
            });
        })
        .on('mouseover', function(e){

            $(this).find('.action-name').show();
        })
        .on('mouseout', function(e){

            $(this).find('.action-name').hide();
        });
    });
    </script>
    <?php
}
