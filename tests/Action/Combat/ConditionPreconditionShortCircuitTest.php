<?php

namespace Tests\Action\Combat;

use App\Action\MeleeAction;
use App\Entity\ActionCondition;
use App\Entity\ActionConditionPrecondition;
use App\Service\Action\ActionLogResolver;
use App\Service\Action\ActionTypeInstructionResolver;
use App\Service\Action\ActionTypePreconditionResolver;
use App\Service\Action\ConditionPreconditionResolver;
use App\Service\ActionExecutorService;
use App\Simulation\SimulatedPlayer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Characterises the short-circuit now that the *Compute conditions' preconditions
 * (Dodge/NoBerserk/Obstacle/AntiSpell) are data-driven and run by the executor: a
 * failed precondition must skip the condition's own check (the roll), exactly as
 * the old in-checkPreconditions short-circuit did. NoBerserk is the deterministic
 * trigger (a future antiBerserkTime).
 */
#[Group('action-combat')]
class ConditionPreconditionShortCircuitTest extends TestCase
{
    protected function setUp(): void
    {
        foreach (['CARACS' => ['cc' => 'CC', 'pv' => 'PV', 'pa' => 'PA', 'agi' => 'Agi'], 'ONE_DAY' => 86400, 'AUTO_FAIL' => false] as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }

    public function testAFailedPreconditionBlocksAndSkipsTheRoll(): void
    {
        $action = new MeleeAction();
        $action->setName('attaquer');
        $compute = new ActionCondition();
        $compute->setConditionType('MeleeCompute');
        $compute->setParameters(['actorRollType' => 'cc', 'targetRollType' => 'cc']);
        $compute->setExecutionOrder(0);
        $compute->setBlocking(false);
        $action->addCondition($compute);

        // Actor under an active anti-Berserk window => NoBerserk fails.
        $coords = (object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => 'gaia'];
        $weapon = (object) ['main1' => (object) ['data' => (object) ['name' => 'épée']]];
        $actor = new SimulatedPlayer(1, ['cc' => 10], ['pv' => 20, 'pa' => 10], $coords, ['name' => 'Acteur', 'antiBerserkTime' => time() + 3600], $weapon);
        $target = new SimulatedPlayer(2, ['cc' => 1], ['pv' => 20], $coords, ['name' => 'Cible'], $weapon);

        $results = (new ActionExecutorService(
            $action,
            $actor,
            $target,
            simulationMode: true,
            typeInstructionResolver: new ActionTypeInstructionResolver($this->em([])),
            preconditionResolver: new ActionTypePreconditionResolver($this->em([])),
            conditionPreconditionResolver: new ConditionPreconditionResolver($this->em([
                $this->precondition('MeleeCompute', 'NoBerserk'),
            ])),
            logResolver: new ActionLogResolver($this->em([])),
        ))->executeAction();

        // NoBerserk failing sets the parent condition blocking → the action blocks.
        $this->assertTrue($results->isBlocked());
        // The result is the NoBerserk failure, not a compute roll — proving the
        // roll was short-circuited.
        $this->assertStringContainsString('Berserk', $this->failureText($results));
        // The compute roll never ran, so it added no miss-malus to the action.
        $this->assertTrue($action->getAutomaticOutcomeInstructions()->isEmpty());
    }

    private function failureText(\App\Action\ActionResults $results): string
    {
        $text = '';
        foreach ($results->getConditionsResultsArray() as $conditionResult) {
            if (!$conditionResult->isSuccess()) {
                $text .= implode(' ', $conditionResult->getConditionFailureMessages());
            }
        }

        return $text;
    }

    private function precondition(string $parent, string $precondition): ActionConditionPrecondition
    {
        return (new ActionConditionPrecondition())
            ->setParentConditionType($parent)
            ->setPreconditionType($precondition)
            ->setOrderIndex(0);
    }

    /**
     * @param array<int, object> $rows
     */
    private function em(array $rows): EntityManagerInterface
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn($rows);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return $em;
    }
}
