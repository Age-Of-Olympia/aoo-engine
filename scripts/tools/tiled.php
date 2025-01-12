<?php


$player = new Player($_SESSION['playerId']);

$player->get_coords();

if(!empty($_POST['delete'])){
    $coordsId = $_POST['coord-id'];
    $type = $_POST['type'];
    include 'tiled_tool/erase_case.php';
    exit();
}

if(!empty($_POST['zone']) && !empty($_POST['type']) && !empty($_POST['src'])){

    $zoneData = [
        'beginX' => intval($_POST['zone']['beginX']),
        'beginY' => intval($_POST['zone']['beginY']),
        'endX' => intval($_POST['zone']['endX']),
        'endY' => intval($_POST['zone']['endY'])
    ];
    
    $allCoords = [];
    
    for ($x = min($zoneData['beginX'], $zoneData['endX']); $x <= max($zoneData['beginX'], $zoneData['endX']); $x++) {
        for ($y = min($zoneData['beginY'], $zoneData['endY']); $y <= max($zoneData['beginY'], $zoneData['endY']); $y++) {

            $coords = $player->coords;

            $coords->x = $x;
            $coords->y = $y;
    
            $coordsId = View::get_coords_id($coords);
    
            // keep all coords ids
            $allCoords[] =  $coordsId;
        }
    }
    
    
    // Create or erase tile for each in the coords zone
    foreach ($allCoords as $coordsId) {
       include 'tiled_tool/erase_or_create_tile.php';
    }




  exit();
}

if(!empty($_POST['coords']) && !empty($_POST['type']) && !empty($_POST['src'])){


    $coords = $player->coords;

    $coords->x = explode(',', $_POST['coords'])[0];
    $coords->y = explode(',', $_POST['coords'])[1];

    $coordsId = View::get_coords_id($coords);


    if($_POST['type'] == 'tp'){

        $player->go($coords);
        exit('tp');
    }

    if($_POST['type'] == 'info'){
        include 'tiled_tool/tile_info.php';
        exit('infos');
    }

    include 'tiled_tool/erase_or_create_tile.php';

    exit();
}


$ui = new Ui($title="Tiled");


$view = new View($player->coords, $p=10, $tiled=true);

$data = $view->get_view();



echo '
<link rel="stylesheet" href="css/modal.css" />

<div style="float: left;">
<script src="js/tiled.js"></script>
<script src="js/modal.js"></script>

';

echo $data;

echo '
</div>
';

echo '<div stlye="position: absolute; top: 0; left: 0;"><a href="index.php"><button>Retour</button></a></div>
<br/>
<div id="ajax-data"></div>';

include 'tiled_tool/display_indestructibles.php';

include 'tiled_tool/display_foregrounds.php';

include 'tiled_tool/display_plants.php';

include 'tiled_tool/display_walls.php';

include 'tiled_tool/display_elements.php';

include 'tiled_tool/display_triggers.php';

include 'tiled_tool/display_tools.php';

include 'tiled_tool/display_mass_tools.php';

use App\View\ModalView;
$modalView = new ModalView();
$modalView->displayModal('tile-info','info-display');

?>


<style>
.custom-cursor {
    position: absolute;
    width: 50px;
    height: 50px;
    pointer-events: none;
    z-index: 1000;
}
</style>

<script>
$(document).ready(function(){


    $('img').each(function(e){

        $(this).attr('title', $(this).data('name'));
    });


    var $customCursor = $('<img>', {
        class: 'custom-cursor',
        src: $('.map').attr('src')
    }).appendTo('body').hide();


    selectPreviousTool($customCursor);


  $('.case').on('contextmenu', function(e) {
      e.preventDefault();

      var coords = $(this).data('coords');

      let [x, y] = coords.split(',');


      // show coords button
      $('#ajax-data').html('<div id="case-coords"><button OnClick="copyToClipboard(this);">x'+ x +',y'+ y +'</button><br>' +
          '<button OnClick="setZoneBeginCoords('+x+','+y+');" title="Debut de zone"><span class="ra ra-overhead"/></button>' +
          '<button OnClick="setZoneEndCoords('+x+','+y+');" title="Fin de zone"><span class="ra ra-underhand"/></button></div>');


    });

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

        }else if( $selected.hasClass('select-name') && $selected.data('name') === 'info'){
            retrieveCaseData($(this).data('coords'));
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
              document.location='tools.php?tiled&selectedTool='+$selected.data('name')+'&selectedParams='+params;
            }
        });
    });

    $('.map').click(function(e){
        if (!$(this).hasClass('selected')) {

          var $paramsField = $('#' + $(this).data('type') + '-params');

          if ($(this).data('params')) {

            let params = $(this).data('params');

            // if($paramsField.val() == ''){

                $paramsField.val(params);
            // }

            $paramsField.focus().select();
          }
          else{
            $paramsField.val('');
          }

          $('.map').removeClass('selected').css('border', '0px');
          $(this).addClass('selected').css('border', '1px solid red');


          // Position de l'image sur la page
          var offsetX = e.offsetX - 25; // 25 pour centrer l'image (50/2)
          var offsetY = e.offsetY - 25; // 25 pour centrer l'image (50/2)

          $customCursor.css({
              left: e.pageX + offsetX + 'px',
              top: e.pageY + offsetY + 'px'
          }).attr('src', $(this).attr('src')).show();

            $('body').on('mousemove', function(e) {
                $customCursor.css({
                    left: e.pageX - 25 +'px',
                    top: e.pageY - 25+'px'
                });
            });

        } else{
          $(this).removeClass('selected').css('border', '0px');
        }
    });


    $(document).on('click', function(e) {
        if (!$(e.target).hasClass('map') && $(e.target).attr('type') != 'text') {
            $customCursor.hide();
            $('body').off('mousemove');
            $('.map').removeClass('selected').css('border', '0px');
        }
    });

});
</script>



