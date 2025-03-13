

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
        $('#players'+ window.playerId),
        $('.go[data-coords="'+ window.dataCoords +'"]'),
        $('.ra-chessboard'),
        $('.ra-key'),
        $('#missive-btn')
       
    ];

    var text = [
        "Au centre du Damier, c'est vous!",
        "Cliquez sur une case adjacente pour vous y déplacer",
        "Cliquez-ici à tout moment pour revenir au Damier.",
        "Vos objets sont entreposés dans votre Inventaire. Parlez à Gaïa pour terminer le tuto et gagner 20Po ainsi qu'un Bâton de Marche.",
        "Par ailleurs, vous avez un nouveau message! Cliquer ici pour lire cette importante Missive."
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
