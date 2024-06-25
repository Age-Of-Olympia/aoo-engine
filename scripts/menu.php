<?php

if(!empty($_SESSION['playerId'])){


    echo '<a href="index.php" title="Vue"><button>&nbsp;<span class="ra ra-chessboard"></span>&nbsp;</button></a><a href="upgrades.php"><button><span class="ra ra-podium"></span> Améliorations</button></a><a href="logs.php"><button><span class="ra ra-book"></span> Evènements</button></a><a href="inventory.php"><button><span class="ra ra-key"></span> Inventaire</button></a><a href="map.php" title="Carte"><button>&nbsp;<span class="ra ra-scroll-unfurled"></span>&nbsp;</button></a><a href="forum.php?forum=Missives" title="Missives"><button>&nbsp;<span class="ra ra-quill-ink"></span>&nbsp;</button></a><a href="account.php" title="Profil"><button>&nbsp;<span class="ra ra-wrench"></span>&nbsp;</button></a>';

    ?>
    <script>
    $(document).ready(function(){

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
}
else{


    echo '
    <input type="text" />
    <input type="password" />
    <input type="submit" value="Connexion" />
    ';

    ?>
    <script>
    $(document).ready(function(){

        $('input[type="submit"]').click(function(e){

            let player = $('input[type="text"]').val();

            open_console('session open '+ player);
        });
    });
    </script>
    <?php

    exit();
}
