<?php

namespace Tests\Action\Schema;

use App\Entity\ActionTypeXp;
use App\Service\Action\ActionTypeXpEditService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionTypeXpEditServiceTest extends TestCase
{
    private function em(?ActionTypeXp $existing): EntityManagerInterface&MockObject
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($existing);          // save() upserts via findOneBy
        $repo->method('findBy')->willReturn($existing === null ? [] : [$existing]); // configForType() resolves a chain
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return $em;
    }

    public function testConfigForTypeDefaultsToFixedZeroWhenAbsent(): void
    {
        $config = (new ActionTypeXpEditService($this->em(null)))->configForType('pray');

        $this->assertSame('fixed', $config['mode']);
        $this->assertSame(0, $config['params']['actorSuccess']);
        $this->assertNull($config['inheritedFrom']);
    }

    public function testConfigForTypeMergesStoredParamsOverDefaults(): void
    {
        $row = (new ActionTypeXp())->setTypeKey('attack')->setMode('attack')->setParams(['base' => 9]);

        $config = (new ActionTypeXpEditService($this->em($row)))->configForType('attack');

        $this->assertSame('attack', $config['mode']);
        $this->assertSame(9, $config['params']['base']);
        $this->assertSame(2, $config['params']['min'], 'unset knobs fall back to the calculator default');
        $this->assertNull($config['inheritedFrom'], "the type's own row is not inherited");
    }

    public function testConfigForTypeInheritsFromTheClosestAncestor(): void
    {
        // A spell has no "spell" row; it inherits "attack" (spell -> technique -> attack).
        $row = (new ActionTypeXp())->setTypeKey('attack')->setMode('attack')->setParams(['base' => 5]);

        $config = (new ActionTypeXpEditService($this->em($row)))->configForType('spell');

        $this->assertSame('attack', $config['mode']);
        $this->assertSame('attack', $config['inheritedFrom']);
    }

    public function testSaveKeepsOnlyTheModesKnownParamsCoercedToInt(): void
    {
        $em = $this->em(null);
        $persisted = null;
        $em->expects($this->once())->method('persist')->willReturnCallback(function ($e) use (&$persisted) {
            $persisted = $e;
        });
        $em->expects($this->once())->method('flush');

        (new ActionTypeXpEditService($em))->save('attack', 'attack', ['base' => '7', 'min' => '3', 'bogus' => '99']);

        $this->assertSame('attack', $persisted->getMode());
        $this->assertSame(7, $persisted->getParams()['base']);
        $this->assertSame(3, $persisted->getParams()['min']);
        $this->assertArrayNotHasKey('bogus', $persisted->getParams());
    }

    public function testSaveRejectsAnUnknownMode(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ActionTypeXpEditService($this->em(null)))->save('attack', 'not-a-mode', []);
    }
}
