<?php

$itemList = Item::get_item_list($player->id);


if(!empty($_POST['action'])){


    if(in_array($_POST['action'], array('drop','use'))){

        include('scripts/inventory/'. $_POST['action'] .'.php');

        exit();
    }

    if(in_array($_POST['action'], array('newAsk','newBid'))){

        include('scripts/merchant/new_contract.php');

        exit();
    }
}


echo Ui::print_inventory($itemList);


?>
<script>
$(document).ready(function(){

    var $actions = $('.preview-action');

    $actions
    .append('<button class="action" data-action="use">Utiliser</button><br />')
    .append('<button class="action" data-action="drop">Déposer</button><br />');


    $('.action').click(function(e){


        var action = $(this).data('action');
        var n = 0;

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
        }

        $.ajax({
            type: "POST",
            url: 'inventory.php',
            data: {'action': action,'itemId': window.id,'item': window.name,'n': n, 'price': window.price}, // serializes the form's elements.
            success: function(data)
            {
                alert(data);
                document.location.reload();
            }
        });
    });
});
</script>
