<?php

namespace Tests\View;

use App\Action\DeathReport;
use App\View\DeathReportView;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

class DeathReportViewTest extends LegacyPlayerFixtureTestCase
{
    public function testAKillTellsWhoEarnedWhat(): void
    {
        $viewer = $this->createRealPlayer('GmTemoin');
        $viewer->getCoords();

        $html = DeathReportView::render([new DeathReport(DeathReport::KILLED, 20, false, [
            ['name' => 'Dorna', 'xp' => 15, 'bonus' => false],
            ['name' => 'Thyrias', 'xp' => 5, 'bonus' => true],
        ])], $viewer);

        $this->assertStringContainsString('Vous tuez votre adversaire.', $html);
        $this->assertStringContainsString('Partage de 20Xp:<br />Dorna +15Xp<br />Thyrias +5Xp bonus<br />', $html);
    }

    public function testAnInactiveVictimSharesNothingAndSaysWhy(): void
    {
        $viewer = $this->createRealPlayer('GmTemoin2');
        $viewer->getCoords();

        $html = DeathReportView::render([new DeathReport(DeathReport::KILLED, 0, true)], $viewer);

        $this->assertStringContainsString('Partage de 0Xp (joueur inactif):', $html);
    }

    public function testNoDeathRendersNothing(): void
    {
        $this->assertSame('', DeathReportView::render([], $this->createRealPlayer('GmTemoin3')));
    }
}
