<?php

namespace Tests\Action\Condition;

use App\Action\Condition\ConditionObject;
use App\Action\Condition\RequiresTraitValueCondition;
use App\Action\MeleeAction;
use App\Entity\ActionCondition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Action\Mock\PlayerMock;

#[Group('action-condition')]
class RequiresTraitValueConditionTest extends TestCase
{
    /** @param array<string, mixed> $params */
    private function condition(array $params): ActionCondition
    {
        $action = new MeleeAction();
        $action->setName('test');

        return (new ActionCondition())->setParameters($params)->setAction($action);
    }

    public function testACostAfterADrainedTraitIsStillChecked(): void
    {
        $actor = new PlayerMock(1, 'Actor');
        $actor->remaining = ['pm' => 3, 'a' => 0];

        $result = (new RequiresTraitValueCondition())
            ->check($actor, null, $this->condition(['remainingNullable' => 'pm', 'a' => 1]), new ConditionObject());

        $this->assertFalse($result->isSuccess(), 'the key after remainingNullable must be checked too');
    }

    public function testEveryCostIsPaidWhateverTheKeyOrder(): void
    {
        $actor = new PlayerMock(1, 'Actor');
        $actor->remaining = ['mvt' => 2, 'a' => 3];

        $lines = (new RequiresTraitValueCondition())
            ->applyCosts($actor, null, $this->condition(['remaining' => 'mvt', 'a' => 1]));

        $this->assertCount(2, $lines, 'both the drain and the flat cost are paid');
    }
}
