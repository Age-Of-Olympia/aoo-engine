<?php

if(!empty($_SESSION['playerId'])){


    $svgUrl = 'datas/private/players/'. $_SESSION['playerId'] .'.svg';


    if(!file_exists($svgUrl)){

        // coords
        $db = new Db();

        $player = new Player($_SESSION['playerId']);

        $coords = $player->get_coords();

        $player->get_caracs();


        $p = $player->caracs->p;

        $view = new View($coords, $p);

        $data = $view->get_view();

        $myfile = fopen($svgUrl, "w") or die("Unable to open file!");
        fwrite($myfile, $data);
        fclose($myfile);

        echo $data;

        echo '<sup>La vue a été rafraîchie!</sup>';
    }

    else{

        echo file_get_contents($svgUrl);
    }

    echo '<div id="ajax-data"></div>';


    ?>
    <script>
    $(document).ready(function(){

        $('.case').click(function(e){

            var coords = $(this).data('coords');

            $.ajax({
                type: "POST",
                url: 'observe.php',
                data: {'coords':coords}, // serializes the form's elements.
                success: function(data)
                {
                    // alert(data);

                    $('#ajax-data').html(data);
                }
            });
        });


        $('#go-rect').click(function(e){

            var coords = $(this).data('coords');

            $.ajax({
                type: "POST",
                url: 'go.php',
                data: {'coords':coords}, // serializes the form's elements.
                success: function(data)
                {
                    // alert(data);

                    if(data.trim() != ''){


                        $('#ajax-data').html(data);

                        return false;
                    }

                    document.location.reload();
                }
            });
        });
    });
    </script>
    <?php
}
else{


    echo "Aoo, JDR gratuit au tour-par-tour.";
}
