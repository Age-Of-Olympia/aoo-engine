<?php

namespace App\Service\Action;

use App\Action\ActionResults;
use App\Entity\Action;
use App\Entity\ActionPassive;
use App\Service\ActionExecutorService;
use App\Service\ActionPassiveService;
use App\Simulation\DiceLog;
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
    private ?ActionTypeInstructionResolver $typeInstructionResolver;
    private ?ActionTypePreconditionResolver $preconditionResolver;
    private ?ConditionPreconditionResolver $conditionPreconditionResolver;
    private ?SimulationWeaponCatalog $weaponCatalog;

    public function __construct(
        ?ActionPassiveService $passiveService = null,
        ?ActionTypeInstructionResolver $typeInstructionResolver = null,
        ?ActionTypePreconditionResolver $preconditionResolver = null,
        ?ConditionPreconditionResolver $conditionPreconditionResolver = null,
        ?SimulationWeaponCatalog $weaponCatalog = null,
    ) {
        $this->passiveService = $passiveService;
        $this->typeInstructionResolver = $typeInstructionResolver;
        $this->preconditionResolver = $preconditionResolver;
        $this->conditionPreconditionResolver = $conditionPreconditionResolver;
        $this->weaponCatalog = $weaponCatalog;
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
                fn() => (new ActionExecutorService(
                    $action,
                    $actor,
                    $target,
                    simulationMode: true,
                    typeInstructionResolver: $this->typeInstructionResolver,
                    preconditionResolver: $this->preconditionResolver,
                    conditionPreconditionResolver: $this->conditionPreconditionResolver,
                ))->executeAction()
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
        $sampleRolls = [];

        for ($i = 0; $i < $runs; $i++) {
            // Record the dice of the first run only — it's the detailed sample.
            $recordSample = ($i === 0);
            if ($recordSample) {
                DiceLog::start();
            }
            try {
                $results = $this->simulate($action, $input);
            } finally {
                if ($recordSample) {
                    $sampleRolls = DiceLog::stop();
                }
            }
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
            $sampleRolls,
        );
    }

    private function buildPlayer(SimulationInput $input, bool $isTarget, int $x): SimulatedPlayer
    {
        $weapon = $isTarget ? $input->targetWeapon : $input->actorWeapon;

        // antiBerserkTime in the future makes the actor's NoBerserk precondition
        // fail; both fighters share the plan so the enfers gate reads it.
        $data = ['name' => $isTarget ? 'Cible' : 'Acteur'];
        if (!$isTarget && $input->actorBerserk) {
            $data['antiBerserkTime'] = time() + ONE_DAY;
        }

        return new SimulatedPlayer(
            $isTarget ? 2 : 1,
            $isTarget ? $input->targetCaracs : $input->actorCaracs,
            $isTarget ? $input->targetRemaining : $input->actorRemaining,
            (object) ['x' => $x, 'y' => 0, 'z' => 0, 'plan' => $input->plan],
            $data,
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

    private function emplacements(?string $weaponName): object
    {
        if ($weaponName === null || $weaponName === '') {
            // A real player is never bare-handed: unarmed means the "Poing" (fist),
            // which the object-break path treats as unbreakable.
            $weapon = new SimulatedItem('', 'Poing');
        } elseif ($this->weaponCatalog()->has($weaponName)) {
            // A real weapon: carry its data (spellMalus/subtype/…) so the
            // weapon-dependent conditions (AntiSpell, Dodge) read real values.
            $weapon = SimulatedItem::fromData($this->weaponCatalog()->dataFor($weaponName));
        } else {
            // Legacy: a bare subtype string (melee/tir/…).
            $weapon = new SimulatedItem($weaponName, ucfirst($weaponName));
        }

        return (object) ['main1' => $weapon];
    }

    private function weaponCatalog(): SimulationWeaponCatalog
    {
        return $this->weaponCatalog ??= new SimulationWeaponCatalog();
    }
}
