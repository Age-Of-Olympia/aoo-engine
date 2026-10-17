<?php

namespace App\View\Merchant;

use App\Factory\PlayerFactory;
use App\Service\ContainerService;
use App\View\ExchangePanesView;
use Classes\Exchange;
use Classes\Player;

/**
 * Exchanges at a merchant: the open deals, a new deal, and the edit screen.
 * The edit screen is the bank's two-pane screen: each move applies at once.
 * Items are given from the bag (the player is at the counter); what is received goes to the bank.
 */
class ExchangesView
{
    public static function renderExchanges(Player $player, Player $target): void
    {
        echo '<h1>Échanges</h1>';

        if (isset($_GET['newExchange'])) {
            self::renderNew($target);
            return;
        }
        if (isset($_GET['editExchange'])) {
            self::renderEdit($player, $target, (int) $_GET['editExchange']);
            return;
        }
        self::renderList($player, $target);
    }

    private static function renderList(Player $player, Player $target): void
    {
        echo '<p><a href="merchant.php?targetId=' . (int) $target->id . '&exchanges&newExchange">'
            . '<button><span class="ra ra-scroll-unfurled"></span> Proposer un échange</button></a></p>';

        $exchanges = Exchange::get_open_exchanges($player->id);
        if ($exchanges === []) {
            echo '<p class="exchange-hint">Aucun échange en cours. Pour en proposer un, choisissez un personnage,'
                . ' puis les objets de votre sac à lui donner.</p>';
        }

        foreach ($exchanges as $exchange) {
            $other = self::otherParty($player, $exchange);
            $give = $exchange->contentsOf((int) $player->id);
            $receive = $exchange->contentsOf((int) $other->id);

            echo '<section class="exchange-deal">';
            self::renderHead($player, $other, $exchange, self::proposedBy($player, $other, $exchange));

            ExchangePanesView::openPanes();
            ExchangePanesView::pane('Vous donnez', $give, '', '');
            ExchangePanesView::pane(self::e($other->data->name) . ' vous donne', $receive, '', '');
            ExchangePanesView::closePanes();

            echo '<p class="exchange-status">' . self::status($player, $other, $exchange, self::isEmpty($give, $receive)) . '</p>';
            echo '<div class="exchange-actions">'
                . '<a href="merchant.php?targetId=' . (int) $target->id . '&exchanges&editExchange=' . (int) $exchange->id . '">'
                . '<button>Modifier mes objets</button></a>'
                . self::acceptButton($player, $exchange, self::isEmpty($give, $receive))
                . '<button class="exchange-cancel" ' . self::actionData('cancel', $player, $exchange)
                . ' data-confirm="' . self::e('Annuler l\'échange avec ' . $other->data->name . ' ? Vos objets reviennent dans votre sac, ceux de ' . $other->data->name . ' dans sa banque.') . '">'
                . 'Annuler l\'échange</button>'
                . '</div>';
            echo '</section>';
        }

        echo self::actionScript($target);
    }

