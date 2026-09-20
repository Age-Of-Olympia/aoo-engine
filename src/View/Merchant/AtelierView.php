<?php

namespace App\View\Merchant;

use App\Factory\PlayerFactory;
use App\Service\ContainerService;
use App\Service\ItemInstanceService;
use App\Service\RepairService;
use App\View\ExchangePanesView;
use Classes\Player;

/**
 * The atelier's two counters, on the panes pattern: one row per exemplar
 * of the bag, its state, the bill, and the gesture buttons. The buttons
 * are `.pane-move` so ExchangePanesView::script() posts them as it does
 * for the bank: action = "exemplar-<direction>".
 */
final class AtelierView
{
    public static function renderRepair(Player $target): void
    {
        $player = PlayerFactory::active();
        $rows = (new RepairService())->listRepairable((int) $player->id);

        echo '<h1>Réparation</h1>';
        echo '<sup>Remettre un objet à neuf coûte au plus un quart de sa recette, en ressources ou en or.</sup>';
        ExchangePanesView::openPanes();
        echo '<div class="exchange-pane"><h2>Objets usés</h2>';

        if ($rows === []) {
            echo '<p><small>Rien à réparer dans votre sac.</small></p></div>';
        } else {
            echo '<table border="1" class="marbre">';
            foreach ($rows as $row) {
                echo '<tr><td>' . ExchangePanesView::rowSprite((string) $row['name'])
                    . htmlspecialchars(ContainerService::exemplarEntryLabel($row), ENT_QUOTES, 'UTF-8')
                    . ' ' . ItemInstanceService::stateLine($row, withBreak: false) . '</td>';

                $quote = $row['quote'];
                if ($quote === null) {
                    echo '<td><small>Sans recette connue, l\'artisan ne sait pas réparer cela.</small></td></tr>';
                    continue;
                }

                $parts = [];
                foreach ($quote['resources'] as $name => $count) {
                    $parts[] = $count . ' ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                }
                $parts[] = $quote['labour'] . ' PO de main-d\'œuvre';

                echo '<td><button class="pane-move" data-kind="exemplar" data-direction="repair-resources"'
                    . ' data-instance="' . (int) $row['instance_id'] . '">' . implode(' + ', $parts) . '</button>'
                    . ' <button class="pane-move" data-kind="exemplar" data-direction="repair-gold"'
                    . ' data-instance="' . (int) $row['instance_id'] . '">' . (int) $quote['gold'] . ' PO</button></td></tr>';
            }
            echo '</table></div>';
        }

        ExchangePanesView::closePanes();
        echo self::script($target, 'repair');
    }

    public static function renderRecycle(Player $target): void
    {
        $player = PlayerFactory::active();
        $rows = (new RepairService())->listBroken((int) $player->id);

        echo '<h1>Recyclage</h1>';
        echo '<sup>Un objet brisé ne se répare plus : l\'artisan en récupère un quart des matières.</sup>';
        ExchangePanesView::openPanes();
        echo '<div class="exchange-pane"><h2>Objets brisés</h2>';

        if ($rows === []) {
            echo '<p><small>Rien de brisé dans votre sac.</small></p></div>';
        } else {
            echo '<table border="1" class="marbre">';
            foreach ($rows as $row) {
                $parts = [];
                foreach ($row['refund'] as $name => $count) {
                    $parts[] = $count . ' ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                }
                echo '<tr><td>' . ExchangePanesView::rowSprite((string) $row['name'])
                    . htmlspecialchars(ContainerService::exemplarEntryLabel($row), ENT_QUOTES, 'UTF-8')
                    . ' ' . ItemInstanceService::stateLine($row, withBreak: false) . '</td>'
                    . '<td><button class="pane-move" data-kind="exemplar" data-direction="recycle"'
                    . ' data-instance="' . (int) $row['instance_id'] . '">Recycler → '
                    . ($parts === [] ? 'rien' : implode(' + ', $parts)) . '</button></td></tr>';
            }
            echo '</table></div>';
        }

        ExchangePanesView::closePanes();
        echo self::script($target, 'recycle');
    }

    private static function script(Player $target, string $tab): string
    {
        return ExchangePanesView::script(
            'api/atelier/flows.php',
            ['targetId' => (int) $target->id],
            'load_merchant.php?targetId=' . (int) $target->id . '&' . $tab,
            'Atelier'
        );
    }
}
