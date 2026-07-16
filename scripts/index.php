<?php

use App\View\ResetPasswordView;

if(isset($_GET['resetPsw'])){
    ResetPasswordView::renderResetPassword();
    exit();
}

/* Habillage papier & encre de la maquette graphiste : la bannière
 * cède la place au héros planète + titre Gloock (css/landing.css). */
echo '<link rel="stylesheet" href="css/landing.css?v=20260711m" />';

/* Fond filigrané « aootest » hors prod (variante _test du fond
 * composité de la maquette). */
$paperBg = function_exists('aoo_paper_background') ? aoo_paper_background() : '/img/ui/paper/paper.jpg';
if ($paperBg !== '/img/ui/paper/paper.jpg') {
    echo '<style>body{background-image:url(\'/img/ui/paper/landing-bg_test.jpg?v=20260711\')}</style>';
}

/* Premier écran : héros + carte de menu, avec le premier logo
 * partenaire épinglé en bas (css/landing.css #landing-fold) — il
 * dépasse du pli, le repère « Partenaires » juste dessous, pour
 * montrer que la page continue. */
echo '<div id="landing-fold">';

echo '<a href="index.php" id="landing-hero">'
    . '<img src="img/ui/paper/planet.png" alt="" />'
    . '<h1>Age of Olympia</h1>'
    . '</a>';

/* Devise sous la planète (composition de la maquette) — plus de
 * doublon « Age of Olympia » dans la carte de menu. */
echo '<p id="landing-tagline">JDR gratuit au tour-par-tour.</p>';

echo '
<div id="index-menu" class="box-shadow scrolling-bg">
    ';

    echo '<div class="text"><b>Age of Olympia,<br />JDR gratuit au tour-par-tour.</b></div>';

    echo '<a href="index.php" action="login" id="index-button-play" class="index-button">Jouer</a>';


    $raceBg = (new \App\Service\RaceService())->getPlayableRaceNames()[0] ?? 'nain';

    echo '
    <div id="index-login">
        <a href="index.php" action="retour" id="index-button-return" class="index-button">Retour</a>
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
            <input name="name" type="text" id="name-input" style="text-align: center;" />
            </td>
            </tr>
            <tr>
            <td>
            Mot de Passe:
            </td>
            </tr>
            <tr>
            <td>
            <input name="psw" type="password" id="psw-input" style="text-align: center;" />
            </td>
            </tr>
            <tr>
            <td>
            <font style="font-size: 70%"><a href="index.php?resetPsw">Mot de passe perdu?</a></font><br />
            </td>
            </tr>
            </table>

            <button type="submit" action="submit" id="index-button-login" class="index-button">Login</button>
        </form>
    </div>
    ';

    ?>
    <script>
    $(document).ready(function(){


        $('#login').submit(function(e){

            e.preventDefault();

            $('[action="submit"]').click();
        });

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

        echo '<a href="register.php" id="index-button-register" class="index-button">Inscription</a>';
    }
    else{

        echo '<a href="index.php?logout" class="index-button">Déconnexion</a>';
    }

    echo '<div class="menu-sep"></div>';
    echo '<a href="forum.php" class="index-button">Forum</a>';
    echo '<div class="menu-sep"></div>';
    echo '<a href="https://age-of-olympia.net/wiki/" class="index-button">Aide Wiki</a>';
    echo '<div class="menu-sep"></div>';
    echo '<a href="https://discord.gg/djPRYwEt8E" target="_blank" rel="noopener" class="index-button">Discord</a>';

    echo '
    <div class="text">
    '. file_get_contents('datas/public/classements/stats.html') .'
    </div>
    ';

    echo '
</div>
';





/* Bas du premier écran : le premier logo partenaire amorce la suite
 * de la page, le repère « Partenaires » juste dessous — dans le flux,
 * il défile avec le contenu, aucun recouvrement possible. */
echo '
<div id="index-partenaires">
    <a href="https://ame-jdr.net"><img src="img/ui/partenaires/ame-jdr.net.webp" /></a>
</div>
';

echo '<a id="landing-scroll-hint" href="#index-partenaires">Partenaires</a>';

echo '</div>'; /* /#landing-fold */


echo '<a href="https://aufonddutrou.fr/" title="Au fond du trou"><img src="img/ui/partenaires/afdt.gif" /></a>';
echo '<a href="https://www.jdr.alandara.net/" title="Alandara"><img src="img/ui/partenaires/alandara.gif" /></a>';
echo '<a href="https://ideo-lejeu.com/" title="IDEO"><img src="img/ui/partenaires/ideo.gif" /></a>';
echo '<a href="https://www.mountyhall.com/" title="Mounty Hall"><img src="img/ui/partenaires/mountyhall.png" /></a>';
echo '<a href="https://www.tourdejeu.net/annu/fichejeu.php?id=14616" title="Tour de jeu"><img src="img/ui/partenaires/tdj.gif" /></a>';

echo '<br />';

echo '<a href="https://www.les12singes.com/84-les-oublies"><img src="img/ui/partenaires/les_oublies.jpeg" /></a><br />';

echo '<div style="font-size: 75%; color: #333;"><a href="https://votezpourmoi.com/">Votez Pour Moi</a>, Jeu de simulation de campagne électorale! (<a href="https://votezpourmoi.com/jeu-politique/but-jeu.php">en savoir plus</a>)</div>';

echo '<a href="https://www.qtg.fr/" title="Qu\'est-ce que tu Geekes ?"><img src="img/ui/partenaires/qtg.gif" /></a>';


$annonceJson = json()->decode('', 'annonce');

if($annonceJson){

    // Définir la locale en français
    $jour= DAYS_OF_WEEK[getdate($annonceJson->time)["wday"]];
    /* Chip papier (css/landing.css) — l'ancien style inline noir gagnait la cascade. */
    echo '<div id="index-changelog"><a class="install-app" href="https://age-of-olympia.net/wiki/doku.php?id=dev:changelog"><img src="img/ui/partenaires/code.gif" /> '. $annonceJson->text .' ('. $jour .' '. date('d/m/Y', $annonceJson->time) .')</a></div>';
}

/* Le Discord du jeu vit désormais dans la carte de menu (après le
 * wiki) — plus de chip flottante en coin d'écran. */


echo '<div class="preload"><img src="img/ui/bg/button2.png" /></div>';
echo '<div class="preload"><img src="img/ui/bg/button3.png" /></div>';

?>
<script src="js/progressive_loader.js?v=20260716"></script>
<script>

    <?php
    if(!empty($_GET['login']) && is_numeric($_GET['login'])):
    ?>

    $('.index-button, .menu-sep').not('[action="retour"], [action="submit"]').hide();
    $('#index-login').fadeIn();
    $('[type="text"]').val(<?php echo $_GET['login'] ?>);
    $('[type="password"]').focus();

    <?php
    endif
    ?>

$('a[action="login"]').click(function(e){


    <?php if(!isset($_SESSION['playerId'])): ?>
    e.preventDefault();

    $('.index-button, .menu-sep').not('[action="retour"], [action="submit"]').hide();

    $('#index-login').fadeIn();
    <?php endif ?>
});

$('a[action="register"]').click(function(e){

    e.preventDefault();

    aooPrompt('Nom du personnage (sans espace)').then(function(player){

        if(!player) return;

        aooPrompt('Race du personnage\n(nain/geant/hs/olympien/elfe/lutin/redoraan/dieu)').then(function(race){

            if(!race) return;

            open_console('create player '+ player +' '+ race);
        });
    });
});
</script>
<?php

exit();
