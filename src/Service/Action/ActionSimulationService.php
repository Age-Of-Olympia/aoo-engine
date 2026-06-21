<?php

namespace App\Service\Action;

use App\Action\ActionResults;
use App\Entity\Action;
use App\Entity\ActionPassive;
use App\Service\ActionExecutorService;
use App\Service\ActionPassiveService;
use App\Simulation\SimulatedItem;
use App\Simulation\SimulatedPlayer;
use App\Simulation\SimulationGuard;

/**
 * Previews an action EXACTLY like the live game: it runs the real
 * ActionExecutorService against DB-free SimulatedPlayers built from
 * hypothetical state, so all conditions, the opposed roll, damage, costs, XP,
 * messages and logs come from production code (no re-implementation).
 */
final class ActionSimulationService
{
    private ?ActionPassiveService $passiveService;

    public function __construct(?ActionPassiveService $passiveService = null)
    {
        $this->passiveService = $passiveService;
    }

    /**
     * One run → the real ActionResults the player would see.
     */
    public function simulate(Action $action, SimulationInput $input): ActionResults
    {
        $actor = $this->buildPlayer($input, isTarget: false, x: 0);
        $target = $this->buildPlayer($input, isTarget: true, x: $input->distance);

        // Conditions mutate the action's automatic outcome instructions on a miss
        // (e.g. ComputeCondition adds a MalusOutcomeInstruction). The same $action
        // is reused across every distribution() run, so snapshot the baseline and
        // restore it after each run — otherwise those instructions accumulate.
        $baseline = $action->getAutomaticOutcomeInstructions()->toArray();
        try {
            return SimulationGuard::run(
                fn() => (new ActionExecutorService($action, $actor, $target, simulationMode: true))->executeAction()
            );
        } finally {
            $instructions = $action->getAutomaticOutcomeInstructions();
            $instructions->clear();
            foreach ($baseline as $instruction) {
                $instructions->add($instruction);
            }
        }
    }

    /**
     * Run the simulation $runs times (rolls are random) and aggregate hit-rate /
     * average damage, keeping the first run as the detailed sample.
     */
    public function distribution(Action $action, SimulationInput $input, int $runs = 1000): SimulationReport
    {
        $runs = max(1, $runs);
        $successCount = 0;
        $hitCount = 0;
        $damageSum = 0;
        $sample = null;

        for ($i = 0; $i < $runs; $i++) {
            $results = $this->simulate($action, $input);
            if ($i === 0) {
                $sample = $results;
            }
            if ($results->isSuccess()) {
                $successCount++;
            }

            $damage = 0;
            foreach ($results->getOutcomesResultsArray() as $outcome) {
                $damage += (int) $outcome->getTotalDamages();
            }
            if ($damage > 0) {
                $hitCount++;
                $damageSum += $damage;
            }
        }

        return new SimulationReport(
            $runs,
            $successCount,
            $hitCount,
            $hitCount > 0 ? $damageSum / $hitCount : 0.0,
            $sample,
        );
    }

    private function buildPlayer(SimulationInput $input, bool $isTarget, int $x): SimulatedPlayer
    {
        $weapon = $isTarget ? $input->targetWeapon : $input->actorWeapon;

        return new SimulatedPlayer(
            $isTarget ? 2 : 1,
            $isTarget ? $input->targetCaracs : $input->actorCaracs,
            $isTarget ? $input->targetRemaining : $input->actorRemaining,
            (object) ['x' => $x, 'y' => 0, 'z' => 0, 'plan' => 'gaia'],
            ['name' => $isTarget ? 'Cible' : 'Acteur'],
            $this->emplacements($weapon),
            $isTarget ? $input->targetEffects : $input->actorEffects,
            $this->resolvePassives($isTarget ? $input->targetPassives : $input->actorPassives),
        );
    }

    /**
     * Resolve passive names from the form to their real ActionPassive configs
     * (skipping unknown names) so the simulated player computes real values.
     *
     * @param list<string> $names
     * @return list<ActionPassive>
     */
    private function resolvePassives(array $names): array
    {
        if ($names === []) {
            return [];
        }

        $service = $this->passiveService ??= new ActionPassiveService();
        $resolved = [];
        foreach ($names as $name) {
            $passive = $service->getActionPassiveByName((string) $name);
            if ($passive !== null) {
                $resolved[] = $passive;
            }
        }

        return $resolved;
    }

    private function emplacements(?string $weaponType): object
    {
        // A real player is never bare-handed: unarmed means the "Poing" (fist),
        // which the object-break path treats as unbreakable.
        $weapon = ($weaponType === null || $weaponType === '')
            ? new SimulatedItem('', 'Poing')
            : new SimulatedItem($weaponType, ucfirst($weaponType));

        return (object) ['main1' => $weapon];
    }
}
