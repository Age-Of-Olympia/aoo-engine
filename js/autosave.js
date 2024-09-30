$(document).ready(function(){


    var text = $('textarea').val();


    $('textarea').on('keyup', function(){


        $('.submit').html('Envoyer*');
    });


    var autosave = function(){


        var actualText = $('textarea').val();
        var currentSessionId = $('#currentSessionId').text();
        if(actualText != text){


            $('.submit').html('Sauvegarde en cours...');

            $.ajax({
                type: "POST",
                url: 'forum.php?autosave',
                data: {
                    'text': actualText,
                    'currentSessionId': currentSessionId
                }, // serializes the form's elements.
                success: function(data)
                {
                    if(data.trim() != ''){
                        alert(data);
                    }
                    // alert(data);
                    $('.submit').html('Envoyer');

                    text = $('textarea').val();
                }
            });
        }


        setTimeout(autosave, 10000);
    }


    setTimeout(autosave, 1);


    $('#delete').click(function(e){

        if(confirm('Êtes-vous certain de vouloir effacer le texte?')){

            $('textarea').val('').focus();
        }
    });

    $('#add-rows').click(function(e){

        $('textarea').css('height','+=200px');

    });

    $('#upload').click( function(e) {
        $('#drop_file_zone').show();
    });


});




function insert_img(url) {


    // Préparer le texte à insérer
    var imgText = '[img]' + url + '[/img]';

    insert_textarea(imgText);
}

function insert_textarea(addText){

    // Sélectionner l'élément textarea
    var $textarea = $('textarea');

    // Obtenir la position actuelle du curseur
    var curPos = $textarea.prop('selectionStart');

    // Obtenir la valeur actuelle de la zone de texte
    var text = $textarea.val();

    // Insérer le texte à la position du curseur
    var newText = text.slice(0, curPos) + addText + text.slice(curPos);

    // Mettre à jour la valeur de la zone de texte
    $textarea.val(newText);

    // Ajuster la position du curseur après l'insertion du texte
    $textarea.prop('selectionStart', curPos + addText.length);
    $textarea.prop('selectionEnd', curPos + addText.length);

    // Mettre le focus sur la zone de texte
    $textarea.focus();
}
