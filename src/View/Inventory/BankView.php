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

                /* The bag-lines rule, as at the chest and on pickup —
                 * BEFORE the debit, or the units would be destroyed. */
                $capacity = new \App\Service\ContainerService();
                if ($capacity->stackNeedsRoom((int) $player->id, (int) $item->id)
                    && !$capacity->hasRoomForALine((int) $player->id)
                ) {
                    exit('Votre sac est plein.');
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

        /* L'écran du coffre en banque : le MÊME motif à deux volets que
         * le contenant (ExchangePanesView) — sac d'un côté, coffre de
         * l'autre, chaque ligne se déplace. Au guichet ($target), les
         * gestes postent sur api/bank/flows.php ; à distance
         * (inventory.php?bank), consultation seule. */
        $container = new \App\Service\ContainerService();
        $bank = new \App\Service\BankService();

        echo '<h1>Banque</h1>';
        echo '<sup>Votre Or en Banque augmente de ' . BANK_PCT . '% chaque jour passé sans combattre.</sup>';

        $atCounter = $target !== null;
        $bagGauge = ' (' . $container->lineCountOf((int) $player->id)
            . (($capacity = $container->capacityOf((int) $player->id)) !== null ? '/' . $capacity : '') . ')';

        \App\View\ExchangePanesView::openPanes();
        \App\View\ExchangePanesView::pane(
            'Sac' . $bagGauge,
            $container->contentsOf((int) $player->id),
            $atCounter ? 'deposit' : '',
            'Déposer →'
        );
        \App\View\ExchangePanesView::pane(
            'Coffre en banque',
            $bank->contentsOf((int) $player->id),
            $atCounter ? 'withdraw' : '',
            '← Retirer'
        );
        \App\View\ExchangePanesView::closePanes();

        if ($atCounter) {
            echo \App\View\ExchangePanesView::script(
                'api/bank/flows.php',
                ['targetId' => (int) $target->id],
                'load_merchant.php?targetId=' . (int) $target->id . '&bank',
                'Marchand'
            );
        } else {
            echo '<p><sup>Allez au guichet d\'une banque pour déposer ou retirer.</sup></p>';
        }
    }
}
