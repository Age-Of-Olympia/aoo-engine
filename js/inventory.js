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

            /* HUD : le panneau Artisanat pré-filtré sur l'objet, sans
             * quitter le plateau ; habillage hérité : pleine page. */
            if(window.hudOpenPanel){

                window.hudOpenPanel('load_inventory.php?craft&itemId='+ window.id, 'Artisanat');
                return false;
            }

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

        /* Libellé du bouton d'aperçu (« Équiper (1 Ae) »,
         * « Déséquiper »…) : sert au message de confirmation. */
        var label = $(this).text().trim();

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
                    /* Panneau HUD : les paramètres sont ceux du
                     * fragment, pas de la page (main.js). */
                    targetId = aooViewParam('targetId');
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

            /* Équiper / déséquiper / consommer : confirmation — le
             * clic direct sans validation causait des erreurs
             * (retours joueurs juillet 2026). Message composé du
             * libellé d'aperçu : « Équiper gladius en main1 (1 Ae) ? » */
            var askConfirm = Promise.resolve(true);

            if(action == 'use'){

                var verb = label.split(' ')[0];
                var cost = label.substring(verb.length).trim();

                var msg = verb + ' ' + window.name;
                if(verb == 'Équiper' && window.emplacement){
                    msg += ' en ' + window.emplacement;
                }
                if(cost){
                    msg += ' ' + cost;
                }

                askConfirm = aooConfirm(msg + ' ?');
            }

            askConfirm.then(function(ok){

                if(!ok){
                    return;
                }

                $.ajax({
                    type: "POST",
                    url: 'inventory.php',
                    data: {'action': action,'itemId': window.id,'item': window.name,'n': n, 'price': window.price}, // serializes the form's elements.
                    success: function(data)
                    {
                        var contentData = $('<div></div>').html(data).find('#data');

                        if(!contentData.html()){

                            aooReload();
                            return;
                        }

                        /* HUD : le panneau se recharge sous l'alerte ;
                         * habillage hérité : message PUIS rechargement
                         * (l'alerte modale n'est pas bloquante). */
                        if(window.hudReloadPanels){

                            aooAlert(contentData.text());
                            aooReload();
                            return;
                        }

                        aooAlert(contentData.text()).then(aooReload);
                    }
                });
            });
        });

        return false;
    });
});
