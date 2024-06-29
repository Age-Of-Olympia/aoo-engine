<?php

// echo '
// <style>
// #background-video {
//   height: 100%;
//   width: 100%;
//   object-fit: cover;
//   position: fixed;
//   left: 0;
//   right: 0;
//   top: 0;
//   bottom: 0;
//   z-index: -1;
//   opacity: 0.1;
// }
// </style>
// <video id="background-video" autoplay muted loop>
//   <source src="img/ui/bg/vid.mp4" type="video/mp4">
// </video>
// ';

echo '<a href="index.php"><img src="img/ui/bg/banner.png" /></a>';
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
