<?php


if(isset($_GET['resetPsw'])){

    include('scripts/reset_psw.php');

    exit();
}

echo '<a href="index.php"><img src="img/ui/fillers/banner.png" data-src="img/ui/bg/banner.png" /></a>';

echo '
<div id="index-menu" class="box-shadow scrolling-bg">
    ';

    echo '<div class="text"><b>Age of Olympia,<br />JDR gratuit au tour-par-tour.</b></div>';

    echo '<a href="index.php" action="login" class="index-button">Jouer</a>';


    $raceBg = RACES[0];

    echo '
    <div id="index-login">
        <a href="index.php" action="retour" class="index-button">Retour</a>
        <form id="login" method="post" action="login.php">
            <table
                border="0"
                align="center"
                cellspacing="0"
            >
            <tr>
            <td>
            Matricule ou pseudo:
            </td>
            </tr>
            <tr>
            <td>
            <input name="name" type="text" style="text-align: center;" />
            </td>
            </tr>
            <tr>
            <td>
            Mot de Passe:
            </td>
            </tr>
            <tr>
            <td>
            <input name="psw" type="password" style="text-align: center;" />
            </td>
            </tr>
            <tr>
            <td>
            <font style="font-size: 70%"><a href="index.php?resetPsw">Mot de passe perdu?</a></font><br />
            </td>
            </tr>
            </table>

            <a href="index.php" action="submit" class="index-button">Login</a>
        </form>
    </div>
    ';

    ?>
    <script>
    $(document).ready(function(){


        $('[action="submit"]').click(function(e) {

            e.preventDefault(); // avoid to execute the actual submit of the form.

            var $form = $('#login');
            var actionUrl = $form.attr('action');
            var footprint = {
              screenResolution: screen.width + 'x' + screen.height,
              userAgent: navigator.userAgent,
              platform: navigator.platform,
              cookiesEnabled: navigator.cookieEnabled,
              language: navigator.language,
              javaEnabled: navigator.javaEnabled()
            };
            $('<input>').attr({
              type: 'hidden',
              name: 'footprint',
              value:  JSON.stringify(footprint)
            }).appendTo($form);

            $.ajax({
                type: "POST",
                url: actionUrl,
                data: $form.serialize(), // serializes the form's elements.
                success: function(data)
                {

                    if(data.trim() == ''){

                        document.location.reload();

                        return false;
                    }

                    alert(data); // show response from the php script.
                }
            });

        });
    });
    </script>
    <?php


    if(!isset($_SESSION['playerId'])){

        echo '<a href="register.php" class="index-button">Inscription</a>';
    }
    else{

        echo '<a href="index.php?logout" class="index-button">Déconnexion</a>';
    }

    echo '<a href="forum.php" class="index-button">Forum</a>';
    echo '<a href="https://age-of-olympia.net/wiki/doku.php?id=v4" class="index-button">Aide Wiki</a>';

    echo '
    <div class="text">
    '. file_get_contents('datas/public/classements/stats.html') .'
    </div>
    ';

    echo '
</div>
';





echo '
<div id="index-partenaires">

    <a href="https://ame-jdr.net"><img src="img/ui/partenaires/ame-jdr.net.webp" /></a><br />
    <br />
    <br />
    ';

    echo '<a href="https://aufonddutrou.fr/"><img src="img/ui/partenaires/afdt.gif" /></a>';
    echo '<a href="https://www.jdr.alandara.net/"><img src="img/ui/partenaires/alandara.gif" /></a>';
    echo '<a href="https://ideo-lejeu.com/"><img src="img/ui/partenaires/ideo.gif" /></a>';
    echo '<a href="https://www.mountyhall.com/"><img src="img/ui/partenaires/mountyhall.png" /></a>';
    echo '<a href="https://www.tourdejeu.net/annu/fichejeu.php?id=14616"><img src="img/ui/partenaires/tdj.gif" /></a>';

    echo '<div style="font-size: 75%; color: #333;"><a href="https://votezpourmoi.com/">Votez Pour Moi</a>, Jeu de simulation de campagne électorale! (<a href="https://votezpourmoi.com/jeu-politique/but-jeu.php">en savoir plus</a>)</div>';

    echo '
</div>
';


$annonceJson = json()->decode('', 'annonce');

if($annonceJson){

    echo '<div id="index-changelog"><a class="install-app" style="background: black; color: white;" href="https://age-of-olympia.net/wiki/doku.php?id=dev:changelog"><img src="img/ui/partenaires/code.gif" /> '. $annonceJson->text .' ('. date('d/m/Y', $annonceJson->time) .')</a></div>';
}

echo '<div id="index-discord"><a class="install-app" style="background: #5865f2; color: white;" href="https://discord.gg/peYNvzhDag"><img src="img/ui/partenaires/discord.webp" /> Discord </a></div>';


echo '<div class="preload"><img src="img/ui/bg/button2.png" /></div>';
echo '<div class="preload"><img src="img/ui/bg/button3.png" /></div>';

?>
<script src="js/progressive_loader.js"></script>
<script>

    <?php
    if(!empty($_GET['login']) && is_numeric($_GET['login'])):
    ?>

    $('.index-button').not('[action="retour"], [action="submit"]').hide();
    $('#index-login').fadeIn();
    $('[type="text"]').val(<?php echo $_GET['login'] ?>);
    $('[type="password"]').focus();

    <?php
    endif
    ?>

$('a[action="login"]').click(function(e){


    <?php if(!isset($_SESSION['playerId'])): ?>
    e.preventDefault();

    $('.index-button').not('[action="retour"], [action="submit"]').hide();

    $('#index-login').fadeIn();
    <?php endif ?>
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
