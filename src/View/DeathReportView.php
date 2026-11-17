<?php

namespace App\View;

use App\Action\DeathReport;
use Classes\Player;

final class DeathReportView
{
    private const HEADLINES = [
        DeathReport::KILLED => 'Vous tuez votre adversaire.',
        DeathReport::DESTROYED => 'Vous détruisez la structure.',
        DeathReport::SUCCUMBED => 'Vous succombez à vos propres effets.',
    ];

    /** @param list<DeathReport> $reports */
    public static function render(array $reports, Player $viewer): string
    {
        ob_start();

        foreach ($reports as $report) {
            echo '<b><font color="red">' . self::HEADLINES[$report->outcome] . '</font></b>';

            if ($report->outcome === DeathReport::KILLED) {
                echo '<div class="action-details">'
                    . 'Partage de ' . $report->xpShared . 'Xp'
                    . ($report->xpShared === 0 && $report->victimInactive ? ' (joueur inactif)' : '') . ':<br />';
                foreach ($report->xpGains as $gain) {
                    echo htmlspecialchars($gain['name']) . ' +' . $gain['xp'] . 'Xp' . ($gain['bonus'] ? ' bonus' : '') . '<br />';
                }
                echo '</div>';
            }

            // The board the viewer sees no longer holds the dead.
            OnHideReloadView::render($viewer);
        }

        return (string) ob_get_clean();
    }
}
