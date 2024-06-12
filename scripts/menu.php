<?php

if(!empty($_SESSION['playerId'])){


    echo '<a href="upgrades.php"><button><span class="ra ra-podium"></span> Améliorations</button></a><a href="logs.php"><button><span class="ra ra-book"></span> Evènements</button></a><a href="inventory.php"><button><span class="ra ra-key"></span> Inventaire</button></a><a href="map.php"><button><span class="ra ra-scroll-unfurled"></span> Carte</button></a><a href="account.php"><button><span class="ra ra-wrench"></span> Profil</button></a>';

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
}
