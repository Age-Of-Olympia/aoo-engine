<?php
namespace Classes;

class Market
{


    private $bids = null; // offres
    private $asks = null; // demandes
    private $target; // le marchand


    function __construct($target)
    {


        $this->target = $target;
    }

    public function HasTarget()
    {
        return $this->target != null;
    }

    public function get($table)
    {

        if ($this->$table != null) {

            return $this->$table;
        }
        if ($table != 'bids' && $table != 'asks') {

            exit('error table');
        }

        $return = array();

        $order = ($table == 'bids') ? 'DESC' : 'ASC';

        $sql = '
        SELECT
        *
        FROM
        items_' . $table . '
        ORDER BY
        price
        ' . $order . '
        ';

        $db = new Db();

        $res = $db->exe($sql);

        while ($row = $res->fetch_object()) {


            if (!isset($return[$row->item_id])) {

                $return[$row->item_id] = array();
            }

            $return[$row->item_id][] = $row;
        }

        $this->$table = $return;

        return $return;
    }


    public function print_market($table, $player_id)
    {


        ob_start();


        echo '
        <table border="1" align="center" class="marbre">
        <tr>
            <th></th>
            <th>Objet</th>
            <th>Meilleur prix</th>
            <th></th>
        </tr>
        ';


        foreach ($this->get($table) as $k => $e) {


            $row = array_pop($e);


            $item = new Item($row->item_id);
            $item->get_data();


            // data-target : le marchand porté par la ligne — le handler
            // de scripts/merchant/body.php est un délégué partagé, il ne
            // peut pas se fier à un identifiant figé au rendu.
            echo '
            <tr
                class="item ' . $table . '"

                data-market="' . $table . '"
                data-name="' . $item->row->name . '"
                data-id="' . $item->id . '"
                data-target="' . $this->target->id . '"
                >
                ';

            echo '
                <td>
                    <img src="' . $item->data->mini . '" />
                </td>
                ';

            echo '
                <td>';
            //Is player having at least 1 offer?
            echo ucfirst($item->data->name);
            if (array_filter($this->$table[$k], fn($row) => $row->player_id == $player_id)) {
                echo '<b>*</b>';
            }
            echo '
                </td>
                ';

            echo '
                <td>
                    ' . $row->price . 'Po
                </td>
                ';

            echo '
                <td>
                    <a href="merchant.php?' . $table . '&targetId=' . $this->target->id . '&itemId=' . $item->id . '">
                        Négocier
                    </a>
                </td>
                ';


            echo '
            </tr>
            ';
        }

        echo '
        </table>
        ';

        return ob_get_clean();
    }


