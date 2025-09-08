<?php

namespace App\View\Merchant;

use Classes\Item;
use Classes\Market;
use Classes\Player;
use Classes\Str;


class AsksView
{
    public static function renderAsks(Player $player, Market $market, Player $target): void
    {
        ob_start();


        if (!empty($_GET['itemId'])) {


            $item = new Item($_GET['itemId']);
            $item->get_data();


            echo '<h1>' . ucfirst($item->data->name) . '</h1>';
            echo '<div><img src="' . $item->data->mini . '" /></div>';


            $inventaire = $item->get_n($player, bank: false);
            $banque = $item->get_n($player, bank: true);;

            if ($inventaire == 0 && $banque == 0) {
                echo 'Vous n\'en possédez pas.';
            } else {
                echo 'Vous en possédez ';
                if ($inventaire > 0) {
                    echo '<span class="inventaire">' . $inventaire . ' dans l\'inventaire</span>';
                    if ($banque > 0) echo ' et ';
                }
                if ($banque > 0) {
                    echo '<span class="banque">' . $banque . ' en banque</span>';
                }
                echo '.';
            }

            echo $market->print_detail($item, 'asks', $player);

            exit();
        }


        echo '<h1>Demandes d\'Achat</h1>';


        if (isset($_GET['newContract'])) {


            include('scripts/merchant/new_contract.php');

            exit();
        }



        echo '<div>Voici les objets que les autres personnages veulent acheter.<br /><font><b>Vendez vos objets ici à partir de votre <i>stock en banque</i>.</b></font></div>';

        echo $market->print_market('asks', $player->id);

        echo '<br/><b>*</b> Certaines des propositions d\'achat sont les vôtres'
?>
        <div class="button-container">
            <a href="merchant.php?targetId=<?php echo $target->id ?>&asks&newContract">
                <button class="buy-button"><span class="ra ra-scroll-unfurled"></span> Nouvelle demande d'Achat</button>
            </a>
        </div>
        </div>

        <div class="section">
            <div class="section-title">Offre de Vente</div>
            <div>Si l'objet que vous souhaitez <b>vendre</b> n'apparaît pas dans la liste, vous pouvez créer une nouvelle offre de vente:</div>

            <div class="button-container">
                <a href="merchant.php?targetId=<?php echo $target->id ?>&bids&newContract">
                    <button class="sell-button"><span class="ra ra-gavel"></span> Nouvelle offre de Vente</button>
                </a>
            </div>
        </div>
<?php

        echo Str::minify(ob_get_clean());
    }
}
