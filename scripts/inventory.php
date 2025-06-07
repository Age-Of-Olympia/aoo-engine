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

$player = new Player($_SESSION['playerId']);

$itemList = Item::get_item_list($player->id, bank: $itemsFromBank);
$data = Ui::print_inventory($itemList);
$data .= '
<script>
window.freeEmp = '. Item::get_free_emplacement($player) .';
window.aeLeft = '. $player->getRemaining('ae') .';
</script>
';

$myfile = fopen($path, "w") or die("Unable to open file!");
fwrite($myfile, $data);
fclose($myfile);

echo $data;


?>
<script src="js/progressive_loader.js"></script>
<script src="js/inventory.js?v=20250516"></script>
