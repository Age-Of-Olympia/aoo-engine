<?php

namespace App\View\Inventory;

use App\Action\Condition\ItemPickCondition;
use App\Factory\PlayerFactory;
use App\Service\InventoryService;
use App\Tutorial\TutorialHelper;
use App\View\AssetTableView;
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

        $player = PlayerFactory::legacy($activePlayerId);

        $itemList = Item::get_item_list($player->id, bank: $itemsFromBank);

        /* Compteur d'Actions d'Équipement : la carac n'apparaît plus
         * dans le nouveau HUD (pilules et page d'amélioration l'ignorent),
         * on l'affiche là où elle sert — équiper/déséquiper un objet. */
        /* The bag section carries its own gauge (Ui::print_inventory,
         * $bagLabel): the limit reads exactly on what it counts —
         * ContainerService is the one rule that also refuses the line. */
        $capacityService = new \App\Service\ContainerService();
        $bagCapacity = $capacityService->capacityOf((int) $player->id);
        $bagLabel = $itemsFromBank ? null
            : 'Dans votre sac (' . $capacityService->lineCountOf((int) $player->id)
                . ($bagCapacity !== null ? '/' . $bagCapacity : '') . ' lignes)';

        $aeInfo = '<span class="inventory-ae" style="float: left; line-height: 28px;" flow="right" tooltip="'
            . CARACS_TXT_LONG['ae'] . '">'
            . 'Actions d\'équipement : ' . $player->getRemaining('ae') . '/' . $player->get_caracsJson()->ae
            . '</span>';

        $data = Ui::print_inventory(
            $itemList,
            $aeInfo,
            rowActions: $hudPanel,
            aeLeft: $player->getRemaining('ae'),
            aLeft: $player->getRemaining('a'),
            bagLabel: $bagLabel
        );
        $data .= '
<script>
window.freeEmp = ' . Item::get_free_emplacement($player) . ';
window.aeLeft = ' . $player->getRemaining('ae') . ';
window.aLeft = ' . $player->getRemaining('a') . ';
</script>
';

        echo $data;

        if (!$itemsFromBank) {
            echo self::ownAssetsHtml((int) $player->id);
        }


?>
        <script src="js/progressive_loader.js?v=20260716"></script>
        <script src="js/inventory.js?v=20260927"></script>
<?php
    }

    /**
     * What the player owns in person, standing on the board: chests,
     * doors, buildings — one section each, only when there is one.
     */
    private static function ownAssetsHtml(int $playerId): string
    {
        $chests = (new \App\Service\ContainerService())->chestsOwnedBy($playerId);
        $doors = (new \App\Service\FactionService())->doorsOwnedBy($playerId);
        $buildings = (new \App\Service\FactionService())->buildingsOwnedBy($playerId);
        if ($chests === [] && $doors === [] && $buildings === []) {
            return '';
        }

        $html = '<div class="inventory-own-assets" data-reload="load_inventory.php|Inventaire">';

        if ($chests !== []) {
            $chestService = new \App\Service\FactionChestService();
            $rows = [];
            foreach ($chests as $chest) {
                $rows[] = [
                    AssetTableView::entityLinkHtml($chest['id'], $chest['name']),
                    AssetTableView::contentsCellHtml($chest['id'], $playerId),
                    ($chest['isOpen'] ? 'Ouvert' : '<span class="ra ra-key"></span> Fermé')
                        . AssetTableView::lockCellHtml($chest['id'], $playerId),
                    AssetTableView::territoryHtml($chest['plan'], $chest['x'], $chest['y'], $chest['z']),
                    $chestService->mayEntrust($chest['id'], $playerId) ? AssetTableView::entrustCellHtml($chest['id']) : '',
                ];
            }
            $html .= '<h3>Mes coffres</h3>' . AssetTableView::table(['Nom', 'Contenu', 'État', 'Territoire', ''], $rows);
        }

        if ($doors !== []) {
            $html .= '<h3>Mes portes</h3>'
                . AssetTableView::table(AssetTableView::DOOR_HEADERS, AssetTableView::doorRows($doors, $playerId));
        }

        if ($buildings !== []) {
            // No upkeep column: decay is announced to factions only.
            $html .= '<h3>Mes bâtiments</h3>'
                . AssetTableView::buildingsTable($buildings, true, $playerId, withUpkeep: false);
        }

        return $html . '</div>'
            . AssetTableView::script()
            . ($buildings !== [] ? AssetTableView::driveScript() : '');
    }
}
