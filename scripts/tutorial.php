<?php

@unlink('datas/private/players/'. $player->id .'.msg.html');

$player->get_coords();

echo '
<style>
#tooltip{

    position:absolute;
    background-color:black;
    color: white;
    border-radius: 15px;
    padding:10px;
    font-size: 88%;
    max-width: 200px;
    text-align: left;
    display: none;
}

    #tooltip::before {

        content: "▲";
        position: absolute;
        top: -1em;
        left: 15px;
        font-size: 15px;
        color: black;
    }


    #tooltip a{

        color: cyan;
    }

    #tooltip .tooltip-next{

        text-align: right;
    }
</style>
<div id="tooltip"><div class="text">tooltip</div><div class="tooltip-next"><a href="#" class="next">[suite]</a></div></div>
';

?>
<script>

function append_tuto($element, text){


    var elementPosition = $($element).offset();

    var width = $element.width();
    var height = $element.height();


    if(window.cursor == 1){

        $($element).click();
        height += 50;
    }


    $('#tooltip')
    .hide()
    .fadeIn()
    .css({
        top: elementPosition.top + height + 5,
        left: elementPosition.left - 10,
        display: 'block'
    });

    $('#tooltip .text')
    .html(text);
}


$(document).ready(function(){

    $msg = $('#view-landing-msg');

    if($msg[0]){

        $msg.fadeOut();
    }

    var list = [
        $('#players<?php echo $_SESSION['playerId'] ?>'),
        $('.go[data-coords="<?php echo $player->coords->x+1 ?>,<?php echo $player->coords->y ?>"]'),
        $('.ra-chessboard'),
        $('.ra-key'),
        $('[href="pnjs.php"]'),
        $('.ra-quill-ink')
    ];

    var text = [
        "Au centre du Damier, c'est vous!",
        "Cliquez sur une case adjacente pour vous y déplacer",
        "Cliquez-ici à tout moment pour revenir au Damier.",
        "Vos objets sont entreposés ici. Parlez à Gaïa pour terminer le tuto et gagner 20Po ainsi qu'un Bâton de Marche.",
        "Par ailleurs, vous avez un nouveau message!",
        "Vous pouvez également cliquer ici pour lire cette importante Missive."
    ];

    window.cursor = 0;

    append_tuto(list[window.cursor], text[window.cursor]);


    $('.next').click(function(e){

        e.preventDefault();

        window.cursor++;

        if(window.cursor >= list.length){

            alert('Le tutoriel est terminé!\nN\'hésitez pas à demander de l\'aide à votre Animateur ou sur le Forum (catégorie Aide et Suggestions) ou encore sur le Discord (salon Entraide).\nBon jeu!');

            document.location = 'index.php';
        }

        append_tuto(list[window.cursor], text[window.cursor]);
    });
});
</script>
