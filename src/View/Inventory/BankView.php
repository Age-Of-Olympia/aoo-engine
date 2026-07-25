<?php

namespace App\View\Inventory;

use App\Factory\PlayerFactory;
use App\Service\ItemInstanceService;
use Classes\Item;
use Classes\Market;
use Classes\Player;

class BankView
{
    public static function renderBank(?Market $market=null, ?Player $target=null): void
    {
        $player = PlayerFactory::active();

        if (!empty($_POST['action']) && !empty($_POST['itemId']) && !empty($_POST['n'])) {


            $item = new Item($_POST['itemId']);

            /* Objet individualisé (usé, nommé, de qualité) : il ne se
             * compte pas, il se déplace. Son identité et son usure
             * restent sur la même ligne de possession — seule sa
             * localisation change, d'où l'absence de quantité ici. */
            $instanceId = (int) ($_POST['instanceId'] ?? 0);

            if ($instanceId > 0) {

                $service = new ItemInstanceService();

                try {

                    if ($_POST['action'] == 'withdraw') {

                        $service->withdrawFromBank($instanceId, (int) $player->id);
                    } elseif ($_POST['action'] == 'store') {

                        if (!$item->is_bankable()) {

                            exit('Cet objet est refusé en banque.');
                        }

                        $service->storeInBank($instanceId, (int) $player->id);
                    }
                } catch (\InvalidArgumentException $e) {

                    exit($e->getMessage());
                }

                exit();
            }


            if ($_POST['action'] == 'withdraw') {


                if (!is_numeric($_POST['n']) || $_POST['n'] < 1 || $_POST['n'] > $item->get_n($player, bank: true)) {

                    exit('error n');
                }


                if (!$item->add_item($player, -$_POST['n'], bank: true)) {

                    exit('error withdraw bank');
                }

                $item->add_item($player, $_POST['n']);
            } elseif ($_POST['action'] == 'store') {


                // script called from inventory.php

                //todo add check is_bankable
                if(!$item->is_bankable()){
                    exit('Cet objet est refusé en banque.');
                }


                /* Pile uniquement : le dépôt décrémente la pile — compter
                 * les instances gonflerait la garde et ferait échouer le
                 * add_item plus bas avec un message trompeur. */
                if (!is_numeric($_POST['n']) || $_POST['n'] < 1 || $_POST['n'] > $item->get_n($player, includeInstances: false)) {

                    exit('error n');
                }


                if (!$item->add_item($player, -$_POST['n'])) {

                    exit('error withdraw bank');
                }

                $item->add_item($player, $_POST['n'], bank: true);
            }

            exit();
        }

        if($market===null){
         exit('error no market');
        }
        
        echo '<h1>Banque</h1>';

        echo '<sup>Votre Or en Banque augmente de ' . BANK_PCT . '% chaque jour passé sans combattre.</sup>';

        echo $market->print_bank($player);


?>
        <script src="js/progressive_loader.js?v=20260716"></script>
        <?php
        if ($market->HasTarget()) {
        ?>
            <script>
                $(document).ready(function() {

                    var $actions = $('.preview-action');

                    $actions
                        .append('<button class="action" data-action="withdraw">←Retirer</button><br />');

                    /* Sélecteur restreint au bouton posé juste au-dessus :
                     * « action » seule est partagée avec l'inventaire et
                     * les contrats du marché. */
                    $('.preview-action .action[data-action="withdraw"]').click(function(e) {


                        var action = $(this).data('action');
                        var url = 'merchant.php?targetId=<?php echo isset($target) ? $target->id : "0" ?>&bank'; /* 0 allow valid link even if code should not be used in that case */

                        function send(n) {

                            $.ajax({
                                type: "POST",
                                url: url,
                                data: {
                                    'action': action,
                                    'itemId': window.id,
                                    'instanceId': window.instanceId || '',
                                    'n': n
                                },
                                success: function(data) {

                                    /* Erreur métier : le serveur renvoie sa
                                     * raison en clair plutôt qu'une page. */
                                    var text = $('<div></div>').html(data).text().trim();

                                    if (text) {

                                        aooAlert(text).then(aooReload);
                                        return;
                                    }

                                    /* Panneau HUD ou page (main.js) */
                                    aooReload();
                                }
                            });
                        }

                        /* Un objet individualisé est unique : il n'y a
                         * pas de quantité à demander. */
                        if (window.instanceId) {

                            send(1);
                            return;
                        }

                        aooPrompt('Combien?', window.n).then(function(n) {

                            if (n == null) {

                                return;
                            }
                            if (n == '' || n < 1 || n > window.n) {

                                aooAlert('Nombre invalide!');
                                return;
                            }

                            send(n);
                        });
                    });
                });
            </script>
<?php
        }
    }
}
