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
use Classes\Item;

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
    private ?ActionLogResolver $logResolver;
    private ?ActionXpResolver $xpResolver;

    public function __construct(
        ?ActionPassiveService $passiveService = null,
        ?ActionTypeInstructionResolver $typeInstructionResolver = null,
        ?ActionTypePreconditionResolver $preconditionResolver = null,
        ?ConditionPreconditionResolver $conditionPreconditionResolver = null,
        ?SimulationWeaponCatalog $weaponCatalog = null,
        ?ActionLogResolver $logResolver = null,
        ?ActionXpResolver $xpResolver = null,
    ) {
        $this->passiveService = $passiveService;
        $this->typeInstructionResolver = $typeInstructionResolver;
        $this->preconditionResolver = $preconditionResolver;
        $this->conditionPreconditionResolver = $conditionPreconditionResolver;
        $this->weaponCatalog = $weaponCatalog;
        $this->logResolver = $logResolver;
        $this->xpResolver = $xpResolver;
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
                    logResolver: $this->logResolver,
                    xpResolver: $this->xpResolver,
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
        $equipment = $isTarget ? $input->targetEquipment : $input->actorEquipment;

        // antiBerserkTime in the future makes the actor's NoBerserk precondition
        // fail; both fighters share the plan so the enfers gate reads it.
        $data = [
            'name' => $isTarget ? 'Cible' : 'Acteur',
            'rank' => $isTarget ? $input->targetRank : $input->actorRank,
            'energie' => $isTarget ? $input->targetEnergie : $input->actorEnergie,
        ];
        if (!$isTarget && $input->actorBerserk) {
            $data['antiBerserkTime'] = time() + ONE_DAY;
        }

        return new SimulatedPlayer(
            $isTarget ? 2 : 1,
            $isTarget ? $input->targetCaracs : $input->actorCaracs,
            $isTarget ? $input->targetRemaining : $input->actorRemaining,
            (object) ['x' => $x, 'y' => 0, 'z' => 0, 'plan' => $input->plan],
            $data,
            $this->emplacements($weapon, $equipment),
            $isTarget ? $input->targetEffects : $input->actorEffects,
            $this->resolvePassives($isTarget ? $input->targetPassives : $input->actorPassives),
            // Tile types are the actor's location; the target's tile is irrelevant.
            $isTarget ? [] : $input->tileTypes,
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

    /**
     * @param array<string, string> $equipment slot (emplacement) => item name
     */
    private function emplacements(?string $weaponName, array $equipment = []): object
    {
        if ($weaponName === null || $weaponName === '') {
            // A real player is never bare-handed: unarmed means the "Poing" (fist),
            // a real melee item — load its real data so it carries subtype 'melee'
            // (weapon-type conditions pass) while the object-break path treats it
            // as unbreakable. Fall back to a bare melee item if the datas lack it.
            $poing = $this->weaponCatalog()->dataFor('poing');
            $weapon = $poing !== null
                ? SimulatedItem::fromData($poing)
                : new SimulatedItem('melee', 'Poing');
        } elseif ($this->weaponCatalog()->has($weaponName)) {
            // A real weapon: carry its data (spellMalus/subtype/…) so the
            // weapon-dependent conditions (AntiSpell, Dodge) read real values.
            $weapon = SimulatedItem::fromData($this->weaponCatalog()->dataFor($weaponName));
        } else {
            // Legacy: a bare subtype string (melee/tir/…).
            $weapon = new SimulatedItem($weaponName, ucfirst($weaponName));
        }

        $emplacements = (object) ['main1' => $weapon];

        // Other equipped slots (helmet, ring, armour, shield, …): their stats
        // fold into caracs and their properties (e.g. a helmet's spellMalus) feed
        // the conditions, the same way the weapon does.
        foreach ($equipment as $slot => $name) {
            if ($slot !== 'main1' && $this->weaponCatalog()->has($name)) {
                $emplacements->{$slot} = SimulatedItem::fromData($this->weaponCatalog()->dataFor($name));
            }
        }

        return $this->applyEquipLimit($emplacements, $weaponName);
    }

    /**
     * Enforce the game's equip rule on a built loadout: at most ITEM_LIMIT real
     * items across the normal slots (in slot order), while the ring/munition/
     * trophee slots are kept on top of the cap. The bare-handed "Poing" is not a
     * real equipped item, so it never counts. Uses Item::countsTowardEquipLimit
     * so the simulator follows the exact same rule as the game.
     */
    private function applyEquipLimit(object $emplacements, ?string $weaponName): object
    {
        $weaponIsReal = $weaponName !== null && $weaponName !== '' && $this->weaponCatalog()->has($weaponName);
        $order = defined('ITEM_EMPLACEMENT_FORMAT') ? ITEM_EMPLACEMENT_FORMAT : array_keys((array) $emplacements);
        $limit = defined('ITEM_LIMIT') ? ITEM_LIMIT : 3;

        $kept = (object) [];
        $normalCount = 0;
        foreach ($order as $slot) {
            if (!isset($emplacements->{$slot})) {
                continue;
            }
            // Exempt slots (ring/munition/trophee) and the non-real main-hand
            // (Poing/legacy type) are always kept and don't count toward the cap.
            if (!Item::countsTowardEquipLimit($slot) || ($slot === 'main1' && !$weaponIsReal)) {
                $kept->{$slot} = $emplacements->{$slot};
                continue;
            }
            if ($normalCount < $limit) {
                $kept->{$slot} = $emplacements->{$slot};
                $normalCount++;
            }
        }

        return $kept;
    }

    private function weaponCatalog(): SimulationWeaponCatalog
    {
        return $this->weaponCatalog ??= new SimulationWeaponCatalog();
    }
}
