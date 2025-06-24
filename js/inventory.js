$(document).ready(function(){


    var $actions = $('.preview-action');

    $actions
    .append('<button class="action" data-action="use" disabled="true">Utiliser</button><br />')
    .append('<button class="action" data-action="drop">Jeter</button><br />')
    .append('<button class="action" data-action="craft">Artisanat</button><br />');


    $('.action').click(function(e){


        var action = $(this).data('action');
        var n = 0;


        if(action == 'craft'){

            document.location = 'inventory.php?craft&itemId='+ window.id;
            return false;
        }

        if(action == 'drop' || action == "store" || action == "newAsk" || action == "newBid"){

            n = prompt('Combien?', window.n);

            if(n == null){

                return false;
            }
            if(n == '' || n < 1 || n > window.n){

                alert('Nombre invalide!');
                return false;
            }
        }

        if(action == "newAsk" || action == "newBid"){


            if(window.name == 'or'){
                alert('Impossible de vendre cet objet.');
                return false;
            }


            price = prompt('Pour quel prix? (à l\'unité)', window.price);

            if(price == null){

                return false;
            }
            if(price == '' || price < 1){

                alert('Prix invalide!');
                return false;
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
            return false;
        }


        if(action == 'use'){

            if(window.type == 'structure'){

                document.location = 'build.php?itemId='+ window.id;

                return false;
            }
        }


        $.ajax({
            type: "POST",
            url: 'inventory.php',
            data: {'action': action,'itemId': window.id,'item': window.name,'n': n, 'price': window.price}, // serializes the form's elements.
            success: function(data)
            {
                // alert(data);
                var contentData = $('<div></div>').html(data).find('#data');
                if(contentData.html()){
                    alert(contentData.html())
                }
                document.location.reload();
            }
        });
    });
});
