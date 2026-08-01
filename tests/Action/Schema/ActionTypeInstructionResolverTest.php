<?php

namespace Tests\Action\Schema;

use App\Action\MeleeAction;
use App\Factory\OutcomeInstructionFactory;
use App\Entity\ActionTypeInstruction;
use App\Service\Action\ActionTypeInstructionResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionTypeInstructionResolverTest extends TestCase
{
    /**
     * @param array<int, ActionTypeInstruction> $configs
     */
    private function resolverReturning(array $configs): ActionTypeInstructionResolver
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn($configs);

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return new ActionTypeInstructionResolver($em);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function config(string $typeKey, string $instructionType, int $orderIndex, array $params = []): ActionTypeInstruction
    {
        return (new ActionTypeInstruction())
            ->setTypeKey($typeKey)
            ->setInstructionType($instructionType)
            ->setOrderIndex($orderIndex)
            ->setParameters($params);
    }

    public function testBuildsExecutableInstructionsWithTheirParameters(): void
    {
        $resolver = $this->resolverReturning([
            $this->config('attack', 'applystatus', 0, ['adrenaline' => true]),
        ]);

        $instructions = $resolver->resolve(new MeleeAction());

        $this->assertCount(1, $instructions);
        $this->assertSame('applystatus', OutcomeInstructionFactory::typeOf($instructions[0]));
        $this->assertSame(['adrenaline' => true], $instructions[0]->getParameters());
    }

    public function testOrdersBroadestAncestorTypeFirst(): void
    {
        // MeleeAction inherits melee + attack; attack (parent) must run first.
        $resolver = $this->resolverReturning([
            $this->config('melee', 'healing', 0),
            $this->config('attack', 'applystatus', 0),
        ]);

        $types = array_map(
            static fn ($i) => OutcomeInstructionFactory::typeOf($i),
            $resolver->resolve(new MeleeAction()),
        );

        $this->assertSame(['applystatus', 'healing'], $types);
    }

    public function testSkipsUnknownInstructionTypes(): void
    {
        $resolver = $this->resolverReturning([
            $this->config('attack', 'definitelynotarealtype', 0),
            $this->config('attack', 'applystatus', 1),
        ]);

        $instructions = $resolver->resolve(new MeleeAction());

        $this->assertCount(1, $instructions);
        $this->assertSame('applystatus', OutcomeInstructionFactory::typeOf($instructions[0]));
    }

    public function testReturnsNothingWhenNoTypeInstructionsAreConfigured(): void
    {
        $this->assertSame([], $this->resolverReturning([])->resolve(new MeleeAction()));
    }
}
