$(document).ready(function(e){

    $('.name').click(function(e){

        if($(this).val() == 'Titre du sujet'){

            $(this).val('');
        }
    });

    $('textarea').click(function(e){

        if($(this).val() == 'Message'){

            $(this).val('');
        }
    });

    $('.submit').click(function(e){


        $(this).prop('disabled', true);

        var name = $('.name').val();

        if(name.trim() == ''){

            alert('Votre titre doit contenir du texte.');

            $(this).prop('disabled', false);

            return false;
        }

        var text = $('textarea').val();

        if(text.trim() == ''){

            alert('Votre message doit contenir du texte.');

            $(this).prop('disabled', false);

            return false;
        }

        var forum = $(this).data('forum');


        var destId = 0;

        var $destField = $('#dest');

        if($destField != null){

            destId = $destField.val();
        }

        var currentSessionId = $('#currentSessionId').text();

        $.ajax({
            type: "POST",
            url: 'forum.php?newTopic='+ forum,
            data: {
                'text': text,
                'name': name,
                'destId': destId,
                'currentSessionId': currentSessionId
            }, // serializes the form's elements.
            success: function(data)
            {
                //alert(data);
                document.location = 'forum.php?topic='+ data.match(/\d+$/)[0];
            }
        });
    });
});
