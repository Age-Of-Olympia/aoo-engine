$(document).ready(function(e){

    $('.submit').click(function(e){

        var text = $('textarea').val();


        if(text.trim() == ''){

            alert('Le message ne doit pas être vide.');
            return false;
        }


        $(this).prop('disabled', true);


        var topic = $(this).data('topic');


        $.ajax({
            type: "POST",
            url: 'forum.php?reply='+ topic,
            data: {
                'text': text
            }, // serializes the form's elements.
            success: function(data)
            {
                // alert(data);
                document.location = 'forum.php?topic='+ topic +'&page='+ window.pagesN +'#'+ data.trim();
            }
        });
    });
});
