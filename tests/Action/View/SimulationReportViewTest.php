<?php

namespace Tests\Action\View;

use App\Action\ActionResults;
use App\Service\Action\SimulationReport;
use App\View\Action\SimulationReportView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class SimulationReportViewTest extends TestCase
{
    private function sample(): ActionResults
    {
        return new ActionResults(true, false, [], [], [], [], ['actor' => '', 'target' => '']);
    }

    public function testRendersTheDiceBreakdownWhenRollsAreCaptured(): void
    {
        $report = new SimulationReport(10, 5, 4, 2.0, $this->sample(), [
            ['sides' => 3, 'faces' => [2, 3, 1]],
            ['sides' => 3, 'faces' => [1, 1]],
        ]);

        $html = (new SimulationReportView($report))->render();

        $this->assertStringContainsString('Calcul des dés', $html);
        $this->assertStringContainsString('3d3', $html);
        $this->assertStringContainsString('[2, 3, 1] = <strong>6</strong>', $html);
        $this->assertStringContainsString('[1, 1] = <strong>2</strong>', $html);
    }

    public function testOmitsTheDiceBreakdownWhenNoRollsWereCaptured(): void
    {
        $report = new SimulationReport(10, 5, 4, 2.0, $this->sample(), []);

        $this->assertStringNotContainsString('Calcul des dés', (new SimulationReportView($report))->render());
    }
}
