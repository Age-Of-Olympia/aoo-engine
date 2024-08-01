<?php

if(!empty($_SESSION['playerId'])){


    ob_start();


    echo '<a href="index.php" title="Vue"><button>&nbsp;<span class="ra ra-chessboard"></span>&nbsp;</button></a><a href="upgrades.php"><button><span class="ra ra-podium"></span> Améliorations</button></a><a href="logs.php"><button><span class="ra ra-book"></span> Evènements</button></a><a href="inventory.php"><button><span class="ra ra-key"></span> Inventaire</button></a><a href="map.php" title="Carte"><button>&nbsp;<span class="ra ra-scroll-unfurled"></span>&nbsp;</button></a><a href="forum.php?forum=Missives" title="Missives"><button>&nbsp;<span class="ra ra-quill-ink"></span>&nbsp;</button></a><a href="#" id="show-caracs" title="Caractéristiques"><button>&nbsp;<span class="ra ra-muscle-up"></span>&nbsp;</button></a><a href="account.php" title="Profil"><button>&nbsp;<span class="ra ra-wrench"></span>&nbsp;</button></a>';


    $caracsJson = $player->get_caracsJson();


    echo '
    <table border="1" align="center" class="marbre" id="caracs-menu">
        ';

        echo '
        <tr>
            ';

            foreach(CARACS as $k=>$e){


                echo '<th width="30">'. $e .'</th>';
            }

            echo '
        </tr>
        ';

        echo '
        <tr>
            ';

            foreach(CARACS as $k=>$e){


                echo '<td>'. $caracsJson->$k .'</td>';
            }

            echo '
        </tr>
        ';


        $pct = Str::calculate_xp_percentage($player->data->xp, $player->data->rank);


        echo '<tr>';

            echo '<td colspan="'. count(CARACS) .'">
            <div class="progress-bar">
                <div class="bar" style="width: '. $pct .'%;">&nbsp;</div>
                <div class="text">Xp: '. $player->data->xp .'/'. Str::get_next_xp($player->data->rank) .'</div>
            </div>
            </td>';

        echo '</tr>';

        echo '<tr>';

            if($player->data->malus){

                echo '<td colspan="'. count(CARACS) .'">Malus: -'. $player->data->malus .' aux jets de défense.</td>';
            }

        echo '</tr>';

        echo '<tr>';

            if($player->data->fatigue >= FAT_EVERY){


                $fatMalus = floor($player->data->fatigue / FAT_EVERY);

                echo '<td colspan="'. count(CARACS) .'">Fatigue ('. $player->data->fatigue .'): -'. $fatMalus .' à tous les jets.</td>';
            }

        echo '</tr>';


        echo '
    </table>
    ';


    ?>
    <script>
    $(document).ready(function(){


        $('#show-caracs').click(function(e){


            e.preventDefault();

            $('#caracs-menu-landing').hide();

            if($('#caracs-menu').is(':hidden')){
                $('#caracs-menu').fadeIn();
            }
            else{
                $('#caracs-menu').hide();
            }
        });


        $('.menu-link').click(function(e){


            e.preventDefault();

            let url = $(this).attr('href');

            $.ajax({
                type: "POST",
                url: url,
                success: function(data)
                {
                    $('#ajax-data').html(data);
                }
            });
        });
    });
    </script>
    <?php

    echo Str::minify(ob_get_clean());
}
