<?php

namespace App\View;

use App\Factory\PlayerFactory;
use App\Service\ContainerService;
use App\Service\PlayerExchangeService;
use Classes\Exchange;
use Classes\Player;

/** Character-to-character exchange screen. */
final class PlayerExchangeView
{
    public static function render(Player $player, Player $target): void
    {
        (new PlayerExchangeService())->assertContact($player, $target);
        echo '<h1>Donner à ' . self::e((string) $target->data->name) . '</h1>';
        $exchangeId = (int) ($_GET['exchangeId'] ?? 0);
        if ($exchangeId === 0) {
            $found = null;
            foreach (Exchange::get_open_exchanges((int) $player->id) as $candidate) {
                if ($candidate->exchangeType === 'player'
                    && in_array((int) $target->id, [(int) $candidate->playerId, (int) $candidate->targetId], true)) {
                    $found = $candidate;
                    break;
                }
            }
            if ($found !== null) {
                echo '<p><a href="give.php?targetId=' . (int) $target->id . '&exchangeId=' . (int) $found->id . '"><button>Reprendre l’échange en cours</button></a></p>';
            } else {
                echo '<p>Choisissez des objets dans votre sac, puis validez la proposition. ' . self::e((string) $target->data->name) . ' pourra l’accepter, ajouter des objets ou la refuser.</p>';
                echo '<button type="button" id="player-exchange-create">Commencer l’échange</button>';
                echo self::createScript((int) $target->id);
            }
            return;
        }

        $exchange = new Exchange($exchangeId);
        $exchange->get_base_data();
        if (!$exchange->is_in_progress() || $exchange->exchangeType !== 'player'
            || !in_array((int) $player->id, [(int) $exchange->playerId, (int) $exchange->targetId], true)
            || !in_array((int) $target->id, [(int) $exchange->playerId, (int) $exchange->targetId], true)) {
            echo '<p>Cet échange est terminé ou indisponible.</p>';
            return;
        }
        $exchange->get_items_data();
        $other = PlayerFactory::legacy((int) ($exchange->playerId === $player->id ? $exchange->targetId : $exchange->playerId));
        $other->get_data();
        $mine = $exchange->contentsOf((int) $player->id);
        $theirs = $exchange->contentsOf((int) $other->id);
        $empty = $mine['stacks'] === [] && $mine['exemplars'] === [] && $theirs['stacks'] === [] && $theirs['exemplars'] === [];
        $myAccepted = (int) ($exchange->playerId === $player->id ? $exchange->playerOk : $exchange->targetOk) === 1;
        $rules = new PlayerExchangeService();
        $canReceive = $rules->canReceive($exchange, $player);
        $bag = new ContainerService();
        $capacity = $bag->capacityOf((int) $player->id);
        $bagLabel = 'Votre sac (' . $bag->lineCountOf((int) $player->id) . ($capacity === null ? '' : '/' . $capacity) . ')';

        echo '<section class="exchange-deal"><header class="exchange-deal-head"><h2>Échange avec ' . self::e((string) $other->data->name) . '</h2>'
            . '<p>' . ((int) $exchange->playerOk === 1 ? 'Proposition validée' : 'Proposition à valider') . ' · '
            . ((int) $exchange->targetOk === 1 ? 'Réponse validée' : 'En attente de réponse') . '</p></header>';
        echo '<p>Les objets restent dans l’échange jusqu’à l’accord des deux personnages. Toute modification demande une nouvelle validation.</p>';
        ExchangePanesView::openPanes();
        ExchangePanesView::pane($bagLabel, $bag->contentsOf((int) $player->id), 'give', 'Donner →');
        ExchangePanesView::pane('Vous proposez', $mine, 'take', '← Reprendre');
        ExchangePanesView::closePanes();
        ExchangePanesView::pane(self::e((string) $other->data->name) . ' propose', $theirs, '', '');
        echo '<div class="exchange-actions"><button data-player-exchange="accept" data-lastmodification="' . (int) $exchange->updateTime . '"'
            . ($empty || !$canReceive ? ' disabled title="' . ($empty ? 'Ajoutez au moins un objet.' : 'Cet échange dépasse la taille du sac') . '"' : '')
            . ($myAccepted ? ' style="font-weight: bold;"' : '') . '>'
            . ($myAccepted ? 'Valider de nouveau' : 'Valider l’échange') . '</button>'
            . '<button data-player-exchange="refuse">Refuser l’échange</button></div></section>';

        $endpoint = json_encode('api/exchanges/player.php?targetId=' . (int) $target->id);
        $refresh = json_encode('give.php?targetId=' . (int) $target->id . '&exchangeId=' . (int) $exchange->id);
        echo ExchangePanesView::script(
            'api/exchanges/player.php?targetId=' . (int) $target->id,
            ['id' => (int) $exchange->id],
            'load_give.php?targetId=' . (int) $target->id . '&exchangeId=' . (int) $exchange->id,
            'Donner'
        );
        echo <<<HTML
        <script>
        (function(){
            var endpoint = {$endpoint};
            var refresh = {$refresh};
            $(document).off('click.playerExchange').on('click.playerExchange', '[data-player-exchange]', function(){
                var button = this;
                aooFetch(endpoint, {action: button.dataset.playerExchange, id: {$exchangeId}, lastmodification: button.dataset.lastmodification})
                    .then(function(response){
                        var message = response && (response.error || response.message);
                        (message ? aooAlert(message) : Promise.resolve()).then(function(){ aooGoto(refresh); });
                    }).catch(autoError());
            });
        })();
        </script>
        HTML;
    }

    private static function createScript(int $targetId): string
    {
        $endpoint = json_encode('api/exchanges/player.php?targetId=' . $targetId);
        $url = json_encode('give.php?targetId=' . $targetId);
        return <<<HTML
        <script>
        $(document).off('click.playerExchangeCreate').on('click.playerExchangeCreate', '#player-exchange-create', function(){
            aooFetch({$endpoint}, {action: 'create'}).then(function(response){
                if (response && response.error) { aooAlert(response.error); return; }
                aooGoto({$url} + '&exchangeId=' + response.result);
            }).catch(autoError());
        });
        </script>
        HTML;
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
