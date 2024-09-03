$(document).ready(function(){

    $('#go-rect').hide();
    $('#go-img').hide();


    window.visible = false;


    $('.action').click(function(e){


        if($(this).find('.action-name').html() != 'Fermer'){


            if(!window.visible){


                $('.action').find('.action-name').show();

                window.visible = true;

                return false;
            }


            if(!confirm($(this).find('.action-name').html() +'?')){

                return false;
            }
        }


        $('.action').prop('disabled', true);
        $('#action-data').hide().html();

        let url = 'action.php';

        if($(this).data('url')){

            url = $(this).data('url');
        }

        let targetId = $(this).data('target-id');
        let action = $(this).data('action');

        if(action == 'close-card'){

            $('#ui-card').hide();
            return false;
        }


        $('.card-text').html('<div class="action-details"><i><span class="ra ra-perspective-dice-random"></span> Lancé de dés...</i></div>');


        $.ajax({
            type: "POST",
            url: url,
            data: {'action':action, 'targetId':targetId}, // serializes the form's elements.
            success: function(data)
            {
                // alert(data);
                let $action = $('<div>'+ data +'</div>').hide();
                $('.card-text').html('').addClass('action-text').append($action.fadeIn());
                $('.action').prop('disabled', false);
            }
        });
    })
    .on('mouseover', function(e){

        // $(this).find('.action-name').show();
    })
    .on('mouseout', function(e){

        // $(this).find('.action-name').hide();
    });
});
