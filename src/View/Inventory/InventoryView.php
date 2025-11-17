<?php

namespace App\View\Inventory;

use App\Service\InventoryService;
use App\Tutorial\TutorialHelper;
use Classes\Player;
use Classes\Item;
use Classes\Ui;

class InventoryView
{
    public static function renderInventory(bool $itemsFromBank): void
    {

        if (!empty($_POST['action'])) {

            // Get active player ID (tutorial player if in tutorial mode, otherwise main player)
            $activePlayerId = TutorialHelper::getActivePlayerId();
            $player = new Player($activePlayerId);

            $itemList = Item::get_item_list($player->id);


            if (in_array($_POST['action'], array('drop', 'use'))) {
                $item = new Item($_POST['itemId']);
                $item->get_data();

                $player->get_data();

                switch ($_POST['action']) {
                    case 'drop':
                        InventoryService::dropItem($player, $item);
                        break;
                    case 'use':
                        InventoryService::useItem($player, $item);
                        break;
                };

                exit();
            }

            if (in_array($_POST['action'], array('newAsk', 'newBid'))) {

                include('scripts/merchant/new_contract.php');

                exit();
            }
        }


        // Get active player ID (tutorial player if in tutorial mode, otherwise main player)
        $activePlayerId = TutorialHelper::getActivePlayerId();

        $path = 'datas/private/players/' . $activePlayerId . '.invent.html';

        $player = new Player($activePlayerId);

        $itemList = Item::get_item_list($player->id, bank: $itemsFromBank);
        $data = Ui::print_inventory($itemList);
        $data .= '
<script>
window.freeEmp = ' . Item::get_free_emplacement($player) . ';
window.aeLeft = ' . $player->getRemaining('ae') . ';
window.aLeft = ' . $player->getRemaining('a') . ';
</script>
';

        $myfile = fopen($path, "w") or die("Unable to open file!");
        fwrite($myfile, $data);
        fclose($myfile);

        echo $data;


?>
        <script src="js/progressive_loader.js"></script>
        <script src="js/inventory.js?v=202506245"></script>
<?php
    }
}
