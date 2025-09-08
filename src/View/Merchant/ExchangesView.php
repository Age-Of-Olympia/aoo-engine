<?php

namespace App\View\Merchant;

use Classes\Exchange;
use Classes\Player;
use Classes\Market;


class ExchangesView
{
    public static function renderExchanges(Player $player, Market $market, Player $target): void
    {

        echo '<h1>Echanges</h1>';

        if (isset($_GET['newExchange'])) {

            include('scripts/merchant/new_exchange.php');

            exit();
        }
        if (isset($_GET['editExchange'])) {

            include('scripts/merchant/edit_exchange.php');

            exit();
        }

        echo '<div>Pour échanger des objets avec d\'autres personnages par le biais des marchands, c\'est ici. </div>';


?>


        <div class="section">
            <div class="section-title">Echanges en cours</div>

            <?php

            $exchanges = Exchange::get_open_exchanges($player->id);
            foreach ($exchanges as $exchange) {
                if ($exchange->playerId != $player->id) {
                    $fromPlayer = new Player($exchange->playerId);
                    $fromPlayer->get_data();
                    echo '<section style="background-color: #f0f8ff5e; margin-top:10px;">';
                    echo 'Echange reçu de <a href="infos.php?targetId=' . $fromPlayer->id . '">' . $fromPlayer->data->name . '(' . $fromPlayer->id . ')</a> le ' . date('d/m/Y H:i', $exchange->updateTime) . '. 
              <br> L\'échange sera validé quand les deux joueurs auront accepté.<br>';
                    if ($exchange->playerOk == 1) {
                        echo $fromPlayer->data->name . ' a accepté<br>';
                    }
                    if ($exchange->targetOk == 1) {
                        echo 'Vous avez accepté. => <a class="action" href="#" data-url="api/exchanges/exchanges-edit.php?targetId=' . $target->id . '" data-action="refuse" data-id="' . $exchange->id . '" data-playerid="' . $player->id . '">Refuser</a> ( n\'annule pas l\'échange)<br>';
                    } else
                        echo '<a class="action" href="#" data-url="api/exchanges/exchanges-edit.php?targetId=' . $target->id . '" data-action="accept" data-id="' . $exchange->id . '" data-lastModification="' . $exchange->updateTime . '" data-playerid="' . $player->id . '">Accepter l\'échange</a><br>';
                    echo '<a class="action" href="#" data-url="api/exchanges/exchanges-edit.php?targetId=' . $target->id . '" data-action="cancel" data-id="' . $exchange->id . '" data-playerid="' . $player->id . '" >Annuler ( supprimer )</a><br>';
                    echo '<a href="merchant.php?targetId=' . $target->id . '&exchanges&editExchange=' . $exchange->id . '">Modifier</a> <br>';
                    echo '<ul class="compact-list">
              <li style="font-weight: bold;">Vous recevez : </li>';
                    echo $exchange->render_items_for_player($exchange->playerId);
                    echo '<li style="font-weight: bold;">Vous donnez : </li>';
                    echo $exchange->render_items_for_player($exchange->targetId);
                    echo '</ul> <br/>';
                    echo '</section>';
                }
            }
            echo '<br/>';
            foreach ($exchanges as $exchange) {
                if ($exchange->playerId == $player->id) {
                    echo '<section style="background-color: #f0f8ff5e; margin-top:10px;">';
                    $targetPlayer = new Player($exchange->targetId);
                    $targetPlayer->get_data();
                    echo 'Echange proposé à <a href="infos.php?targetId=' . $targetPlayer->id . '">' . $targetPlayer->data->name . '(' . $targetPlayer->id . ')</a> le ' . date('d/m/Y H:i', $exchange->updateTime) . '.
                <br> L\'échange sera validé quand les deux joueurs auront accepté.<br>';

                    if ($exchange->targetOk == 1) {
                        echo $targetPlayer->data->name . ' a accepté<br>';
                    }
                    if ($exchange->playerOk == 1) {
                        echo 'Vous avez accepté. => <a class="action" href="#" data-url="api/exchanges/exchanges-edit.php?targetId=' . $target->id . '" data-action="refuse" data-id="' . $exchange->id . '" data-playerid="' . $player->id . '">Refuser</a> ( n\'annule pas l\'échange) <br>';
                    } else
                        echo '<a class="action" href="#" data-url="api/exchanges/exchanges-edit.php?targetId=' . $target->id . '" data-action="accept" data-id="' . $exchange->id . '" data-playerid="' . $player->id . '" data-lastModification="' . $exchange->updateTime . '" >Accepter l\'échange</a> <br>';

                    echo '<a class="action" href="#" data-url="api/exchanges/exchanges-edit.php?targetId=' . $target->id . '" data-action="cancel" data-id="' . $exchange->id . '" data-playerid="' . $player->id . '">Annuler ( supprimer )</a><br>';
                    echo '<a href="merchant.php?targetId=' . $target->id . '&exchanges&editExchange=' . $exchange->id . '">Modifier</a>  <br>';
                    echo '<br><ul class="compact-list">
                <li style="font-weight: bold;">Vous recevez : </li>';
                    echo $exchange->render_items_for_player($exchange->targetId);
                    echo '<li style="font-weight: bold;">Vous donnez : </li>';
                    echo $exchange->render_items_for_player($exchange->playerId);
                    echo '</ul> <br/>';
                    echo '</section>';
                }
            }
            ?>

        </div>
        <div class="button-container">
            <a href="merchant.php?targetId=<?php echo $target->id ?>&exchanges&newExchange">
                <button class="exchange-button"><span class="ra ra-scroll-unfurled"></span> Nouvel échange</button>
            </a>
        </div>

        </div>
        <script>
            $('.action').click(function(e) {
                e.preventDefault();
                let elem = e.currentTarget;
                let url = elem.dataset.url;
                const dataset = elem.dataset;
                const payload = {
                    ...dataset
                };
                delete payload.url;


                aooFetch(url, payload, null)
                    .then(data => {
                        if (data.error) {
                            alert(data.error);
                        } else if (data.message) {
                            alert(data.message);
                        }
                        //console.log('Success:', data);
                        location.reload();
                    })
                    .catch((error) => {
                        console.error('Error:', error);
                        location.reload();
                    });
            });
        </script>
<?php
    }
}
