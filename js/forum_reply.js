$(document).ready(function(e){

    $('.submit').click(function(e){

        var text = $('textarea').val();


        if(text.trim() == ''){

            alert('Le message ne doit pas être vide.');
            return false;
        }


        $(this).prop('disabled', true);


        var topic = $(this).data('topic');
        var currentSessionId = $('#currentSessionId').text();

        $.ajax({
            type: "POST",
            url: 'forum.php?reply='+ topic,
            data: {
                'text': text,
                'currentSessionId': currentSessionId
            }, // serializes the form's elements.
            success: function(data)
            {
                try {
                    let response = JSON.parse(data);
                    if(response.error){
                        alert(response.error);
                    }
                    else{
                        document.location = 'forum.php?topic='+ topic +'&page='+ window.pagesN +'#'+ response.result;
                    }
                } catch (error) {
                    alert(data);
                }
                // alert(data);
                
            }
        });
    });
});
