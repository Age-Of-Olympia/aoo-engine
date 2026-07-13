$(document).ready(function(e){

    $('.submit').click(function(e){

        var text = $('textarea').val();

        if(text.trim() == ''){

            alert('Le message ne doit pas être vide.');
            return false;
        }


        $(this).prop('disabled', true);


        var post = $(this).data('post');


        $.ajax({
            type: "POST",
            url: 'forum.php?edit='+ post,
            data: {
                'text': text
            }, // serializes the form's elements.
            success: function(data)
            {
                /* HUD : rouvrir le fil dans le panneau au lieu de
                 * naviguer vers la page héritée. */
                if(window.hudOpenPanel){

                    window.hudOpenPanel('load_forum.php?topic='+ window.topId +'&page='+ window.pagesN, 'Missives');
                    return;
                }

                document.location = 'forum.php?topic='+ window.topId +'&page='+ window.pagesN +'#'+ data.trim();
            }
        });
    });
});
