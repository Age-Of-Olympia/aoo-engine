$(document).ready(function(){

    $('.upgrade').click(function(e){

        $('.upgrade').prop('disabled', true);

        let carac = $(this).data('carac');


        if(!confirm('Augmenter '+ $(this).data('carac-name') +'?')){


            $('.upgrade').prop('disabled', false);
            return false;
        }

        $.ajax({
            type: "POST",
            url: 'upgrades.php',
            data: {'carac':carac}, // serializes the form's elements.
            success: function(data)
            {
                // alert(data);

                document.location.reload();
            }
        });
    });
});
