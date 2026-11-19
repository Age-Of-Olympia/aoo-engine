<?php

namespace App\View\Merchant;

use Classes\Item;
use Classes\Market;
use Classes\Player;
use Classes\Str;

/**
 * One side of the market: the purchase requests (asks) or the sale offers
 * (bids). Each page offers to post on its own side, then on the other.
 */
class MarketSideView
{
    private const SIDES = [
        'asks' => [
            'title' => 'Demandes d\'Achat',
            'intro' => '<div>Voici les objets que les autres personnages veulent acheter.<br /><b>Vous vendez les objets de votre sac, et l\'or reçu arrive dans votre sac.</b></div>',
            'mine' => 'Certaines des propositions d\'achat sont les vôtres',
            'icon' => 'ra-scroll-unfurled',
            'button' => 'Nouvelle demande d\'Achat',
            'section' => 'Demande d\'Achat',
            'verb' => 'acheter',
            'contract' => 'nouvelle demande d\'achat',
        ],
        'bids' => [
            'title' => 'Offres de Vente',
            'intro' => '<div><p>Voici les objets que les autres personnages veulent vendre.<br /><b>Vous payez avec l\'or de votre sac, et les objets achetés arrivent dans votre sac.</b></p></div>',
            'mine' => 'Certains des objets en vente sont les vôtres',
            'icon' => 'ra-gavel',
            'button' => 'Nouvelle offre de Vente',
            'section' => 'Offre de Vente',
            'verb' => 'vendre',
            'contract' => 'nouvelle offre de vente',
        ],
    ];

    /** @param 'asks'|'bids' $side */
    public static function render(string $side, Player $player, Market $market, Player $target): void
    {
        ob_start();

        if (!empty($_GET['itemId'])) {
            $item = new Item($_GET['itemId']);
            $item->get_data();

            echo '<h1>' . ucfirst($item->data->name) . '</h1>';
            echo '<div><img src="' . $item->data->mini . '"' . Item::sheetAttributes($item->data) . ' /></div>';
            echo $side === 'asks' ? self::itemsOwned($item, $player) : self::goldAndBasePrice($item, $player);
            echo $market->print_detail($item, $side, $player);

            exit();
        }

        $own = self::SIDES[$side];
        $otherSide = $side === 'asks' ? 'bids' : 'asks';
        $other = self::SIDES[$otherSide];

        echo '<h1>' . $own['title'] . '</h1>';

        if (isset($_GET['newContract'])) {
            include('scripts/merchant/new_contract.php');

            exit();
        }

        echo $own['intro'];
        echo $market->print_market($side, $player->id);
        echo '<br/><b>*</b> ' . $own['mine'];
?>
        <div class="button-container">
            <a href="merchant.php?targetId=<?php echo $target->id ?>&<?php echo $side ?>&newContract">
                <button><span class="ra <?php echo $own['icon'] ?>"></span> <?php echo $own['button'] ?></button>
            </a>
        </div>
        </div>

        <div class="section">
            <div class="section-title"><?php echo $other['section'] ?></div>
            <div>Si l'objet que vous souhaitez <b><?php echo $other['verb'] ?></b> n'apparaît pas dans la liste, vous pouvez créer une <?php echo $other['contract'] ?>:</div>

            <div class="button-container">
                <a href="merchant.php?targetId=<?php echo $target->id ?>&<?php echo $otherSide ?>&newContract">
                    <button><span class="ra <?php echo $other['icon'] ?>"></span> <?php echo $other['button'] ?></button>
                </a>
            </div>
        </div>
<?php

        echo Str::minify(ob_get_clean());
    }

    private static function itemsOwned(Item $item, Player $player): string
    {
        $inventory = $item->get_n($player, bank: false);
        $bank = $item->get_n($player, bank: true);

        if ($inventory == 0 && $bank == 0) {
            return 'Vous n\'en possédez pas.';
        }

        $parts = [];
        if ($inventory > 0) {
            $parts[] = '<span class="inventaire">' . $inventory . ' dans l\'inventaire</span>';
        }
        if ($bank > 0) {
            $parts[] = '<span class="banque">' . $bank . ' en banque</span>';
        }

        return 'Vous en possédez ' . implode(' et ', $parts) . '.';
    }

    private static function goldAndBasePrice(Item $item, Player $player): string
    {
        $html = '<div>Vous possédez <span class="or-inventaire">' . $player->get_gold() . '</span> Po dans l\'inventaire';
        $bankGold = $player->get_gold(true);
        if ($bankGold > 0) {
            $html .= ' et <span class="or-banque">' . $bankGold . '</span> Po en banque';
        }

        return $html . '</div>'
            . '<div>Prix de base: ' . $item->data->price . 'Po</div>'
            . '<script>window.basePrice = ' . $item->data->price . ';</script>';
    }
}
