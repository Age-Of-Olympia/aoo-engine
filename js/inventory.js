$(document).ready(function(){


    var $actions = $('.preview-action');

    $actions
    .append('<button class="action" data-action="use" disabled="true">Utiliser</button><br />')
    .append('<button class="action" data-action="drop">Jeter</button><br />')
    .append('<button class="action" data-action="craft">Artisanat</button><br />');

    /* Panneau HUD (boutons par ligne) : l'aperçu redevient une fiche de
     * lecture — ses boutons restent dans le DOM (les boutons de ligne
     * leur délèguent l'exécution et l'état) mais sont masqués. */
    if($('.item-case .row-action').length){

        $actions.hide();
    }


    /* Boutons par ligne (panneau HUD, Ui::print_inventory rowActions) :
     * sélectionner la ligne (inventUi.js pose window.* et l'état du
     * bouton Utiliser de l'aperçu), puis déléguer au bouton d'aperçu
     * correspondant — la logique d'état et de coût reste unique.
     * Liaison directe (pas de délégation document) : le script est
     * ré-exécuté à chaque chargement du panneau, une délégation
     * s'empilerait. */
    $('.item-case .row-action').click(function(e){

        e.stopPropagation();

        $(this).closest('.item-case').trigger('click');

        var $preview = $('.preview-action .action[data-action="'+ $(this).data('action') +'"]').first();

        /* .trigger() de jQuery ignore l'attribut disabled : ne pas
         * exécuter une action que l'aperçu interdit (Ae/A épuisés…). */
        if(!$preview.prop('disabled')){

            $preview.trigger('click');
        }

        return false;
    });


    $('.action').click(function(e){


        var action = $(this).data('action');


        if(action == 'craft'){

            document.location = 'inventory.php?craft&itemId='+ window.id;
            return false;
        }

        if(action == 'use' && window.type == 'structure'){

            document.location = 'build.php?itemId='+ window.id;
            return false;
        }

        var isMarket = (action == "newAsk" || action == "newBid");
        var needsN = (action == 'drop' || action == "store" || isMarket);

        if(isMarket && window.name == 'or'){
            aooAlert('Impossible de vendre cet objet.');
            return false;
        }

        /* Quantité demandée en modale ; null = annulé (silencieux) */
        var askN = needsN
            ? aooPrompt('Combien?', window.n).then(function(value){
                if(value === null){
                    return null;
                }
                var n = parseInt(value);
                if(isNaN(n) || n < 1 || n > window.n){
                    aooAlert('Nombre invalide!');
                    return null;
                }
                return n;
            })
            : Promise.resolve(0);

        askN.then(function(n){

            if(n === null){
                return;
            }

            if(isMarket){

                aooPrompt('Pour quel prix? (à l\'unité)', window.price).then(function(price){

                    if(price === null){
                        return;
                    }
                    if(price == '' || price < 1){
                        aooAlert('Prix invalide!');
                        return;
                    }
                    const urlParams = new URLSearchParams(window.location.search);
                    targetId = urlParams.get('targetId');
                    let url= 'api/exchanges/asks-bids.php?targetId='+targetId;
                    let payload = {
                        'action': 'create',
                        'type': action == 'newAsk' ? 'asks' : 'bids',
                        'item_id': window.id,
                        'quantity': n,
                        'price': price
                    };
                    aooFetch(url,payload,null)
                    .then(autoModal)
                    .catch(autoError());
                });
                return;
            }

            $.ajax({
                type: "POST",
                url: 'inventory.php',
                data: {'action': action,'itemId': window.id,'item': window.name,'n': n, 'price': window.price}, // serializes the form's elements.
                success: function(data)
                {
                    var contentData = $('<div></div>').html(data).find('#data');
                    if(contentData.html()){
                        /* Message PUIS rechargement : l'alerte modale
                         * n'est pas bloquante, il faut chaîner. */
                        aooAlert(contentData.text()).then(function(){
                            document.location.reload();
                        });
                        return;
                    }
                    document.location.reload();
                }
            });
        });

        return false;
    });
});
