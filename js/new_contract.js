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

        var n = prompt('Quantité?', 1);

        if (n == null) {

            return false;
        }

        if (n == '' || n < 1) {

            alert('Nombre invalide!');
            return false;
        }


        let basePrice = window.basePrice || 0;


        var price = prompt('Prix à l\'unité?', basePrice);

        if (price == null) {

            return false;
        }

        if (price == '' || price < 1) {

            alert('Nombre invalide!');
            return false;
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
