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

        $resolved = $resolver->resolve('SpellCompute');

        $this->assertInstanceOf(DodgeCondition::class, $resolved[0]->handler());
        $this->assertInstanceOf(NoBerserkCondition::class, $resolved[1]->handler());
        $this->assertInstanceOf(ObstacleCondition::class, $resolved[2]->handler());
        $this->assertInstanceOf(AntiSpellCondition::class, $resolved[3]->handler());
    }

    /**
     * Each row says what its failure costs. A dodge is a paid failure; an
     * obstacle refuses the action before any cost is taken.
     */
    public function testEachRowCarriesWhatItsFailureCosts(): void
    {
        $resolver = $this->resolver([
            $this->row('DistanceCompute', 'Dodge', 0),
            $this->row('DistanceCompute', 'Obstacle', 1, blocking: true),
        ]);

        $resolved = $resolver->resolve('DistanceCompute');

        $this->assertFalse($resolved[0]->isBlocking(), 'une esquive est un échec payé');
        $this->assertTrue($resolved[1]->isBlocking(), 'un obstacle refuse le tir');
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

        $resolved = $resolver->resolve('MeleeCompute');

        $this->assertCount(1, $resolved);
        $this->assertInstanceOf(DodgeCondition::class, $resolved[0]->handler());
    }

    private function row(string $parent, string $precondition, int $order, bool $blocking = false): ActionConditionPrecondition
    {
        return (new ActionConditionPrecondition())
            ->setParentConditionType($parent)
            ->setPreconditionType($precondition)
            ->setOrderIndex($order)
            ->setBlocking($blocking);
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
