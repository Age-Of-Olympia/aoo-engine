<?php

namespace App\View\Action;

use App\Entity\Action;
use App\Service\Action\ActionSimulationService;
use App\Service\Action\SimulationFormBuilder;
use App\Service\Action\SimulationInputMapper;
use Throwable;

/**
 * The simulator panel: the hypothetical-state form, and — on submit — the result
 * report. Extracted so action-simulate.php and the workbench's "Simuler" tab
 * share one implementation instead of each wiring the builder / mapper / service
 * / report views by hand.
 */
final class SimulationPanelView
{
    private ActionSimulationService $service;
    private SimulationFormBuilder $formBuilder;
    private SimulationInputMapper $mapper;

    public function __construct(
        ?ActionSimulationService $service = null,
        ?SimulationFormBuilder $formBuilder = null,
        ?SimulationInputMapper $mapper = null,
    ) {
        $this->service = $service ?? new ActionSimulationService();
        $this->formBuilder = $formBuilder ?? new SimulationFormBuilder();
        $this->mapper = $mapper ?? new SimulationInputMapper();
    }

    /**
     * @param array<string, mixed> $posted
     */
    public function form(Action $action, array $posted): string
    {
        return (new SimulationFormView())->render($action, $this->formBuilder->fieldsFor($action), $posted);
    }

    /**
     * Run the simulation and render the report. Returns a friendly notice when the
     * action can't be fully simulated (it depends on real world state).
     *
     * @param array<string, mixed> $posted
     */
    public function result(Action $action, array $posted): string
    {
        try {
            $report = $this->service->distribution($action, $this->mapper->fromPost($posted), $this->mapper->runs($posted));

            return (new SimulationReportView($report))->render();
        } catch (Throwable $e) {
            return SimulationReportView::unavailable($e->getMessage());
        }
    }
}