    private static function renderNew(Player $target): void
    {
        ?>
        <section class="exchange-deal exchange-new">
            <h2>Proposer un échange</h2>
            <label for="autocomplete">Avec qui ?</label>
            <div class="exchange-recipient-row">
                <input id="autocomplete" type="text" placeholder="Nom du personnage" autocomplete="off">
                <span id="exchange-recipient" style="display:none"></span>
                <button type="button" id="exchange-change" style="display:none">Changer</button>
            </div>
            <p class="exchange-hint">Vous choisirez ensuite les objets à donner, parmi ceux de votre sac.</p>
            <button type="button" id="exchange-create" disabled><span class="ra ra-scroll-unfurled"></span> Créer l'échange</button>
        </section>
        <script src="js/autocomplete.js?v=20261017"></script>
        <script>
        $(function() {
            var recipient = '';

            function choose(name, label) {
                recipient = name;
                /* toggle(), not [hidden]: the HUD button style sets display */
                $('#autocomplete').val('').toggle(!name);
                $('#exchange-recipient').text(label).toggle(!!name);
                $('#exchange-change').toggle(!!name);
                $('#exchange-create').prop('disabled', !name);
                if (!name) {
                    $('#autocomplete').trigger('focus');
                }
            }

            bindAutocomplete(function(event, ui) {
                choose(ui.item.name, ui.item.label);
                /* Keep jQuery UI from writing the value back into the field */
                return false;
            });

            $('#exchange-change').on('click', function() { choose('', ''); });

            $('#exchange-create').on('click', function() {
                var $button = $(this).prop('disabled', true);
                $.ajax({
                    url: 'api/exchanges/exchanges-create.php?targetId=<?php echo (int) $target->id ?>',
                    method: 'POST',
                    dataType: 'json',
                    data: { recipient: recipient },
                    success: function(response) {
                        if (response.error) {
                            aooAlert(response.error);
                            $button.prop('disabled', false);
                            return;
                        }
                        aooGoto('merchant.php?exchanges&targetId=<?php echo (int) $target->id ?>&editExchange=' + response.result);
                    },
                    error: function() {
                        aooAlert('Erreur technique.');
                        $button.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }

    private static function renderEdit(Player $player, Player $target, int $exchangeId): void
    {
        $exchange = new Exchange($exchangeId);
        $exchange->get_base_data();
        $isParty = in_array((int) $player->id, [(int) ($exchange->playerId ?? 0), (int) ($exchange->targetId ?? 0)], true);
        if (!$exchange->is_in_progress() || !$isParty) {
            echo '<p class="exchange-hint">Cet échange est terminé ou annulé.</p>'
                . '<p><a href="merchant.php?targetId=' . (int) $target->id . '&exchanges"><button>Voir les échanges</button></a></p>';
            return;
        }
        $exchange->get_items_data();

        $other = self::otherParty($player, $exchange);
        $give = $exchange->contentsOf((int) $player->id);
        $receive = $exchange->contentsOf((int) $other->id);
        $name = self::e($other->data->name);

        echo '<section class="exchange-deal">';
        self::renderHead($player, $other, $exchange, self::proposedBy($player, $other, $exchange));
        echo '<p class="exchange-hint">Cliquez sur « Donner » ou « Reprendre » : l\'objet passe aussitôt de votre sac à l\'échange, ou revient.'
            . ' Ce que vous recevez arrive dans votre banque.</p>';

        ExchangePanesView::openPanes();
        $bag = new ContainerService();
        $capacity = $bag->capacityOf((int) $player->id);
        $gauge = ' (' . $bag->lineCountOf((int) $player->id) . ($capacity !== null ? '/' . $capacity : '') . ')';
        ExchangePanesView::pane('Votre sac' . $gauge, $bag->contentsOf((int) $player->id), 'give', 'Donner →');
        ExchangePanesView::pane('Vous donnez à ' . $name, $give, 'take', '← Reprendre');
        ExchangePanesView::closePanes();
        ExchangePanesView::pane($name . ' vous donne', $receive, '', '');

        echo '<p class="exchange-status">' . self::status($player, $other, $exchange, self::isEmpty($give, $receive))
            . ' Changer les objets retire les accords déjà donnés.</p>';
        echo '<div class="exchange-actions">'
            . self::acceptButton($player, $exchange, self::isEmpty($give, $receive))
            . '<a href="merchant.php?targetId=' . (int) $target->id . '&exchanges"><button>Voir tous les échanges</button></a>'
            . '</div>';
        echo '</section>';

        echo ExchangePanesView::script(
            'api/exchanges/exchanges-edit.php?targetId=' . (int) $target->id,
            ['id' => (int) $exchange->id, 'playerid' => (int) $player->id],
            'load_merchant.php?targetId=' . (int) $target->id . '&exchanges&editExchange=' . (int) $exchange->id,
            'Marchand'
        );
        echo self::actionScript($target);
    }

    private static function otherParty(Player $player, Exchange $exchange): Player
    {
        $other = PlayerFactory::legacy((int) ((int) $exchange->playerId === (int) $player->id ? $exchange->targetId : $exchange->playerId));
        $other->get_data();

        return $other;
    }

    private static function proposedBy(Player $player, Player $other, Exchange $exchange): string
    {
        return (int) $exchange->playerId === (int) $player->id ? 'Proposé par vous' : 'Proposé par ' . self::e($other->data->name);
    }

    /** Title, who proposed, and one wax seal per party: filled once that party has accepted. */
    private static function renderHead(Player $player, Player $other, Exchange $exchange, string $meta): void
    {
        [$myOk, $otherOk] = self::consents($player, $exchange);

        echo '<header class="exchange-deal-head"><div>'
            . '<h2>Avec <a href="infos.php?targetId=' . (int) $other->id . '">' . self::e($other->data->name) . '</a></h2>'
            . '<p class="exchange-hint">' . $meta . ', le ' . date('d/m/Y à H:i', (int) $exchange->updateTime) . '.</p>'
            . '</div><div class="exchange-seals">'
            . self::seal((string) $player->data->name, $myOk, $myOk ? 'Vous avez accepté' : 'Vous n\'avez pas encore accepté')
            . self::seal((string) $other->data->name, $otherOk, $other->data->name . ($otherOk ? ' a accepté' : ' n\'a pas encore accepté'))
            . '</div></header>';
    }

    /** @return array{bool, bool} [the viewer has accepted, the other party has accepted] */
    private static function consents(Player $player, Exchange $exchange): array
    {
        $mine = (int) $exchange->playerId === (int) $player->id;

        return $mine
            ? [$exchange->playerOk === 1, $exchange->targetOk === 1]
            : [$exchange->targetOk === 1, $exchange->playerOk === 1];
    }

    private static function seal(string $name, bool $sealed, string $title): string
    {
        return '<span class="exchange-seal' . ($sealed ? ' is-sealed' : '') . '" title="' . self::e($title) . '"'
            . ' role="img" aria-label="' . self::e($title) . '">' . self::e(mb_substr($name, 0, 1)) . '</span>';
    }

    private static function status(Player $player, Player $other, Exchange $exchange, bool $empty): string
    {
        [$myOk, $otherOk] = self::consents($player, $exchange);
        $name = self::e($other->data->name);

        return match (true) {
            $empty => 'L\'échange est vide : ajoutez des objets avant de l\'accepter.',
            $myOk => 'Vous avez accepté. L\'échange se fera dès que ' . $name . ' acceptera.',
            $otherOk => $name . ' a accepté. L\'échange se fera dès que vous accepterez.',
            default => 'L\'échange se fera quand vous et ' . $name . ' aurez accepté.',
        };
    }

    private static function acceptButton(Player $player, Exchange $exchange, bool $empty): string
    {
        if (self::consents($player, $exchange)[0]) {
            return '<button ' . self::actionData('refuse', $player, $exchange) . '>Retirer mon accord</button>';
        }

        return '<button ' . self::actionData('accept', $player, $exchange)
            . ' data-lastmodification="' . (int) $exchange->updateTime . '"' . ($empty ? ' disabled' : '') . '>'
            . '<span class="ra ra-quill-ink"></span> Accepter l\'échange</button>';
    }

    private static function actionData(string $action, Player $player, Exchange $exchange): string
    {
        return 'data-exchange-action="' . $action . '" data-id="' . (int) $exchange->id . '" data-playerid="' . (int) $player->id . '"';
    }

    /** @param array{stacks: list<mixed>, exemplars: list<mixed>} ...$contents */
    private static function isEmpty(array ...$contents): bool
    {
        foreach ($contents as $content) {
            if ($content['stacks'] !== [] || $content['exemplars'] !== []) {
                return false;
            }
        }

        return true;
    }

    /** Accept, withdraw consent, cancel: post, show the answer, back to the list. */
    private static function actionScript(Player $target): string
    {
        $endpoint = json_encode('api/exchanges/exchanges-edit.php?targetId=' . (int) $target->id);
        $listUrl = json_encode('load_merchant.php?targetId=' . (int) $target->id . '&exchanges');

        return <<<HTML
        <script>
        /* Namespaced and purged: the HUD re-runs this fragment on every panel load */
        $(document).off('click.exchangeAction').on('click.exchangeAction', '[data-exchange-action]', function(e) {
            e.preventDefault();
            var data = this.dataset;
            var payload = { action: data.exchangeAction, id: data.id, playerid: data.playerid, lastmodification: data.lastmodification };
            (data.confirm ? aooConfirm(data.confirm) : Promise.resolve(true)).then(function(ok) {
                if (!ok) {
                    return;
                }
                aooFetch({$endpoint}, payload).then(function(res) {
                    var message = res && (res.error || res.message);
                    (message ? aooAlert(message) : Promise.resolve()).then(function() {
                        aooPanelOrReload({$listUrl}, 'Marchand');
                    });
                }).catch(autoError());
            });
        });
        </script>
        HTML;
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
