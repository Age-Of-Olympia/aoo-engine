<?php
use App\Factory\PlayerFactory;
use Classes\Item;
use Classes\View;
use Classes\Ui;
use Classes\Log;

require_once('config.php');


$player = PlayerFactory::legacy($_SESSION['playerId']);

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


    /* Pile uniquement : le mur legacy consomme une unité de pile — une
     * instance (objet usé/nommé) ne doit ni passer la garde ni être
     * "consommée" pour rien. */
    if(!$item->get_n($player, includeInstances: false)){

        exit('error item n');
    }


    $table = 'walls';

    if(!empty($item->data->subtype)){


        $table = $item->data->subtype;
    }

    /* Décrément AVANT la pose, retour vérifié : si la pile ne couvre pas
     * l'unité, aucun mur gratuit. */
    if(!$item->add_item($player, -1)){

        exit('error item n');
    }

    View::put($table, $item->row->name, $coords);

    Log::put($player, $player, $player->data->name." a construit ".$item->data->name. " en ".$coordsTbl[0].",".$coordsTbl[1].",".$player->coords->z, "build", '',  time());

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


/* Même règle que la garde : seule la pile est constructible. */
$itemN = $item->get_n($player, includeInstances: false);

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
