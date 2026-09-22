<?php

namespace App\View\Merchant;

use App\Factory\PlayerFactory;
use App\Service\ContainerService;
use App\Service\ItemInstanceService;
use App\Service\RepairService;
use App\View\ExchangePanesView;
use Classes\Player;

/**
 * The atelier's two counters on the panes pattern: one row per exemplar
 * of the bag, its state, the bill, and `.pane-move` buttons that
 * ExchangePanesView::script() posts as "exemplar-<direction>".
 */
final class AtelierView
{
    public static function render(Player $target, string $tab): void
    {
        $service = new RepairService();
        $playerId = (int) PlayerFactory::active()->id;
        $repair = $tab === 'repair';

        $rows = $repair ? $service->listRepairable($playerId) : $service->listBroken($playerId);

        echo '<h1>' . ($repair ? 'Réparation' : 'Recyclage') . '</h1>';
        echo '<sup>' . ($repair
            ? 'Remettre un objet à neuf coûte une part de sa recette, en ressources ou en or.'
            : 'Un objet brisé ne se répare plus : l\'artisan en récupère une part des matières.') . '</sup>';
        ExchangePanesView::openPanes();
        echo '<div class="exchange-pane"><h2>' . ($repair ? 'Objets usés' : 'Objets brisés') . '</h2>';

        if ($rows === []) {
            echo '<p><small>' . ($repair ? 'Rien à réparer' : 'Rien de brisé') . ' dans votre sac.</small></p>';
        } else {
            echo '<table border="1" class="marbre">';
            foreach ($rows as $row) {
                echo '<tr><td>' . ExchangePanesView::rowSprite((string) $row['name'])
                    . htmlspecialchars(ContainerService::exemplarEntryLabel($row), ENT_QUOTES, 'UTF-8')
                    . ' ' . ItemInstanceService::stateLine($row, withBreak: false) . '</td><td>'
                    . ($repair ? self::repairButtons($row) : self::recycleButton($row)) . '</td></tr>';
            }
            echo '</table>';
        }

        echo '</div>';
        ExchangePanesView::closePanes();
        echo ExchangePanesView::script(
            'api/atelier/flows.php',
            ['targetId' => (int) $target->id],
            'load_merchant.php?targetId=' . (int) $target->id . '&' . $tab,
            'Atelier'
        );
    }

    /** @param array<string, mixed> $row */
    private static function repairButtons(array $row): string
    {
        $quote = $row['quote'];
        if ($quote === null) {
            return '<small>Sans recette connue, l\'artisan ne sait pas réparer cela.</small>';
        }

        return self::button($row, 'repair-resources', self::stackList($quote['resources']))
            . ' ' . self::button($row, 'repair-gold', $quote['gold'] . ' PO');
    }

    /** @param array<string, mixed> $row */
    private static function recycleButton(array $row): string
    {
        return self::button($row, 'recycle', 'Recycler → ' . ($row['refund'] === [] ? 'rien' : self::stackList($row['refund'])));
    }

    /** @param array<string, mixed> $row */
    private static function button(array $row, string $direction, string $label): string
    {
        return '<button class="pane-move" data-kind="exemplar" data-direction="' . $direction . '"'
            . ' data-instance="' . (int) $row['instance_id'] . '">' . $label . '</button>';
    }

    /** @param array<string, int> $stacks name => count */
    private static function stackList(array $stacks): string
    {
        $parts = [];
        foreach ($stacks as $name => $count) {
            $parts[] = $count . ' ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        }

        return implode(' + ', $parts);
    }
}
