<?php

namespace Tests\Action\Combat;

use App\Action\PrayAction;
use App\Action\RunAction;
use App\Entity\Action;
use App\Entity\ActionTypePrecondition;
use App\Service\Action\ActionLogResolver;
use App\Service\Action\ActionXpResolver;
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
 * Pins the enfers gate now that it is a data-driven global precondition (was
 * hardcoded in BaseCondition). The action here has NO conditions of its own — so
 * it also proves the executor now gates zero-condition actions, which the old
 * code path silently let through.
 */
#[Group('action-combat')]
class EnfersPreconditionTest extends TestCase
{
    protected function setUp(): void
    {
        foreach (['CARACS' => ['cc' => 'CC', 'pv' => 'PV', 'pa' => 'PA'], 'ONE_DAY' => 86400, 'AUTO_FAIL' => false] as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }

    public function testActionWithoutOwnConditionsIsBlockedInTheEnfers(): void
    {
        $results = $this->execute($this->action(RunAction::class, 'courir'), 'enfers');

        $this->assertTrue($results->isBlocked());
    }

    public function testTheSameActionIsAllowedOutsideTheEnfers(): void
    {
        $results = $this->execute($this->action(RunAction::class, 'courir'), 'gaia');

        $this->assertFalse($results->isBlocked());
    }

    public function testPrierIsWhitelistedInsideTheEnfers(): void
    {
        $results = $this->execute($this->action(PrayAction::class, 'prier'), 'enfers');

        $this->assertFalse($results->isBlocked());
    }

    // -------------------------------------------------------------------------

    private function execute(Action $action, string $plan): \App\Action\ActionResults
    {
        $actor = new SimulatedPlayer(1, ['cc' => 10], ['pv' => 20, 'pa' => 10], $this->coords($plan), ['name' => 'Acteur']);
        $target = new SimulatedPlayer(2, ['cc' => 10], ['pv' => 20], $this->coords($plan), ['name' => 'Cible']);

        return (new ActionExecutorService(
            $action,
            $actor,
            $target,
            simulationMode: true,
            typeInstructionResolver: new ActionTypeInstructionResolver($this->em([])),
            preconditionResolver: new ActionTypePreconditionResolver($this->em([$this->globalPlan()])),
            conditionPreconditionResolver: new ConditionPreconditionResolver($this->em([])),
            logResolver: new ActionLogResolver($this->em([])),
            xpResolver: new ActionXpResolver($this->em([])),
        ))->executeAction();
    }

    private function coords(string $plan): object
    {
        return (object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => $plan];
    }

    private function action(string $class, string $name): Action
    {
        /** @var Action $action */
        $action = new $class();
        $action->setName($name);

        return $action;
    }

    private function globalPlan(): ActionTypePrecondition
    {
        return (new ActionTypePrecondition())
            ->setTypeKey('')
            ->setConditionType('Plan')
            ->setParameters(['plan' => 'enfers'])
            ->setOrderIndex(0)
            ->setBlocking(true);
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
