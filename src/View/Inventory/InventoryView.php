<?php

namespace App\View\Inventory;

use App\Action\Condition\ItemPickCondition;
use App\Factory\PlayerFactory;
use App\Service\InventoryService;
use App\Tutorial\TutorialHelper;
use Classes\Item;
use Classes\Ui;

class InventoryView
{
    /**
     * @param bool $hudPanel rendu en panneau du HUD : chaque ligne porte
     *                       ses boutons Utiliser/Jeter/Artisanat (même
     *                       esprit que le bouton Améliorer par ligne du
     *                       panneau de caractéristiques). Les pages
     *                       héritées (inventory.php, marchand) sont
     *                       inchangées.
     */
    public static function renderInventory(bool $itemsFromBank, bool $hudPanel = false): void
    {

        if (!empty($_POST['action'])) {

            $player = PlayerFactory::active();

            $itemList = Item::get_item_list($player->id);


            if (in_array($_POST['action'], array('drop', 'use'))) {
                $item = new Item($_POST['itemId']);
                $item->get_data();

                $player->get_data();

                // Mêmes lecteurs que le moteur d'actions (ItemPickCondition,
                // source unique du contrat client) : instance précise
                // cliquée, et sens de la bascule — LA ligne cliquée
                // était-elle portée ? (null = contexte non fourni)
                $instanceId = ItemPickCondition::requestedInstanceId();
                $clickedEquippedLine = ItemPickCondition::requestedEquippedLine();

                switch ($_POST['action']) {
                    case 'drop':
                        InventoryService::dropItem($player, $item, $instanceId);
                        break;
                    case 'use':
                        InventoryService::useItem($player, $item, $instanceId, $clickedEquippedLine);
                        break;
                };

                exit();
            }

            if (in_array($_POST['action'], array('newAsk', 'newBid'))) {

                include('scripts/merchant/new_contract.php');

                exit();
            }
        }


        $activePlayerId = TutorialHelper::getActivePlayerId();

        $path = 'datas/private/players/' . $activePlayerId . '.invent.html';

        $player = PlayerFactory::legacy($activePlayerId);

        $itemList = Item::get_item_list($player->id, bank: $itemsFromBank);

        /* Compteur d'Actions d'Équipement : la carac n'apparaît plus
         * dans le nouveau HUD (pilules et page d'amélioration l'ignorent),
         * on l'affiche là où elle sert — équiper/déséquiper un objet. */
        $aeInfo = '<span class="inventory-ae" style="float: left; line-height: 28px;" flow="right" tooltip="'
            . CARACS_TXT_LONG['ae'] . '">'
            . 'Actions d\'équipement : ' . $player->getRemaining('ae') . '/' . $player->get_caracsJson()->ae
            . '</span>';

        $data = Ui::print_inventory(
            $itemList,
            $aeInfo,
            rowActions: $hudPanel,
            aeLeft: $player->getRemaining('ae'),
            aLeft: $player->getRemaining('a')
        );
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
        <script src="js/progressive_loader.js?v=20260716"></script>
        <script src="js/inventory.js?v=20260722e"></script>
<?php
    }
}
