<?php

if(!empty($_SESSION['playerId'])){


    ob_start();


    echo '<a href="index.php" title="Vue"><button>&nbsp;<span class="ra ra-chessboard"></span>&nbsp;</button></a><a href="#" id="show-caracs" title="Caractéristiques"><button><span class="ra ra-muscle-up"></span>&nbsp;Caractéristiques</button></a><a href="inventory.php"><button><span class="ra ra-key"></span> Inventaire</button></a><!--a href="upgrades.php"><button><span class="ra ra-podium"></span> Améliorations</button></a--><a href="logs.php"><button><span class="ra ra-book"></span> Evènements</button></a><a href="map.php" title="Carte"><button>&nbsp;<span class="ra ra-scroll-unfurled"></span>&nbsp;</button></a><a href="forum.php?forum=Missives" title="Missives"><button>&nbsp;<span class="ra ra-quill-ink"></span>&nbsp;</button></a><a href="account.php" title="Profil"><button>&nbsp;<span class="ra ra-wrench"></span>&nbsp;</button></a>';


    echo '<div id="load-caracs"></div>';


    ?>
    <script>
    $(document).ready(function(){


        $('#show-caracs').click(function(e){


            e.preventDefault();

            if($('#load-caracs').is(':hidden')){

                $.ajax({
                    type: "POST",
                    url: 'load_caracs.php',
                    success: function(data)
                    {
                        $('#load-caracs').html(data).fadeIn();
                    }
                });
            }
            else{
                $('#load-caracs').hide();
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
