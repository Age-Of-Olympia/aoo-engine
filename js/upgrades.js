$(document).ready(function(){

    $('.upgrade').click(function(e){

        $('.upgrade').prop('disabled', true);

        let carac = $(this).data('carac');


        aooConfirm('Augmenter '+ $(this).data('carac-name') +'?').then(function(ok){

            if(!ok){

                $('.upgrade').prop('disabled', false);
                return;
            }

            $.ajax({
                type: "POST",
                url: 'upgrades.php',
                data: {'carac':carac}, // serializes the form's elements.
                success: function(data)
                {
                    /* HUD panel or page: reloads the view in place (main.js) */
                    aooReload();
                },
                error: function() {
                    aooAlert("Erreur lors de l'amélioration.");
                    $('.upgrade').prop('disabled', false);
                }
            });
        });
    });
});
