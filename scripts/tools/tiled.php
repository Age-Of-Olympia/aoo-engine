<?php


$player = new Player($_SESSION['playerId']);

$player->get_coords();


if(!empty($_POST['coords']) && !empty($_POST['type']) && !empty($_POST['src'])){


    $coords = $player->coords;

    $coords->x = explode(',', $_POST['coords'])[0];
    $coords->y = explode(',', $_POST['coords'])[1];

    $coordsId = View::get_coords_id($coords);


    if($_POST['type'] == 'tp'){

        $player->go($coords);
        exit('tp');
    }


    if(!in_array($_POST['type'], array('tiles','walls','triggers','elements','dialogs','plants'))){

        exit('error type');
    }


    $values = array(
        'name'=>$_POST['src'],
        'coords_id'=>$coordsId
    );

    $db = new Db();

    echo $_POST['type'];

    $db->insert('map_'. $_POST['type'], $values);


    echo '
    '. $_POST['src'] .' in '. $_POST['coords'];


    if(!empty($_POST['params'])){

        $lastId = $db->get_last_id('map_'. $_POST['type']);

        $sql = 'UPDATE map_'. $_POST['type'] .' SET params = ? WHERE id = ?';

        $db->exe($sql, array($_POST['params'], $lastId));

        echo '
        params: '. $_POST['params'];
    }

    exit();
}


$ui = new Ui($title="Tiled");


$view = new View($player->coords, $p=8, $tiled=true);

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

foreach(File::scan_dir('img/tiles/', $without=".png") as $e){


    $url = 'img/tiles/'. $e .'.png';

    if(!file_exists($url)){

        continue;
    }


    echo '<img
        class="map tile select-name"
        data-type="tiles"
        data-name="'. $e .'"
        src="'. $url .'"
        width="50"
    />';
}

echo '
</div>
';


echo '<h3>Plantes (recoltables, passables)</h3>';

echo '
<div>
';

foreach(File::scan_dir('img/plants/', $without=".png") as $e){

    echo '<img
        class="map plants select-name"
        data-type="plants"
        data-name="'. $e .'"
        src="img/plants/'. $e .'.png"
    />';
}

echo '
</div>
';


echo '<h3>Walls (destructibles, non passables)</h3>';

echo '
<div>
';

foreach(File::scan_dir('img/walls/', $without=".png") as $e){


    $url = 'img/walls/'. $e .'.png';

    if(!file_exists($url)){

        continue;
    }

    echo '<img
        class="map wall select-name"
        data-type="walls"
        data-name="'. $e .'"
        src="'. $url .'"
    />';
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

    echo '<img
        class="map ele"
        data-type="elements"
        data-element="'. explode('.', $e)[0] .'"
        src="img/elements/'. $e .'"
    />';
}

echo '
</div>
';


echo '<h3>Déclencheurs (invisibles)</h3>';

echo '
<div>
';

foreach(File::scan_dir('img/triggers/', $without=".png") as $e){


    $params = '';

    if($e == 'exit'){

        $params = 'direction:';
    }
    elseif($e == 'tp'){

        $params = 'x,y,z,plan';
    }
    elseif($e == 'need'){

        $params = 'item:name:n,spell:spell_name';
    }
    elseif($e == 'enter'){

        $params = 'direction:';
    }


    echo '<img
        class="map trigger select-name"
        data-type="triggers"
        data-params="'. $params .'"
        data-name="'. $e .'"
        src="img/triggers/'. $e .'.png"
    />';
}


echo '<div>Params: <input type="text" id="triggers-params" /></div>';


echo '
</div>
';


echo '<h3>Outils</h3>';

echo '
<div>
';

echo '
<img
    class="map dialog select-name"
    data-type="dialogs"
    data-params="dialog"
    data-name="question"
    src="img/dialogs/question.png"
    />
';

echo '<div>Params: <input type="text" id="dialogs-params" /></div>';

echo '
</div>
';


?>
<script>
$(document).ready(function(){

    $('.case').click(function(e){

        var $selected = $('.selected');

        if(!$selected[0]){

            if(!confirm('TP?')){

                return false;
            }

            $.ajax({
                type: "POST",
                url: 'tools.php?tiled',
                data: {
                    'coords':$(this).data('coords'),
                    'type':'tp',
                    'src':1
                }, // serializes the form's elements.
                success: function(data)
                {
                    // alert(data);
                    document.location.reload();
                }
            });

            return false;
        }

        var src = $selected.attr('src');

        var params = '';

        if($selected.hasClass('ele')){

            src = $selected.data('element');
        }

        if($selected.hasClass('select-name')){

            src = $selected.data('name');
        }

        if($selected.data('params') != null){

            params = $('#'+ $selected.data('type') +'-params').val();
        }


        $.ajax({
            type: "POST",
            url: 'tools.php?tiled',
            data: {
                'coords':$(this).data('coords'),
                'type':$selected.data('type'),
                'src':src,
                'params':params
            }, // serializes the form's elements.
            success: function(data)
            {
                // alert(data);
                document.location.reload();
            }
        });
    });

    $('.map').click(function(e){

        if($(this).data('params')){

            let params = $(this).data('params');

            $('#'+ $(this).data('type') +'-params').val(params);
        }

        $('.map').removeClass('selected').css('border', '0px');
        $(this).addClass('selected').css('border', '1px solid red');
    });

});
</script>
