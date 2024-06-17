<?php


if(!empty($_POST['action']) && !empty($_POST['item']) && !empty($_POST['n'])){


    $item = Item::get_item_by_name($_POST['item']);


    if(!is_numeric($_POST['n']) || $_POST['n'] < 1 || $_POST['n'] > $item->get_n($player, $bank=true)){

        exit('error n');
    }


    if($_POST['action'] == 'withdraw'){


        if(!$item->add_item($player, -$_POST['n'], $bank=true)){

            exit('error withdraw bank');
        }

        $item->add_item($player, $_POST['n']);
    }

    elseif($_POST['action'] == 'store'){


        // script called from inventory.php


        if(!$item->add_item($player, -$_POST['n'])){

            exit('error withdraw bank');
        }

        $item->add_item($player, $_POST['n'], $bank=true);
    }

    exit();
}


echo '<h1>Banque</h1>';


echo $market->print_bank($player);


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
            url: 'merchant.php?targetId=<?php echo $target->id ?>&bank',
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
