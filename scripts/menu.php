<?php

if(!empty($_SESSION['playerId'])){


    echo '<a href="upgrades.php"><button>Améliorations</button></a><a href="logs.php"><button>Evènements</button></a><a class="menu-link" href="inventory.php"><button>Inventaire</button></a><a href="map.php"><button>Carte</button></a><a href="account.php"><button>Profil</button></a>';

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