    public function print_detail($item, $table, $player)
    {


        ob_start();



        if (!isset($this->get($table)[$item->id])) {

            // Le ternaire doit être parenthésé : « . » est prioritaire sur
            // « ?: », la condition portait sur '<div>' . ($table == 'bids')
            // (toujours vraie) et le message affichait toujours « Acheter ».
            exit('<div>' . ($table == 'bids' ? 'Acheter' : 'Vendre') . ' cet objet : aucun contrat trouvé.</div>');
        }


        echo '
        <table border="1" align="center" class="marbre">
        <tr>
            <th></th>
            <th>Prix</th>
            <th>Nombre</th>
            <th>Origine</th>
            <th>Action</th>
        </tr>
        ';

        $data = $this->$table[$item->id];

        krsort($data);

        foreach ($data as $k => $row) {


            if ($k == 0) $color = 'red';
            elseif ($k == count($data) - 1) $color = 'blue';
            else $color = '';


            $playerJson = json()->decode('players', $row->player_id);

            $txt = ($table == 'bids') ? 'Acheter' : 'Vendre';
            $action = 'accept';
            if ($playerJson->id == $player->id) {
                $txt = ($table == 'bids') ? 'Annuler l\'offre' : 'Annuler la demande';
                $action = 'cancel';
            }
            $adminInfos = '';
            if ($player->have_option('isAdmin')) {
                $adminInfos = ' [<a href="infos.php?targetId=' . $player->id . '">' . $playerJson->name . '(' . $row->player_id . ')</a>]';
            }


            echo '
            <tr>
                ';


            echo '
                <td>
                    <img src="' . $item->data->mini . '" width="25" />
                </td>
                ';

            echo '
                <td>
                    <font color="' . $color . '">' . $row->price . 'Po</font>
                </td>
                ';

            echo '
                <td>
                    x' . $row->stock . '</font>
                </td>
                ';

            echo '
                <td>
                    ' . $playerJson->race . $adminInfos . '</font>
                </td>
                ';

            // market-action : classe propre au marché — « action » seule est
            // partagée avec l'inventaire et les échanges, un délégué posé
            // dessus attraperait leurs boutons (et réciproquement).
            // data-label : libellé français réutilisé dans la confirmation.
            // data-target : le marchand, porté par le bouton — plus sûr que
            // la seule requête de vue (un second panneau ouvert derrière
            // remplace window.hudPanelQuery).
            echo '
                <td>
                    <button
                        class="action market-action"

                        data-item="' . $item->id . '"
                        data-type="' . $table . '"
                        data-name="' . ucfirst($item->data->name) . '"
                        data-action="' . $action . '"
                        data-label="' . $txt . '"
                        data-stock="' . $row->stock . '"
                        data-price="' . $row->price . '"
                        data-id="' . $row->id . '"
                        data-target="' . $this->target->id . '"

                        >' . $txt . '</button>';



            echo '
                </td>
                ';

            echo '
            </tr>
            ';
        }

        echo '
        </table>
        ';


?>
        <script>
            /* Délégué namespacé et purgé : ce fragment est ré-exécuté à
             * chaque chargement de panneau (js/hud.js), un délégué non
             * purgé s'empilerait et déclencherait N transactions. */
            $(document).off('click.marketDetail').on('click.marketDetail', 'button.market-action', function(e) {

                var item = $(this).data('name');
                var label = $(this).data('label');
                var action = $(this).data('action');
                var stock = $(this).data('stock');
                var price = $(this).data('price');
                var id = $(this).data('id');
                var type = $(this).data('type');

                /* Panneau HUD : la barre d'adresse ne porte pas les
                 * paramètres du fragment. Le marchand est lu sur le
                 * bouton lui-même, et à défaut dans la requête de la
                 * vue courante (main.js). */
                var targetId = $(this).data('target') || aooViewParam('targetId');
                var url = 'api/exchanges/asks-bids.php?targetId=' + targetId;
                var payload = {
                    'action': action,
                    'type': type,
                    'id': id,
                };

                /* Modales du jeu (js/modal.js) : asynchrones — la suite
                 * vit dans les .then(), pas dans un if(confirm(...)). */
                var askConfirmText = (action == 'cancel')

                    ? Promise.resolve('Annuler ' + item + ' x' + stock + ' ?')

                    : aooPrompt('Combien ?', stock).then(function(value) {

                        /* null = annulation (silencieuse) */
                        if (value === null) {

                            return null;
                        }

                        var n = parseInt(value, 10);

                        if (isNaN(n) || n < 1 || n > stock) {

                            aooAlert('Nombre invalide !');
                            return null;
                        }

                        payload.quantity = n;
                        payload.price = price;

                        return label + ' ' + item + ' x' + n + '\nà ' + price + 'Po/unité\npour un total de ' + (n * price) + 'Po ?';
                    });

                askConfirmText.then(function(confirmText) {

                    if (confirmText === null) {

                        return;
                    }

                    aooConfirm(confirmText).then(function(ok) {

                        if (!ok) {

                            return;
                        }

                        aooFetch(url, payload, null)
                            .then(autoModal)
                            .catch(autoError());
                    });
                });
            });
        </script>
<?php

        return ob_get_clean();
    }


    public function print_bank($player)
    {


        ob_start();


        $itemList = Item::get_item_list($player, bank: true);


        echo Ui::print_inventory($itemList);


        return ob_get_clean();
    }

    //null if no error else return reason
    public static function CheckMarketAccess($player, $potentialMerchant): ?string
    {
        if (!$potentialMerchant->have_option('isMerchant')) {
            return 'error not merchant';
        }

        // distance
        $distance = View::get_distance($player->getCoords(), $potentialMerchant->getCoords());

        if ($distance > 1) {

            return ERROR_DISTANCE;
        }

        // Blocage marchand/écoles (catalogue : blocks_trading, ex-adrénaline)
        $effectService = new \App\Service\EffectService();
        if ($blocker = $effectService->tradingBlocker($player->getEffects())) {

            return 'Vous ne pouvez pas marchander sous l\'effet « ' . $blocker->getLabel() . ' ».';
        }

        if ($blocker = $effectService->tradingBlocker($potentialMerchant->getEffects())) {

            return 'Vous ne pouvez pas marchander avec un Marchand sous l\'effet « ' . $blocker->getLabel() . ' ».';
        }
        return null;
    }
}
