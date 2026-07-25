$(document).ready(function () {

    $('#item').change(function (e) {

        var itemId = $(this).val();

        window.itemId = itemId;
        window.action = 'newAsk';


        $.ajax({
            type: "POST",
            url: 'merchant.php?targetId=' + window.targetId + '&bids&hideMenu&itemId=' + itemId,
            data: {}, // serializes the form's elements.
            success: function (data) {
                /* voir new_contract.php : id propre au contrat */
                $('#contract-preview').html(data);
            }
        });
    });


    $('#submit').click(function (e) {


        var itemId = window.itemId;

        aooPrompt('Quantité?', 1).then(function (n) {

            if (n == null) {

                return;
            }

            if (n == '' || n < 1) {

                aooAlert('Nombre invalide!');
                return;
            }


            let basePrice = window.basePrice || 0;


            aooPrompt('Prix à l\'unité?', basePrice).then(function (price) {

                if (price == null) {

                    return;
                }

                if (price == '' || price < 1) {

                    aooAlert('Nombre invalide!');
                    return;
                }



                /* Panneau HUD : les paramètres sont ceux du
                 * fragment, pas de la page (main.js). */
                /* L'acheteur bloque son or À L'AVANCE : sans ce choix,
                 * il paierait sans savoir dans quel état on le
                 * livrerait — les exemplaires usés circulent désormais.
                 * Le libellé décrit le PIRE état accepté. */
                aooChoose('État minimum accepté ?', [
                    { value: '100', label: 'Neuf uniquement' },
                    { value: '50', label: 'Bon état ou mieux' },
                    { value: '1', label: 'Tout sauf brisé' }
                ], '50').then(function (minCondition) {

                    if (minCondition === null) {

                        return;
                    }

                    targetId = aooViewParam('targetId');
                    let url = 'api/exchanges/asks-bids.php?targetId=' + targetId;
                    let payload = {
                        'action': 'create',
                        'type': 'asks',
                        'item_id': itemId,
                        'quantity': n,
                        'price': price,
                        'min_condition': minCondition
                    };
                    aooFetch(url, payload, null)
                        .then(autoModal)
                        .catch(autoError());
                });
            });
        });
    });
});
