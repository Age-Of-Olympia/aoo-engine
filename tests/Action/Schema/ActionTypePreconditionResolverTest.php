<?php

namespace Tests\Action\Schema;

use App\Action\MeleeAction;
use App\Entity\ActionCondition;
use App\Entity\ActionTypePrecondition;
use App\Service\Action\ActionTypePreconditionResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionTypePreconditionResolverTest extends TestCase
{
    public function testResolvesGlobalThenAncestryOrderedBroadestFirst(): void
    {
        // Returned in a deliberately scrambled order; resolver must sort them.
        $configs = [
            $this->config('melee', 'NoBerserk', 0),
            $this->config('', 'Plan', 0),
            $this->config('attack', 'Dodge', 0),
        ];

        $resolved = $this->resolver($configs, MeleeAction::class)->resolve($this->melee());

        $this->assertSame(
            ['Plan', 'Dodge', 'NoBerserk'],
            array_map(static fn (ActionCondition $c): string => $c->getConditionType(), $resolved)
        );
    }

    public function testOrdersByOrderIndexWithinAType(): void
    {
        $configs = [
            $this->config('attack', 'Second', 1),
            $this->config('attack', 'First', 0),
        ];

        $resolved = $this->resolver($configs, MeleeAction::class)->resolve($this->melee());

        $this->assertSame(
            ['First', 'Second'],
            array_map(static fn (ActionCondition $c): string => $c->getConditionType(), $resolved)
        );
    }

    public function testBuildsRunnableConditionsCarryingParamsBlockingAndAction(): void
    {
        $action = $this->melee();
        $config = $this->config('', 'Plan', 0, ['plan' => 'enfers'], blocking: true);

        $resolved = $this->resolver([$config], MeleeAction::class)->resolve($action);

        $this->assertCount(1, $resolved);
        $condition = $resolved[0];
        $this->assertSame(['plan' => 'enfers'], $condition->getParameters());
        $this->assertTrue($condition->isBlocking());
        $this->assertSame($action, $condition->getAction());
    }

    public function testQueriesGlobalPlusTheActionsAncestryKeys(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->once())->method('findBy')
            ->with($this->callback(static function (array $criteria): bool {
                sort($criteria['typeKey']);
                return $criteria['typeKey'] === ['', 'attack', 'melee'];
            }))
            ->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        (new ActionTypePreconditionResolver($em))->resolve($this->melee());
    }

    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed>|null $params
     */
    private function config(string $typeKey, string $conditionType, int $order, ?array $params = null, bool $blocking = true): ActionTypePrecondition
    {
        return (new ActionTypePrecondition())
            ->setTypeKey($typeKey)
            ->setConditionType($conditionType)
            ->setOrderIndex($order)
            ->setParameters($params)
            ->setBlocking($blocking);
    }

    private function melee(): MeleeAction
    {
        $action = new MeleeAction();
        $action->setName('attaquer');

        return $action;
    }

    /**
     * @param array<int, ActionTypePrecondition> $configs
     */
    private function resolver(array $configs, string $actionClass): ActionTypePreconditionResolver
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn($configs);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return new ActionTypePreconditionResolver($em);
    }
}
