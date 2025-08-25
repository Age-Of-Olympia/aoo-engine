<?php
use Classes\Player;
use Classes\Item;
use Classes\View;
use Classes\Ui;

require_once('config.php');


$player = new Player($_SESSION['playerId']);

$player->getCoords();

$player->get_caracs();

$aLeft = $player->getRemaining('a');


if(!empty($_POST['itemId']) && !empty($_POST['coords'])){


    if(!$aLeft){

        exit('error a');
    }


    $coordsTbl = explode(',', $_POST['coords']);

    if(count($coordsTbl) != 2){

        exit('error coords');
    }

    list($x, $y) = $coordsTbl;

    $player->getCoords();

    $coords = (object) array(
        'x'=>$x,
        'y'=>$y,
        'z'=>$player->coords->z,
        'plan'=>$player->coords->plan
    );


    $coordsTaken = View::get_coords_taken($player->coords);


    if(in_array($_POST['coords'], $coordsTaken)){

        exit('error coords taken');
    }


    $item = new Item($_POST['itemId']);

    $item->get_data();


    if(!$item->get_n($player)){

        exit('error item n');
    }


    $table = 'walls';

    if(!empty($item->data->subtype)){


        $table = $item->data->subtype;
    }

    View::put($table, $item->row->name, $coords);


    $item->add_item($player, -1);


    $player->putBonus(['a'=>-1]);


    exit();
}


if(!isset($_GET['itemId'])){

    exit('error item id');
}


$item = new Item($_GET['itemId']);


$item->get_data();


$ui = new Ui('Construire '. $item->data->name);


echo '<div><a href="inventory.php#'. $item->id .'"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';


$view = new View($player->coords, p:1);


echo '<h1>Construire</h1>';


$itemN = $item->get_n($player);

$nText = (!$itemN) ? '<font color="red">x'. $itemN .'</font>' : 'x'. $itemN ;


echo '
<table border="1" class="marbre" align="center">
<tr>
    <th colspan="2">'. $item->data->name .'</th>
</tr>
<tr>
    <td><img src="'. $item->data->mini .'" /></td>
    <td align="left">'. $nText .'<br />Actions: '. $aLeft .'</td>
</tr>
</table>
<br />
';

echo $view->get_view();


echo '<sup>Construire une structure coûte 1 Action.</sup>';


?>
<script>
$(document).ready(function(){


    window.aLeft = <?php echo $aLeft ?>;


    window.itemId = <?php echo $item->id ?>;


    $('#svg-view')
    .html($('#svg-view').html()+ '<image id="build" x="0" y="0" style="z-index: 100; display: none;" class="blink" href="<?php echo $item->data->mini ?>" />');


    $('.case').click(function(e){


        var $case = $(this);

        var coords = $case.data('coords');


        if(coords == '<?php echo $player->coords->x .','. $player->coords->y ?>'){


            document.location = 'index.php';

            return false;
        }


        if(!window.aLeft){

            alert('Vous n\'avez plus d\'Actions disponibles ce tour-ci.');

            return false;
        }


        var i = $(this).attr('x');
        var j = $(this).attr('y');


        let [x, y] = coords.split(',');


        $('#build')
            .show()
            .attr({'x': i, 'y': j})
            .data('coords', x +','+ y);
    });

    $('#build').click(function(e){

        var coords = $(this).data('coords');

        $.ajax({
            type: "POST",
            url: 'build.php',
            data: {
                'coords':coords,
                'itemId': window.itemId
            }, // serializes the form's elements.
            success: function(data)
            {

                //alert(data);
                document.location.reload();
            }
        });
    });
});
</script>
