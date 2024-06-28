<?php


define('NO_LOGIN', true);


require_once('config.php');


$ui = new Ui($title="Index");


if(!isset($_SESSION['playerId']) || isset($_GET['menu'])){


    echo '<a href="index.php"><img src="img/ui/bg/banner.png" height="250" /></a>';
    echo "<div>Aoo, JDR gratuit au tour-par-tour.</div>";
    echo '
    <div id="index-menu">
        ';

        echo '<a href="#" action="login" class="index-button">Jouer</a>';


        echo '
        <table
            id="index-login"
            style="display: none;" border="0"
            align="center"
            class="marbre box-shadow"
            cellspacing="0"
        >
        <tr>
        <td>
        Matricule ou pseudo
        </td>
        </tr>
        <tr>
        <td>
        <input type="text" style="text-align: center;" />
        </td>
        </tr>
        <tr>
        <td>
        Mot de Passe
        </td>
        </tr>
        <tr>
        <td>
        <input type="password" style="text-align: center;" />
        </td>
        </tr>
        <tr>
        <td>
        <input type="submit" value="Connexion" />
        </td>
        </tr>
        </table>
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


        echo '<a href="#" action="register" class="index-button">Inscription</a>';
        echo '<a href="forum.php" class="index-button">Forum</a>';
        echo '<a href="https://age-of-olympia.net/wiki/doku.php?id=v4" class="index-button">Wiki v4</a>';

        echo '
    </div>
    ';


    echo '<div class="preload"><img src="img/ui/bg/button2.png" /></div>';
    echo '<div class="preload"><img src="img/ui/bg/button3.png" /></div>';

    ?>
    <script>
    $('a[action="login"]').click(function(e){

        e.preventDefault();

        $('#index-login').fadeIn();
    });

    $('a[action="register"]').click(function(e){

        e.preventDefault();

        let player = prompt('Nom du personnage (sans espace)');

        if(!player) return false;

        let race = prompt('Race du personnage\n(nain/geant/hs/olympien/elfe/lutin/redoraan/dieu)');

        if(!race) return false;

        open_console('create player '+ player +' '+ race);
    });
    </script>
    <?php

    exit();
}


?>


<div id="infos"><?php include('scripts/infos.php') ?></div>

<div id="menu"><?php include('scripts/menu.php') ?></div>

<div id="view"><?php include('scripts/view.php') ?></div>


<?php

echo '<div style="color: red;">';

if(!CACHED_INVENT) echo 'CACHED_INVENT = false<br />';
if(AUTO_GROW) echo 'AUTO_GROW = true<br />';
if(FISHING) echo 'AUTO_GROW = true<br />';

echo '</div>';
