<?php

require_once('config.php');


$player = new Player($_SESSION['playerId']);

$player->get_coords();


if(!empty($_POST['coords']) && !empty($_POST['type']) && !empty($_POST['src'])){


    if(!in_array($_POST['type'], array('tiles','walls','triggers','elements'))){

        exit('error type');
    }

    $coords = $player->get_coords();

    $coords->x = explode(',', $_POST['coords'])[0];
    $coords->y = explode(',', $_POST['coords'])[1];


    $coordsId = View::get_coords_id($coords);


    $values = array(
        'name'=>$_POST['src'],
        'coords_id'=>$coordsId
    );

    $db = new Db();

    echo $_POST['type'];

    $db->insert('map_'. $_POST['type'], $values);


    echo $_POST['src'] .' in '. $_POST['coords'];

    exit();
}


$ui = new Ui($title="Tiled");


$view = new View($player->coords, $p=8);

$data = $view->get_view();

echo '
<div style="float: left;">
';

echo $data;

echo '
</div>
';

echo '<h3>Tiles (indestructibles, passables)</h3>';

echo '
<div>
';

foreach(File::scan_dir('img/tiles/') as $e){

    echo '<img class="map tile" type="tiles" src="img/tiles/'. $e .'" />';
}

echo '
</div>
';


echo '<h3>Walls (destructibles, non passables)</h3>';

echo '
<div>
';

foreach(File::scan_dir('img/walls/', $without=".png") as $e){

    echo '<img class="map wall" data-type="walls" data-name="'. $e .'" src="img/walls/'. $e .'.png" />';
}

echo '
</div>
';


echo '<h3>Elements (ajoute un effet, passables)</h3>';

echo '
<div>
';

foreach(File::scan_dir('img/elements/') as $e){

    if(explode('.', $e)[1] == 'gif'){

        continue;
    }

    echo '<img class="map ele" data-type="elements" data-element="'. explode('.', $e)[0] .'" src="img/elements/'. $e .'" />';
}

echo '
</div>
';

?>
<script>
$(document).ready(function(){

    $('.case').click(function(e){

        var $selected = $('.selected');

        var src = $selected.attr('src');

        if($selected.hasClass('ele')){

            src = $selected.data('element');
        }

        if($selected.hasClass('wall')){

            src = $selected.data('name');
        }

        $.ajax({
            type: "POST",
            url: 'tiled.php',
            data: {
                'coords':$(this).data('coords'),
                'type':$selected.data('type'),
                'src':src
            }, // serializes the form's elements.
            success: function(data)
            {
                alert(data);
                document.location.reload();
            }
        });
    });

    $('.tile, .wall, .ele').click(function(e){

        $('.map').removeClass('selected').css('border', '0px');
        $(this).addClass('selected').css('border', '1px solid red');
    });

});
</script>
