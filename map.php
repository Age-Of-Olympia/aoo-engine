<?php

require_once('config.php');

$ui = new Ui('Carte du Monde');

$player = new Player($_SESSION['playerId']);

$player->get_coords();

$planJson = json()->decode('plans', $player->coords->plan);


// hors map
if(!$planJson){

    echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div><br />';


    $url = 'img/ui/illustrations/'. $player->coords->plan .'.png';

    if(!file_exists($url)){

        $url = 'img/ui/illustrations/gaia.jpeg';
    }


    echo '<img class="box-shadow" src="'. $url .'" />';

    exit();
}


if(isset($_GET['local'])){

    include('scripts/map/local.php');
    exit();
}

?>
<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a><a href="map.php"><button>Monde</button></a><a href="map.php?local"><button><?php echo $planJson->name ?></button></a></div>


<?php echo Ui::print_map($player, $planJson) ?>


<script>
$(document).ready(function(){


    if(<?php echo (isset($_GET['allMap'])) ? 'true' : 'false' ?>){

        $('.map').css('opacity', 1).data('opacity', 1).show().off('mouseover');
        $('.text').delete();
    }

    $('.map[data-plan="<?php echo $player->coords->plan ?>"]').css('opacity', 1).data('opacity', 1);
    $('.text[data-plan="<?php echo $player->coords->plan ?>"]').show();

    $('[data-plan="<?php echo $player->coords->plan ?>"]').click(function(e){

        document.location = 'map.php?local';
    });


    <?php include('scripts/map/travel.php') ?>


    $('.map')
    <?php

    if(!empty($triggerId)){

        ?>
        .on('click', function(e){

            if($(this).hasClass('blink')){


                // war
                if($(this).hasClass('colored-red')){


                    alert('Ce territoire est en guerre: impossible de s\'y rendre.');

                    return false;
                }


                if(confirm('Voyager jusqu\'à '+ $(this).data('name') +'?')){

                    $.ajax({
                        type: "POST",
                        url: 'map.php?triggerId=<?php echo $triggerId ?>',
                        data: {'goPlan':$(this).data('plan')}, // serializes the form's elements.
                        success: function(data)
                        {
                            // alert(data);
                            document.location = "index.php";
                        }
                    });
                }
            }
        })
        <?php
    }
    ?>
    .on('mouseover', function(e){

        window.old_opacity = $(this).data('opacity');

        $(this).css('opacity','1');
        $('.text[data-plan="'+ $(this).data('plan') +'"]').show();
    })
    .on('mouseout', function(e){

        $(this).css('opacity',window.old_opacity);

        if(window.old_opacity != 1){

            $('.text[data-plan="'+ $(this).data('plan') +'"]').hide();
        }
    });
});
</script>
