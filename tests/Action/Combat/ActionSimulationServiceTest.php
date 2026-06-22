<?php

namespace Tests\Action\Combat;

use App\Action\ActionResults;
use App\Action\Condition\DistanceComputeCondition;
use App\Action\Condition\MeleeComputeCondition;
use App\Action\BuffAction;
use App\Action\MeleeAction;
use App\Action\OutcomeInstruction\LifeLossOutcomeInstruction;
use App\Entity\ActionCondition;
use App\Entity\ActionConditionPrecondition;
use App\Entity\ActionTypeInstruction;
use App\Entity\ActionTypePrecondition;
use App\Service\Action\ActionSimulationService;
use App\Service\Action\ActionTypeInstructionResolver;
use App\Service\Action\ActionTypePreconditionResolver;
use App\Service\Action\ConditionPreconditionResolver;
use App\Service\Action\SimulationInput;
use App\Service\Action\SimulationWeaponCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-combat')]
class ActionSimulationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        // Constants the combat path reads, normally provided by config/constants.php
        // (loaded by config.php in the web context, absent under the test bootstrap).
        // CARACS mirrors the real key set: it is a process-global constant, so a
        // partial stub would corrupt any later test that builds a player from it.
        $constants = [
            'AUTO_FAIL' => false,
            'DMG_CRIT' => 5,
            'ACTION_XP' => 5,
            'ONE_DAY' => 86400,
            'CARACS' => [
                'a' => 'A', 'mvt' => 'Mvt', 'p' => 'P', 'pv' => 'PV', 'cc' => 'CC',
                'ct' => 'CT', 'f' => 'F', 'e' => 'E', 'agi' => 'Agi', 'pm' => 'PM',
                'fm' => 'FM', 'm' => 'M', 'r' => 'R', 'rm' => 'RM', 'spd' => 'Spd', 'ae' => 'Ae',
            ],
        ];
        foreach ($constants as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }

    /**
     * The sim service with a DB-free type-instruction resolver (no type-level
     * instructions configured), so these tests exercise the legacy automatic
     * path without hitting the database.
     */
    /**
     * @param array<int, \App\Entity\ActionTypeInstruction> $typeConfigs
     */
    private function simulationService(array $typeConfigs = []): ActionSimulationService
    {
        $instructionRepo = $this->createMock(EntityRepository::class);
        $instructionRepo->method('findBy')->willReturn($typeConfigs);
        $emptyRepo = $this->createMock(EntityRepository::class);
        $emptyRepo->method('findBy')->willReturn([]); // no global/type/condition preconditions in combat sims

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class): EntityRepository => in_array($class, [ActionTypePrecondition::class, ActionConditionPrecondition::class], true)
                ? $emptyRepo
                : $instructionRepo
        );

        return new ActionSimulationService(
            typeInstructionResolver: new ActionTypeInstructionResolver($em),
            preconditionResolver: new ActionTypePreconditionResolver($em),
            conditionPreconditionResolver: new ConditionPreconditionResolver($em),
            weaponCatalog: new SimulationWeaponCatalog([]), // no real datas in the test env
        );
    }

    /**
     * @param array<int, ActionTypePrecondition>      $globalPreconditions
     * @param array<int, ActionConditionPrecondition> $conditionPreconditions
     * @param array<string, object>                   $weapons
     */
    private function simulationServiceWith(array $globalPreconditions = [], array $conditionPreconditions = [], array $weapons = []): ActionSimulationService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(function (string $class) use ($globalPreconditions, $conditionPreconditions): EntityRepository {
            $rows = match ($class) {
                ActionTypePrecondition::class => $globalPreconditions,
                ActionConditionPrecondition::class => $conditionPreconditions,
                default => [],
            };
            $repo = $this->createMock(EntityRepository::class);
            $repo->method('findBy')->willReturn($rows);

            return $repo;
        });

        return new ActionSimulationService(
            typeInstructionResolver: new ActionTypeInstructionResolver($em),
            preconditionResolver: new ActionTypePreconditionResolver($em),
            conditionPreconditionResolver: new ConditionPreconditionResolver($em),
            weaponCatalog: new SimulationWeaponCatalog($weapons),
        );
    }

    /* --- the per-type defense formulas (kept; used by the real conditions) --- */

    public function testMeleeDefenseUsesMaxOfCcAndAgi(): void
    {
        $this->assertSame(8, MeleeComputeCondition::targetDefenseValue(5, 8));
    }

    public function testDistanceDefenseUsesTheWeightedBlend(): void
    {
        // floor(max(3/4*8 + 1/4*4, 1/4*8 + 3/4*4)) = floor(max(7, 5)) = 7
        $this->assertSame(7, DistanceComputeCondition::targetDefenseValue(8, 4));
    }

    public function testDistanceMalusAndThresholdFormulas(): void
    {
        $this->assertSame(0, DistanceComputeCondition::distanceMalusFor(2));
        $this->assertSame(3, DistanceComputeCondition::distanceMalusFor(4));
        $this->assertSame(10, DistanceComputeCondition::distanceThresholdFor(4));
    }

    /* --- the real engine, driven through SimulatedPlayer (the fix) --- */

    private function meleeWith(ActionCondition ...$conditions): MeleeAction
    {
        $action = new MeleeAction();
        $action->setName('melee');
        foreach ($conditions as $condition) {
            $action->addCondition($condition);
        }

        return $action;
    }

    private function condition(string $type, array $params): ActionCondition
    {
        $condition = new ActionCondition();
        $condition->setConditionType($type);
        $condition->setParameters($params);
        $condition->setBlocking(true);
        $condition->setExecutionOrder(0);

        return $condition;
    }

    public function testEquipmentOnAnySlotFeedsTheConditions(): void
    {
        // A helmet (tete slot) with spellMalus must fire AntiSpell, just like a
        // weapon would — proving non-main-hand equipment is really equipped.
        $action = $this->meleeWith($this->condition('SpellCompute', ['actorRollType' => 'cc', 'targetRollType' => 'cc']));
        $input = new SimulationInput(actorCaracs: ['cc' => 10], targetCaracs: ['cc' => 1], actorEquipment: ['tete' => 'casque']);

        $antiSpell = (new ActionConditionPrecondition())->setParentConditionType('SpellCompute')
            ->setPreconditionType('AntiSpell')->setOrderIndex(0);
        $casque = ['casque' => (object) ['type' => 'equipement', 'emplacement' => 'tete', 'subtype' => 'casque', 'name' => 'Casque', 'spellMalus' => 1]];

        $results = $this->simulationServiceWith(conditionPreconditions: [$antiSpell], weapons: $casque)->simulate($action, $input);

        $this->assertTrue($results->isBlocked());
    }

    public function testTheEquipLimitExemptsRingMunitionAndTrophy(): void
    {
        $this->assertTrue(\Classes\Item::countsTowardEquipLimit('main1'));
        $this->assertTrue(\Classes\Item::countsTowardEquipLimit('tete'));
        $this->assertFalse(\Classes\Item::countsTowardEquipLimit('doigt'));
        $this->assertFalse(\Classes\Item::countsTowardEquipLimit('munition'));
        $this->assertFalse(\Classes\Item::countsTowardEquipLimit('trophee'));
    }

    public function testEquipLimitDropsNormalItemsBeyondTheCap(): void
    {
        // gladius(main1) + bouclier(main2) + lame(deuxmains) = 3 normal items =
        // the cap, so the casque (tete, 4th normal) is NOT equipped — its
        // spellMalus must therefore NOT fire AntiSpell. BuffAction has no auto
        // outcomes, so a successful roll touches nothing.
        $action = new BuffAction();
        $action->setName('sort');
        $action->setDisplayName('Sort');
        $action->addCondition($this->condition('SpellCompute', ['actorRollType' => 'cc', 'targetRollType' => 'cc']));

        $input = new SimulationInput(
            actorCaracs: ['cc' => 30],
            targetCaracs: ['cc' => 1],
            actorWeapon: 'gladius',
            actorEquipment: ['main2' => 'bouclier', 'deuxmains' => 'lame', 'tete' => 'casque'],
        );
        $antiSpell = (new ActionConditionPrecondition())->setParentConditionType('SpellCompute')
            ->setPreconditionType('AntiSpell')->setOrderIndex(0);
        $weapons = [
            'gladius' => (object) ['type' => 'equipement', 'emplacement' => 'main1', 'subtype' => 'melee', 'name' => 'Gladius'],
            'bouclier' => (object) ['type' => 'equipement', 'emplacement' => 'main2', 'subtype' => 'bouclier', 'name' => 'Bouclier'],
            'lame' => (object) ['type' => 'equipement', 'emplacement' => 'deuxmains', 'subtype' => 'melee', 'name' => 'Lame'],
            'casque' => (object) ['type' => 'equipement', 'emplacement' => 'tete', 'subtype' => 'casque', 'name' => 'Casque', 'spellMalus' => 1],
        ];

        $results = $this->simulationServiceWith(conditionPreconditions: [$antiSpell], weapons: $weapons)->simulate($action, $input);

        $this->assertStringNotContainsString('magie', $this->failureText($results));
    }

    public function testTheEnfersToggleBlocksTheAction(): void
    {
        $action = $this->meleeWith($this->condition('MeleeCompute', ['actorRollType' => 'cc', 'targetRollType' => 'cc']));
        $input = new SimulationInput(actorCaracs: ['cc' => 10], targetCaracs: ['cc' => 1], actorWeapon: 'melee', plan: 'enfers');

        $globalPlan = (new ActionTypePrecondition())->setTypeKey('')->setConditionType('Plan')
            ->setParameters(['plan' => 'enfers'])->setBlocking(true)->setOrderIndex(0);

        $results = $this->simulationServiceWith(globalPreconditions: [$globalPlan])->simulate($action, $input);

        $this->assertTrue($results->isBlocked());
    }

    public function testARealWeaponWithSpellMalusFiresTheAntiSpellPrecondition(): void
    {
        $action = $this->meleeWith($this->condition('SpellCompute', ['actorRollType' => 'cc', 'targetRollType' => 'cc']));
        // gladius is a real datas weapon with spellMalus => AntiSpell blocks.
        $input = new SimulationInput(actorCaracs: ['cc' => 10], targetCaracs: ['cc' => 1], actorWeapon: 'gladius');

        $antiSpell = (new ActionConditionPrecondition())->setParentConditionType('SpellCompute')
            ->setPreconditionType('AntiSpell')->setOrderIndex(0);
        $gladius = ['gladius' => (object) ['type' => 'equipement', 'emplacement' => 'main1', 'subtype' => 'melee', 'name' => 'Gladius', 'spellMalus' => 1]];

        $results = $this->simulationServiceWith(conditionPreconditions: [$antiSpell], weapons: $gladius)->simulate($action, $input);

        $this->assertTrue($results->isBlocked());
        $this->assertStringContainsString('magie', $this->failureText($results));
    }

    public function testTheBerserkToggleShortCircuitsACompute(): void
    {
        $action = $this->meleeWith($this->condition('MeleeCompute', ['actorRollType' => 'cc', 'targetRollType' => 'cc']));
        $input = new SimulationInput(actorCaracs: ['cc' => 10], targetCaracs: ['cc' => 1], actorWeapon: 'melee', actorBerserk: true);

        $noBerserk = (new ActionConditionPrecondition())->setParentConditionType('MeleeCompute')
            ->setPreconditionType('NoBerserk')->setOrderIndex(0);

        $results = $this->simulationServiceWith(conditionPreconditions: [$noBerserk])->simulate($action, $input);

        $this->assertTrue($results->isBlocked());
    }

    public function testMeleeIsBlockedAtDistanceFourByRequiresDistance(): void
    {
        $action = $this->meleeWith($this->condition('RequiresDistance', ['max' => 1]));
        $input = new SimulationInput(
            actorCaracs: ['cc' => 10],
            targetCaracs: ['cc' => 10],
            distance: 4,
            actorWeapon: 'melee',
            targetWeapon: 'melee',
        );

        $results = ($this->simulationService())->simulate($action, $input);

        $this->assertTrue($results->isBlocked());
        $this->assertStringContainsString('loin', $this->failureText($results));
    }

    public function testMeleeIsBlockedWithoutAMeleeWeapon(): void
    {
        $action = $this->meleeWith($this->condition('RequiresWeaponType', ['type' => ['melee']]));
        $input = new SimulationInput(
            actorCaracs: ['cc' => 10],
            targetCaracs: ['cc' => 10],
            distance: 1,
            actorWeapon: 'tir',
            targetWeapon: 'melee',
        );

        $results = ($this->simulationService())->simulate($action, $input);

        $this->assertTrue($results->isBlocked());
        $this->assertStringContainsString('arme', $this->failureText($results));
    }

    public function testFullHitAppliesLifeLossDamageWithoutTouchingTheDatabase(): void
    {
        // BuffAction (not an AttackAction) carries the LifeLoss instruction without
        // the adrenaline/object-effect auto-instructions, isolating the damage path.
        $action = new BuffAction();
        $action->setName('drain');
        $action->setDisplayName('Drain');
        $action->addAutomaticOutcomeInstruction(
            $this->lifeLoss(['actorDamagesTrait' => 'cc', 'targetDamagesTrait' => 'agi'])
        );
        $input = new SimulationInput(
            actorCaracs: ['cc' => 20],
            targetCaracs: ['agi' => 2],
        );

        $results = ($this->simulationService())->simulate($action, $input);

        $this->assertFalse($results->isBlocked());
        $damage = 0;
        foreach ($results->getOutcomesResultsArray() as $outcome) {
            $damage += (int) $outcome->getTotalDamages();
        }
        $this->assertGreaterThan(0, $damage);
    }

    public function testTypeLevelInstructionsAreRunByTheExecutor(): void
    {
        // A LifeLoss configured on the action's TYPE applies even though the
        // action carries no automatic/DB instructions of its own — proving the
        // gate runs the resolved type-level instructions.
        $config = (new ActionTypeInstruction())
            ->setTypeKey('buff')
            ->setInstructionType('lifeloss')
            ->setOrderIndex(0)
            ->setParameters(['actorDamagesTrait' => 'cc', 'targetDamagesTrait' => 'agi']);

        $action = new BuffAction();
        $action->setName('drain');
        $action->setDisplayName('Drain');
        $input = new SimulationInput(actorCaracs: ['cc' => 20], targetCaracs: ['agi' => 2]);

        $results = ($this->simulationService([$config]))->simulate($action, $input);

        $damage = 0;
        foreach ($results->getOutcomesResultsArray() as $outcome) {
            $damage += (int) $outcome->getTotalDamages();
        }
        $this->assertGreaterThan(0, $damage);
    }

    public function testTypeLevelAndDynamicAutomaticInstructionsBothRun(): void
    {
        // A type-level LifeLoss (inherited) AND a dynamically-added automatic
        // LifeLoss (the shape a compute condition's miss-malus uses) must BOTH
        // apply — the executor runs both sources, it doesn't pick one. This guards
        // against the earlier gate that skipped the dynamic ones for attacks.
        $config = (new ActionTypeInstruction())
            ->setTypeKey('buff')
            ->setInstructionType('lifeloss')
            ->setOrderIndex(0)
            ->setParameters(['actorDamagesTrait' => 'cc', 'targetDamagesTrait' => 'agi']);

        $action = new BuffAction();
        $action->setName('drain');
        $action->setDisplayName('Drain');
        $action->addAutomaticOutcomeInstruction(
            $this->lifeLoss(['actorDamagesTrait' => 'cc', 'targetDamagesTrait' => 'agi'])
        );
        $input = new SimulationInput(actorCaracs: ['cc' => 20], targetCaracs: ['agi' => 2]);

        $results = ($this->simulationService([$config]))->simulate($action, $input);

        $damaging = 0;
        foreach ($results->getOutcomesResultsArray() as $o) {
            if ((int) $o->getTotalDamages() > 0) {
                $damaging++;
            }
        }
        $this->assertGreaterThanOrEqual(2, $damaging);
    }

    public function testAutomaticInstructionsGetterIsSafeOnAHydratedEntity(): void
    {
        // Doctrine builds entities without calling the constructor, leaving the
        // transient automaticOutcomeInstructions collection uninitialized — the
        // shape simulate() reads from a DB-loaded action in the admin panel.
        $action = (new \ReflectionClass(BuffAction::class))->newInstanceWithoutConstructor();

        $this->assertCount(0, $action->getAutomaticOutcomeInstructions());
    }

    public function testDistributionRestoresTheActionsAutomaticInstructions(): void
    {
        // cc 1 vs agi 30 → the Compute roll misses on virtually every run, and a
        // miss adds a MalusOutcomeInstruction to the shared action; the count must
        // still return to its baseline after the whole distribution.
        $action = $this->meleeWith(
            $this->condition('MeleeCompute', ['actorRollType' => 'cc', 'targetRollType' => 'agi'])
        );
        $baseline = $action->getAutomaticOutcomeInstructions()->count();
        $input = new SimulationInput(
            actorCaracs: ['cc' => 1, 'agi' => 1],
            targetCaracs: ['cc' => 30, 'agi' => 30],
            actorWeapon: 'melee',
            targetWeapon: 'melee',
        );

        ($this->simulationService())->distribution($action, $input, 100);

        $this->assertSame($baseline, $action->getAutomaticOutcomeInstructions()->count());
    }

    /**
     * @param array<string, mixed> $params
     */
    private function lifeLoss(array $params): LifeLossOutcomeInstruction
    {
        $instruction = new LifeLossOutcomeInstruction();
        $instruction->setParameters($params);

        return $instruction;
    }

    private function failureText(ActionResults $results): string
    {
        $text = '';
        foreach ($results->getConditionsResultsArray() as $conditionResult) {
            if (!$conditionResult->isSuccess()) {
                $text .= implode(' ', $conditionResult->getConditionFailureMessages());
            }
        }

        return $text;
    }
}
