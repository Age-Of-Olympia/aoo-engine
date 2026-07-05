<?php

namespace Tests\Action\Schema;

use App\Action\Condition\AntiSpellCondition;
use App\Action\Condition\DodgeCondition;
use App\Action\Condition\NoBerserkCondition;
use App\Action\Condition\ObstacleCondition;
use App\Entity\ActionConditionPrecondition;
use App\Service\Action\ConditionPreconditionResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ConditionPreconditionResolverTest extends TestCase
{
    public function testResolvesPreconditionTypesToTheirHandlersInOrder(): void
    {
        $resolver = $this->resolver([
            $this->row('SpellCompute', 'Dodge', 0),
            $this->row('SpellCompute', 'NoBerserk', 1),
            $this->row('SpellCompute', 'Obstacle', 2),
            $this->row('SpellCompute', 'AntiSpell', 3),
        ]);

        $handlers = $resolver->resolve('SpellCompute');

        $this->assertInstanceOf(DodgeCondition::class, $handlers[0]);
        $this->assertInstanceOf(NoBerserkCondition::class, $handlers[1]);
        $this->assertInstanceOf(ObstacleCondition::class, $handlers[2]);
        $this->assertInstanceOf(AntiSpellCondition::class, $handlers[3]);
    }

    public function testQueriesByParentConditionTypeOrderedByOrderIndex(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->once())->method('findBy')
            ->with(['parentConditionType' => 'MeleeCompute'], ['orderIndex' => 'ASC'])
            ->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $this->assertSame([], (new ConditionPreconditionResolver($em))->resolve('MeleeCompute'));
    }

    public function testSkipsUnknownPreconditionTypes(): void
    {
        $resolver = $this->resolver([
            $this->row('MeleeCompute', 'Dodge', 0),
            $this->row('MeleeCompute', 'NotARealCondition', 1),
        ]);

        $handlers = $resolver->resolve('MeleeCompute');

        $this->assertCount(1, $handlers);
        $this->assertInstanceOf(DodgeCondition::class, $handlers[0]);
    }

    private function row(string $parent, string $precondition, int $order): ActionConditionPrecondition
    {
        return (new ActionConditionPrecondition())
            ->setParentConditionType($parent)
            ->setPreconditionType($precondition)
            ->setOrderIndex($order);
    }

    /**
     * @param array<int, ActionConditionPrecondition> $rows
     */
    private function resolver(array $rows): ConditionPreconditionResolver
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn($rows);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return new ConditionPreconditionResolver($em);
    }
}
