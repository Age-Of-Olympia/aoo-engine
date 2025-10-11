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

    $(document).on('click', 'img.give-cookie:not(.disable)', function() {
        var $this = $(this);
    
        if( !$this.hasClass("disable")) {
            let url= 'api/forum/cookie.php';
            let payload = {
            'post-name': $this.data('post-name'),
            'player-id': $this.data('player-id')
            };
            aooFetch(url,payload,null)
            .then(data=>{
                $this.addClass("disable");
                $this.siblings("span").attr('tooltip', data.message);
                $this.siblings("span").text(parseInt($this.siblings("span").text())+1);
            }
            )
            .catch(autoError());      
        }
    });
});
