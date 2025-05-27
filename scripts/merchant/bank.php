<?php

$player = new Player($_SESSION['playerId']);


if(!empty($_POST['action']) && !empty($_POST['itemId']) && !empty($_POST['n'])){


    $item = new Item($_POST['itemId']);


    if($_POST['action'] == 'withdraw'){


        if(!is_numeric($_POST['n']) || $_POST['n'] < 1 || $_POST['n'] > $item->get_n($player, bank:true)){

            exit('error n');
        }


        if(!$item->add_item($player, -$_POST['n'], bank:true)){

            exit('error withdraw bank');
        }

        $item->add_item($player, $_POST['n']);
    }

    elseif($_POST['action'] == 'store'){


        // script called from inventory.php


        if(!is_numeric($_POST['n']) || $_POST['n'] < 1 || $_POST['n'] > $item->get_n($player)){

            exit('error n');
        }


        if(!$item->add_item($player, -$_POST['n'])){

            exit('error withdraw bank');
        }

        $item->add_item($player, $_POST['n'], bank:true);
    }

    exit();
}


echo '<h1>Banque</h1>';

echo '<sup>Votre Or en Banque augmente de '. BANK_PCT .'% chaque jour passé sans combattre.</sup>';

echo $market->print_bank($player);


?>
<script src="js/progressive_loader.js"></script>
<?php
if($market->HasTarget()){
?>
<script>
$(document).ready(function(){

    var $actions = $('.preview-action');

    $actions
    .append('<button class="action" data-action="withdraw">←Retirer</button><br />');

    $('.action').click(function(e){


        var action = $(this).data('action');
        var n = 0;

        n = prompt('Combien?', window.n);

        if(n == null){

            return false;
        }
        if(n == '' || n < 1 || n > window.n){

            alert('Nombre invalide!');
            return false;
        }

        $.ajax({
            type: "POST",
            url: 'merchant.php?targetId=<?php echo isset($target)?$target->id :"0" ?>&bank', // 0 allow valid link even if code should not be used in that case 
            data: {'action': action,'itemId': window.id,'n': n}, // serializes the form's elements.
            success: function(data)
            {
                // alert(data);
                document.location.reload();
            }
        });
    });
});
</script>
<?php
}
?>