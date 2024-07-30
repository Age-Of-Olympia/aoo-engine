$(document).ready(function(){

    $('#item').change(function(e){

        var itemId = $(this).val();

        window.itemId = itemId;
        window.action = 'newAsk';


        $.ajax({
            type: "POST",
            url: 'merchant.php?targetId='+ window.targetId +'&bids&hideMenu&itemId='+ itemId,
            data: {}, // serializes the form's elements.
            success: function(data)
            {
                // alert(data);
                $('#ajax-data').html(data);
            }
        });
    });


    $('#submit').click(function(e){


        var itemId = window.itemId;

        var n = prompt('Quantité?', 1);

        if(n == null){

            return false;
        }

        if(n == '' || n < 1){

            alert('Nombre invalide!');
            return false;
        }


        let basePrice = window.basePrice || 0;


        var price = prompt('Prix à l\'unité?', basePrice);

        if(price == null){

            return false;
        }

        if(price == '' || price < 1){

            alert('Nombre invalide!');
            return false;
        }

        $.ajax({
            type: "POST",
            url: 'merchant.php?targetId='+ window.targetId +'&bids&hideMenu&newContract',
            data: {
                'action': window.action,
                'itemId': itemId,
                'n': n,
                'price': price
            }, // serializes the form's elements.
            success: function(data)
            {
                // alert(data);
                alert(data);
            }
        });
    });
});
