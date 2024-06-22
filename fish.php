<?php


require_once('config.php');


$ui = new Ui('Ça mord!');


$player = new Player($_SESSION['playerId']);


echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';


if($player->have_option('alreadyFished')){

    exit('Déplacez-vous pour pêcher à nouveau.');
}


$player->add_option('alreadyFished');


echo '<h1>Ça mord!</h1>';


$fishesTbl = array('noires/1.png','noires/2.png','rouges/1.png');


$stopTbl = [rand(0,2), rand(0,2), rand(0,2)];

$win = 0;

if($stopTbl[0] == $stopTbl[1] && $stopTbl[1] == $stopTbl[2]){

    $win = 1;

    $player->get_data();

    $text = $player->data->name .' a pêché un poisson!';

    Log::put($player, $player, $text, $type="fishing");
}

echo '
<table align="center">
    <tr>
    ';

    $n = 1;

    foreach($fishesTbl as $k=>$e){

        echo '
        <td>
        ';


        $style = ($n != 2) ? 'opacity: 0.3' : '';


        echo '
        <div>
            <img
                class="fish1"
                data-n="'. $k .'"
                data-fish="'. $n .'"
                src="img/ui/carpes/'. $e .'"
                width="150"
                style="'. $style .'"
                />
        </div>
        ';


        echo '
        </td>
        ';

        $n++;

    }

    echo '
    </tr>
    <tr>
    ';

    $n = 1;

    foreach($fishesTbl as $k=>$e){

        echo '
        <td>
        ';


        $style = ($n != 2) ? 'opacity: 0.3' : '';


        echo '
        <div>
            <img
                class="fish2"
                data-n="'. $k .'"
                data-fish="'. $n .'"
                src="img/ui/carpes/'. $e .'"
                width="150"
                style="'. $style .'"
                />
        </div>
        ';


        echo '
        </td>
        ';

        $n++;
    }

    echo '
    </tr>
    <tr>
    ';

    $n = 1;

    foreach($fishesTbl as $k=>$e){

        echo '
        <td>
        ';


        $style = ($n != 2) ? 'opacity: 0.3' : '';


        echo '
        <div>
            <img
                class="fish3"
                data-n="'. $k .'"
                data-fish="'. $n .'"
                src="img/ui/carpes/'. $e .'"
                width="150"
                style="'. $style .'"
                />
        </div>
        ';


        echo '
        </td>
        ';

        $n++;
    }

    echo '
    </tr>
</table>
';


?>
<script>
$(document).ready(function(){


    $('body').css('background','url(img/ui/bg/eau.png)');


    const winner = [<?php echo implode(',', $stopTbl) ?>];

    const fishesTbl = [<?php echo '"'. implode('","', $fishesTbl) .'"' ?>];

    var time = 0;


    var roll1 = function () {


        if(time >= 3){

            if($('.fish1[data-fish="2"]').data('n') == winner[0]){

                clearInterval(roll1);

                return false;
            }
        }


        $('.fish1').each(function(){


            let n = $(this).data('n');

            n++;

            if(n > 2){

                n = 0;
            }


            $(this).data('n', n);

            $(this).attr('src', 'img/ui/carpes/'+ fishesTbl[n]);
        });


        setTimeout(roll1, 100);
    }

    setTimeout(roll1, 1);


    var roll2 = function () {


        if(time >= 6){

            if($('.fish2[data-fish="2"]').data('n') == winner[1]){

                clearInterval(roll1);

                return false;
            }
        }


        $('.fish2').each(function(){


            let n = $(this).data('n');

            n--;

            if(n < 0){

                n = 2;
            }


            $(this).data('n', n);

            $(this).attr('src', 'img/ui/carpes/'+ fishesTbl[n]);
        });

        setTimeout(roll2, 150);

    }

    setTimeout(roll2, 1);


    var roll3 = function () {


        if(time >= 9){

            if($('.fish3[data-fish="2"]').data('n') == winner[2]){

                clearInterval(roll1);

                return false;
            }
        }


        $('.fish3').each(function(){


            let n = $(this).data('n');

            n--;

            if(n < 0){

                n = 2;
            }

            $(this).data('n', n);

            $(this).attr('src', 'img/ui/carpes/'+ fishesTbl[n]);
        });

        setTimeout(roll3, 300);

    }

    setTimeout(roll3, 1);



    var chrono = function () {


        time ++;


        if(time == 10){


            if(<?php echo $win ?>){

                $('*[data-fish="2"]').addClass('glow');
            }

            clearInterval(chrono);

            return false;
        }


        setTimeout(chrono, 1000);

    }

    setTimeout(chrono, 1);

});
</script>
