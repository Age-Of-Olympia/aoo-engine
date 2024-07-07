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
});
