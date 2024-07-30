$(document).ready(function(e){

    $('.reply').click(function(e){


        document.location = 'forum.php?reply='+ $(this).data('topic');
    });


    $('.post-rewards img:not(.give-reward)').click(function(e){


        $('.post-rewards span:not(.give-reward-span)').html('');

        $(this).next('span').html($(this).attr('title'));
    });

    $('.give-reward').click(function(e){


        var $this = $(this);


        $.ajax({
            type: "POST",
            url: 'forum.php?rewards',
            data: {
                'post': $this.data('post')
            }, // serializes the form's elements.
            success: function(data)
            {
                htmlContent = $('<div>').html(data).find('#data').html();
                // alert(htmlContent);
                $this.next('span').html(htmlContent);
            }
        });
    });
});
