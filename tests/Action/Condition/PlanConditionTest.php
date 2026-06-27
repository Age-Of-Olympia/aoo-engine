<?php

namespace Tests\Action\Condition;

use App\Action\Condition\ConditionObject;
use App\Action\Condition\PlanCondition;
use App\Action\SearchAction;
use App\Entity\ActionCondition;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-condition')]
class PlanConditionTest extends TestCase
{
    private function actorOnPlan(string $plan): Player
    {
        $actor = $this->createMock(Player::class);
        $actor->coords = (object) ['plan' => $plan];

        return $actor;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function condition(string $actionName, array $params): ActionCondition
    {
        $action = new SearchAction();
        $action->setName($actionName);

        return (new ActionCondition())->setParameters($params)->setAction($action);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function isAllowed(string $plan, string $actionName, array $params): bool
    {
        return (new PlanCondition())
            ->check($this->actorOnPlan($plan), null, $this->condition($actionName, $params), new ConditionObject())
            ->isSuccess();
    }

    public function testBlocksANonExemptActionInTheForbiddenPlan(): void
    {
        $this->assertFalse($this->isAllowed('enfers', 'melee', ['plan' => 'enfers', 'allowed' => ['prier']]));
    }

    public function testAllowsAConfiguredExemptAction(): void
    {
        // The exemption list is data now — adding "melee" lets it through.
        $this->assertTrue($this->isAllowed('enfers', 'melee', ['plan' => 'enfers', 'allowed' => ['prier', 'melee']]));
    }

    public function testDefaultsToPrierWhenNoAllowedParam(): void
    {
        $this->assertTrue($this->isAllowed('enfers', 'prier', ['plan' => 'enfers']));
        $this->assertFalse($this->isAllowed('enfers', 'melee', ['plan' => 'enfers']));
    }

    public function testDoesNotBlockOnAnotherPlan(): void
    {
        $this->assertTrue($this->isAllowed('gaia', 'melee', ['plan' => 'enfers', 'allowed' => ['prier']]));
    }
}
