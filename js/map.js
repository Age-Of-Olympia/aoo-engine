$(document).ready(function(){


    if(window.allMap){

        $('.map').css('opacity', 1).data('opacity', 1).show().off('mouseover');
        $('.text').delete();
    }

    $('.map[data-plan="'+ window.coordsPlan +'"]').css('opacity', 1).data('opacity', 1);
    $('.text[data-plan="'+ window.coordsPlan +'"]').show();

    $('[data-plan="'+ window.coordsPlan +'"]').click(function(e){

        document.location = 'map.php?local';
    });


    $('.map')
    .on('click', function(e){


        if(!window.triggerId){

            return false;
        }


        if($(this).hasClass('blink')){


            // war
            if($(this).hasClass('colored-red')){


                alert('Ce territoire est en guerre: impossible de s\'y rendre.');

                return false;
            }


            if(confirm('Voyager jusqu\'à '+ $(this).data('name') +'?')){

                $.ajax({
                    type: "POST",
                    url: 'map.php?triggerId='+ window.triggerId,
                    data: {'goPlan':$(this).data('plan')}, // serializes the form's elements.
                    success: function(data)
                    {
                        // alert(data);
                        document.location = "index.php";
                    }
                });
            }
        }
    })
    .on('mouseover', function(e){

        window.old_opacity = $(this).data('opacity');

        $(this).css('opacity','1');
        $('.text[data-plan="'+ $(this).data('plan') +'"]').show();
    })
    .on('mouseout', function(e){

        $(this).css('opacity',window.old_opacity);

        if(window.old_opacity != 1){

            $('.text[data-plan="'+ $(this).data('plan') +'"]').hide();
        }
    });
});
