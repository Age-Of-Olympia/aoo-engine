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

    if($_POST['type'] == 'eraser'){
        include 'tiled_tool/erase_map.php';
        exit('erase');
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


$view = new View($player->coords, $p=10, $tiled=true);

$data = $view->get_view();

echo '
<div style="float: left;">
';

echo $data;

echo '
</div>
';

include 'tiled_tool/display_indestructibles.php';

include 'tiled_tool/display_plants.php';

include 'tiled_tool/display_walls.php';

include 'tiled_tool/display_elements.php';

include 'tiled_tool/display_triggers.php';

include 'tiled_tool/display_tools.php';


?>
<script>
$(document).ready(function(){

    selectPreviousTool();

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
                  document.location='tools.php?tiled';
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
                document.location='tools.php?tiled&selectedTool='+$selected.data('name');
            }
        });
    });

    $('.map').click(function(e){
        if (!$(this).hasClass('selected')) {
          if ($(this).data('params')) {

            let params = $(this).data('params');

            $('#' + $(this).data('type') + '-params').val(params);
          }

          $('.map').removeClass('selected').css('border', '0px');
          $(this).addClass('selected').css('border', '1px solid red');
        } else{
          $(this).removeClass('selected').css('border', '0px');
        }
    });


  function selectPreviousTool(){
    let selectedTool = getParameterByName('selectedTool');

    if (selectedTool) {
      $('.map').filter(function() {

        return $(this).data('name') === selectedTool;
      }).each(function() {
        $(this).addClass('selected').css('border', '1px solid red');
      });
    }

  }
  function getParameterByName(name, url = window.location.href) {
    name = name.replace(/[\[\]]/g, '\\$&');
    let regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
        results = regex.exec(url);
    if (!results) return null;
    if (!results[2]) return '';
    return decodeURIComponent(results[2].replace(/\+/g, ' '));
  }

});
</script>
