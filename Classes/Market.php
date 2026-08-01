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

    /**
     * Ce qu'un joueur peut livrer pour une demande d'achat : sa pile de
     * banque (intacte par construction, donc toujours éligible) et
     * chacun de ses exemplaires qui satisfait le seuil d'état exigé.
     *
     * Calculé serveur pour que le vendeur ne se voie proposer que de
     * l'éligible ; le service revérifie à l'acceptation — cette liste
     * est une commodité, pas une garde.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private static function deliverableChoices($player, Item $item, int $minPct): array
    {
        $choices = [];

        $stack = $item->get_n($player, bank: true, includeInstances: false);
        if ($stack > 0) {
            $choices[] = ['value' => '', 'label' => 'Depuis la pile (x' . $stack . ', neuve)'];
        }

        $service = new \App\Service\ItemInstanceService();
        foreach ($service->listForBank((int) $player->id) as $row) {
            if ((int) $row['item_id'] !== (int) $item->id) {
                continue;
            }
            if (!\App\Service\ItemInstanceService::meetsCondition(
                (int) $row['durability'],
                (int) $row['durability_max'],
                $minPct
            )) {
                continue;
            }

            $choices[] = [
                'value' => (string) $row['instance_id'],
                'label' => \App\Service\ItemInstanceService::label($row['custom_name'], (string) $row['name'])
                    . ' — ' . strip_tags(
                        \App\Service\ItemInstanceService::stateLine($row, withBreak: false)
                    ),
            ];
        }

        return $choices;
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

        /* L'état de l'exemplaire voyage avec l'offre de VENTE : sans
         * cette jointure, un acheteur ne saurait pas s'il paie une épée
         * neuve ou une épée à 3/20. L'offre ne PORTE pas l'état (il
         * resterait à recopier et à maintenir) — elle le référence.
         *
         * Réservée à items_bids : seules les offres de vente séquestrent
         * un exemplaire, et seule cette table porte instance_id. Une
         * demande d'achat n'entiercit que de l'or, elle vise un objet de
         * catalogue ; joindre sur une colonne qu'elle n'a pas faisait
         * planter toute la page des demandes. */
        $instanceJoin = $table == 'bids'
            ? ', ' . \App\Service\ItemInstanceService::WEAR_SELECT
                . ', i.quality, i.custom_name, i.destroyed'
            : '';
        /* L'usure vit dans la vie de l'exemplaire : il faut donc son type pour
         * le maximum, et son déficit pour le restant. LEFT JOIN de bout en
         * bout — une offre peut viser une pile, qui n'a pas d'exemplaire. */
        $instanceFrom = $table == 'bids'
            ? ' LEFT JOIN item_instances i ON i.id = o.instance_id
                LEFT JOIN items it ON it.id = i.item_id
                LEFT JOIN players_bonus wear ON wear.player_id = i.entity_id AND wear.name = "pv"'
            : '';

        $sql = '
        SELECT
        o.*' . $instanceJoin . '
        FROM
        items_' . $table . ' AS o' . $instanceFrom . '
        WHERE
        o.stock > 0
        ORDER BY
        o.price
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

            /* Ce que l'acheteur doit savoir AVANT de payer : un
             * exemplaire porte un nom et une usure, et la colonne
             * « quantité » n'a pas de sens pour lui. L'état vient
             * d'ItemInstanceService — même règle et mêmes couleurs que
             * dans l'inventaire, pas une deuxième formulation. */
            $isInstance = !empty($row->instance_id);
            $quantityCell = $isInstance
                ? '<em>unique</em>' . \App\Service\ItemInstanceService::stateLine($row)
                : 'x' . $row->stock;

            /* Demande d'achat : l'acheteur a bloqué son or à l'avance et
             * annonce le pire état qu'il accepte. Le vendeur doit le
             * voir AVANT de proposer quoi que ce soit. */
            $minPct = (int) ($row->min_durability_pct ?? 0);
            if ($table == 'asks' && $minPct > 0) {
                $quantityCell .= '<br /><small>'
                    . htmlspecialchars(
                        \App\Service\ItemInstanceService::CONDITION_LEVELS[$minPct] ?? '—',
                        ENT_QUOTES,
                        'UTF-8'
                    )
                    . '</small>';
            }

            /* Ce que CE joueur peut livrer pour cette demande : sa pile
             * de banque, et chacun de ses exemplaires qui satisfait le
             * seuil. La liste est calculée serveur — le client ne
             * propose que l'éligible, et le serveur revérifie. */
            $deliverable = [];
            if ($table == 'asks' && $action == 'accept') {
                $deliverable = self::deliverableChoices($player, $item, $minPct);
            }

            if ($isInstance && !empty($row->custom_name)) {
                $quantityCell = '« ' . htmlspecialchars((string) $row->custom_name, ENT_QUOTES, 'UTF-8') . ' »'
                    . \App\Service\ItemInstanceService::stateLine($row);
            }

            echo '
                <td>
                    ' . $quantityCell . '
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
                        data-instance="' . ($isInstance ? 1 : 0) . '"
                        data-deliverable="' . htmlspecialchars(json_encode(array_values($deliverable)), ENT_QUOTES, 'UTF-8') . '"

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
                /* Exemplaire unique : ni quantité à demander, ni total à
                 * calculer — l'offre part en entier. */
                var isInstance = $(this).data('instance') == 1;

                /* Répondre à une demande d'achat : le vendeur choisit CE
                 * qu'il livre. La liste ne contient que l'éligible au
                 * seuil d'état exigé — un seul choix possible, on ne
                 * demande rien. */
                var deliverable = $(this).data('deliverable') || [];
                if (type == 'asks' && action == 'accept' && deliverable.length) {

                    var pick = deliverable.length === 1
                        ? Promise.resolve(String(deliverable[0].value))
                        : aooChoose('Que livrez-vous ?', deliverable, deliverable[0].value);

                    pick.then(function(chosen) {

                        if (chosen === null) { return; }

                        payload.instance_id = chosen;

                        if (chosen !== '') {
                            payload.quantity = 1;
                            aooConfirm('Livrer cet exemplaire pour ' + price + 'Po ?').then(function(ok) {
                                if (ok) {
                                    aooFetch(url, payload, null).then(autoModal).catch(autoError());
                                }
                            });
                            return;
                        }

                        aooPrompt('Combien ?', stock).then(function(value) {
                            var n = parseInt(value, 10);
                            if (value === null) { return; }
                            if (isNaN(n) || n < 1 || n > stock) { aooAlert('Nombre invalide !'); return; }
                            payload.quantity = n;
                            aooConfirm(label + ' x' + n + '\npour un total de ' + (n * price) + 'Po ?').then(function(ok) {
                                if (ok) {
                                    aooFetch(url, payload, null).then(autoModal).catch(autoError());
                                }
                            });
                        });
                    });

                    return;
                }

                var askConfirmText = (action == 'cancel')

                    ? Promise.resolve('Annuler ' + item + (isInstance ? ' ?' : ' x' + stock + ' ?'))

                    : isInstance

                    ? (function() {
                        payload.quantity = 1;
                        return Promise.resolve(label + ' ' + item + '\npour ' + price + 'Po ?');
                    })()

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
