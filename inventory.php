<?php

require_once('config.php');

$ui = new Ui('Inventaire');


$player = new Player($_SESSION['playerId']);


echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a><a href="index.php?artisanat"><button><span class="ra ra-forging"></span> Artisanat</button></a></div>';


$itemList = Item::get_item_list($player->id);


if(!empty($_POST['action'])){


    if(!in_array($_POST['action'], array('drop','use'))){

        exit('error action');
    }

    include('scripts/inventory/'. $_POST['action'] .'.php');

    exit();
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

        if(action == 'drop'){

            n = prompt('Combien?', window.n);

            if(n == '' || n < 1 || n > window.n){

                alert('Nombre invalide!');
                return false;
            }
        }

        $.ajax({
            type: "POST",
            url: 'inventory.php',
            data: {'action': action,'item': window.name,'n': n}, // serializes the form's elements.
            success: function(data)
            {
                alert(data);
                document.location.reload();
            }
        });
    });
});
</script>
