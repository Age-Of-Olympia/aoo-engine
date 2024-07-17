<?php


if(!empty($_POST['action'])){


    $player = new Player($_SESSION['playerId']);

    $itemList = Item::get_item_list($player->id);


    if(in_array($_POST['action'], array('drop','use'))){

        include('scripts/inventory/'. $_POST['action'] .'.php');

        exit();
    }

    if(in_array($_POST['action'], array('newAsk','newBid'))){

        include('scripts/merchant/new_contract.php');

        exit();
    }
}


$path = 'datas/private/players/'. $_SESSION['playerId'] .'.invent.html';

if(!file_exists($path) || !CACHED_INVENT){


    $player = new Player($_SESSION['playerId']);

    $itemList = Item::get_item_list($player->id);

    $data = Ui::print_inventory($itemList);

    $myfile = fopen($path, "w") or die("Unable to open file!");
    fwrite($myfile, $data);
    fclose($myfile);
}
else{

    $data = file_get_contents($path);
}

echo $data;


?>
<script src="js/progressive_loader.js"></script>
<script>
$(document).ready(function(){


    window.freeEmp = <?php echo Item::get_free_emplacement($player) ?>;


    var $actions = $('.preview-action');

    $actions
    .append('<button class="action" data-action="use">Utiliser</button><br />')
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
                document.location.reload();
            }
        });
    });
});
</script>
