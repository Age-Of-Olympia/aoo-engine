<?php

namespace Tests\Action;

use App\Action\SearchAction;
use App\Entity\ActionCondition;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The executor's payment invariant: effects run before costs (rest and
 * status read the live pool), but an outcome that THROWS midway must
 * not leave its applied effects unpaid — the debit belongs to the
 * attempt, not to a clean finish.
 */
#[Group('action')]
class ExecutorCostsSurviveAThrowTest extends LegacyPlayerFixtureTestCase
{
    public function testAThrowingOutcomeStillPaysTheQueuedCosts(): void
    {
        $actor = $this->createRealPlayer('GmBoom');
        $actor->getCoords();
        $actor->get_caracs();
        $maxA = (int) $actor->caracs->a;

        // A detached action whose outcome phase explodes after the
        // conditions queued their costs.
        $action = new class extends SearchAction {
            public function getOnSuccessOutcomes(bool $success = true): Collection
            {
                throw new \RuntimeException('boom');
            }
        };
        $action->setName('gm_test_boom');
        $action->addCondition(
            (new ActionCondition())->setConditionType('RequiresTraitValue')->setParameters(['a' => 1])
        );

        try {
            (new ActionExecutorService($action, $actor, $actor))->executeAction();
            $this->fail('the stub outcome must throw');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame(
            $maxA - 1,
            PlayerFactory::legacy($actor->id)->getRemaining('a'),
            'whatever the outcome phase managed to apply is not free'
        );
    }
}
