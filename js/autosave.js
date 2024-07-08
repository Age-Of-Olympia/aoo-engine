$(document).ready(function(){


    var text = $('textarea').val();


    $('textarea').on('keyup', function(){


        $('.submit').html('Envoyer*');
    });


    var autosave = function(){


        var actualText = $('textarea').val();

        if(actualText != text){


            $('.submit').html('Sauvegarde en cours...');

            $.ajax({
                type: "POST",
                url: 'forum.php?autosave',
                data: {
                    'text': actualText
                }, // serializes the form's elements.
                success: function(data)
                {
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




function insert_img(url){

    // will give the current position of the cursor
    var curPos = $('textarea').selectionStart;

    // will get the value of the text area
    let x= $('textarea').val();

    var text = '[img]'+url+'[/img]';

    // setting the updated value in the text area
    $('textarea').val(x.slice(0,curPos)+text+x.slice(curPos));
}
