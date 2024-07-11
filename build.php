<?php


require_once('config.php');


$player = new Player($_SESSION['playerId']);


if(!empty($_POST['itemId']) && !empty($_POST['coords'])){


    $coordsTbl = explode(',', $_POST['coords']);

    if(count($coordsTbl) != 2){

        exit('error coords');
    }

    list($x, $y) = $coordsTbl;

    $player->get_coords();

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

    if(!$item->get_n($player)){

        exit('error item n');
    }


    View::put('walls', $item->row->name, $coords);

    exit();
}


if(!isset($_GET['itemId'])){

    exit('error item id');
}


$item = new Item($_GET['itemId']);



if(!$item->get_n($player)){

    exit('error item n');
}


$item->get_data();


$ui = new Ui('Construire '. $item->data->name);


echo '<div><a href="inventory.php#'. $item->id .'"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';


$view = new View($player->get_coords(), $p=1);


echo '<h1>Construire '. $item->data->name .'</h1>';


echo '<img src="'. $item->data->mini .'" />';


echo '<p>Construire une structure coûte 1 Action.</p>';

echo $view->get_view();


?>
<script>
$(document).ready(function(){


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

                // alert(data);
                document.location.reload();
            }
        });
    });
});
</script>
