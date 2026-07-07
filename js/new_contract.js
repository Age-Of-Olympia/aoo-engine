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
                // alert(data);
                $('#ajax-data').html(data);
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



                const urlParams = new URLSearchParams(window.location.search);
                targetId = urlParams.get('targetId');
                let url = 'api/exchanges/asks-bids.php?targetId=' + targetId;
                let payload = {
                    'action': 'create',
                    'type': 'asks',
                    'item_id': itemId,
                    'quantity': n,
                    'price': price
                };
                aooFetch(url, payload, null)
                    .then(autoModal)
                    .catch(autoError());
            });
        });
    });
});
