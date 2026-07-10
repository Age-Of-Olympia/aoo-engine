$(document).ready(function(){

    window.visible = false;


    $('.action').click(function(e){


        if($(this).find('.action-name').html() != 'Fermer'){


            if(!window.visible){


                $('.action').css('width', '110px').find('.action-name').show();

                window.visible = true;

                return false;
            }


            // if(!confirm($(this).find('.action-name').html() +'?')){
            //
            //     return false;
            // }
        }


        /* Boutons de NAVIGATION (Marchander, Apprendre…) : un lien
         * autour et pas de data-action — rien à POSTer vers
         * action.php ; le lien (ou le routeur de panneaux du HUD)
         * s'en charge. */
        if(!$(this).data('action') && $(this).closest('a[href]').length){

            return;
        }


        $('.action').prop('disabled', true);
        $('#action-data').hide().html();

        let url = 'action.php';

        if($(this).data('url')){

            url = $(this).data('url');
        }

        let targetId = $(this).data('target-id');
        let action = $(this).data('action');
        let coordsX = $(this).data('coords-x');
        let coordsY = $(this).data('coords-y');
        let coordsZ = $(this).data('coords-z');
        let coordsPlan = $(this).data('coords-plan');

        if(action == 'close-card'){

            $('#ui-card').hide();
            return false;
        }


        /* HUD : le résultat arrive dans une modale par-dessus le damier
         * (window.hudShowActionResult, js/hud.js) — la fiche de la
         * cible reste intacte. Habillage hérité : comportement
         * d'origine, le résultat remplace le texte de la carte. */
        let diceHtml = '<div class="action-details"><i><span class="ra ra-perspective-dice-random"></span> Lancé de dés...</i></div>';

        if(window.hudShowActionResult){

            window.hudShowActionResult(diceHtml);
        }
        else{

            $('.card-text').html(diceHtml);
        }


        $.ajax({
            type: "POST",
            url: url,
            data: {'action':action, 'targetId':targetId, 'coordsX': coordsX, 'coordsY': coordsY, 'coordsZ': coordsZ, 'coordsPlan': coordsPlan}, // serializes the form's elements.
            success: function(data)
            {
                if(window.hudShowActionResult){

                    /* true = résultat FINAL : le HUD rafraîchit pilules,
                     * cible observée et flux d'évènements. */
                    window.hudShowActionResult(data, true);
                }
                else{

                    let $action = $('<div>'+ data +'</div>').hide();
                    $('.card-text').html('').addClass('action-text').append($action.fadeIn());
                }
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
