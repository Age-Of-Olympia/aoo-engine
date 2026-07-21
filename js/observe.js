$(document).ready(function(){

    window.visible = false;


    /* Boutons d'action DIRECTS (.action--direct : Ramasser sa propre
       case…) : un clic = le geste, via leur gestionnaire délégué
       dédié — ils échappent au cycle en deux temps (élargir puis
       confirmer) des actions de case, qui avalait le premier clic
       puis POSTait un data-action vide (« error action »). */
    $('.action').not('.action--direct').click(function(e){


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

        /* Dés peints (img/ui/paper, icônes en réserve) visibles
         * SEULEMENT quand l'action jette vraiment les dés — signature
         * « Jet X = … » des conditions dans la réponse. Pendant la
         * requête : état neutre. Sans jet (repos, erreur, plus assez
         * d'actions…) : résultat direct. Avec jet : les dés tiennent
         * l'écran ROLL_TOTAL_MS depuis le clic, REQUÊTE COMPRISE —
         * lente, elle ne rajoute rien, avec un plancher pour que les
         * dés restent perceptibles. */
        let ROLL_TOTAL_MS = 600;
        let ROLL_FLOOR_MS = 250;
        let actionStart = Date.now();

        if(window.hudShowActionResult){

            window.hudShowActionResult('<div class="hud-dice-roll"><i>…</i></div>');
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
                    if(!/Jet [^=]*=/.test(data)){

                        window.hudShowActionResult(data, true);
                        $('.action').prop('disabled', false);
                        return;
                    }

                    window.hudShowActionResult(
                        '<div class="hud-dice-roll">'
                        + '<img src="img/ui/paper/icon-caracs.png" alt="" />'
                        + '<i>Lancé de dés…</i>'
                        + '</div>'
                    );

                    setTimeout(function(){

                        window.hudShowActionResult(data, true);
                        $('.action').prop('disabled', false);
                    }, Math.max(ROLL_FLOOR_MS, ROLL_TOTAL_MS - (Date.now() - actionStart)));
                    return;
                }

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

/* Bourse : Ramasser sa propre case (bouton injecté en AJAX — délégué). */
$(document).on('click', '#pickup-own-tile', function(){

    var b = this;
    b.disabled = true;

    fetch('pickup.php', {method: 'POST'}).then(function(r){ return r.text(); })
        .then(function(t){ aooAlert(t).then(function(){ document.location.reload(); }); });
});
