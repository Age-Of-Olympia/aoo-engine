<?php

namespace Tests\Action\Schema;

use App\Entity\ActionTypeLog;
use App\Service\Action\ActionTypeLogEditService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionTypeLogEditServiceTest extends TestCase
{
    private function em(?ActionTypeLog $existing): EntityManagerInterface&MockObject
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($existing);          // save() upserts via findOneBy
        $repo->method('findBy')->willReturn($existing === null ? [] : [$existing]); // templatesForType() resolves a chain
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return $em;
    }

    public function testTemplatesForTypeReturnsTheTypesOwnStoredValues(): void
    {
        $log = (new ActionTypeLog())->setTypeKey('attack')->setActorTemplate('{actor} a frappé.')->setTargetTemplate(null);

        $result = (new ActionTypeLogEditService($this->em($log)))->templatesForType('attack');

        $this->assertSame('{actor} a frappé.', $result['actor']);
        $this->assertNull($result['target']);
        $this->assertNull($result['inheritedFrom'], "the type's own row is not inherited");
    }

    public function testTemplatesForTypeFallsBackToAnAncestorAndNamesIt(): void
    {
        // A melee has no "melee" row; it inherits "attack" (melee -> attack).
        $log = (new ActionTypeLog())->setTypeKey('attack')->setActorTemplate('ATTACK')->setTargetTemplate(null);

        $result = (new ActionTypeLogEditService($this->em($log)))->templatesForType('melee');

        $this->assertSame('ATTACK', $result['actor']);
        $this->assertSame('attack', $result['inheritedFrom']);
        $this->assertNull($result['overriddenParent']);
    }

    public function testTemplatesForTypeReportsTheParentItOverridesWhenItHasItsOwnRow(): void
    {
        $own = (new ActionTypeLog())->setTypeKey('melee')->setActorTemplate('OWN')->setTargetTemplate(null);
        $parent = (new ActionTypeLog())->setTypeKey('attack')->setActorTemplate('ATTACK')->setTargetTemplate(null);

        $result = (new ActionTypeLogEditService($this->emWithRows([$own, $parent])))->templatesForType('melee');

        $this->assertSame('OWN', $result['actor']);
        $this->assertNull($result['inheritedFrom']);
        $this->assertSame('attack', $result['overriddenParent']);
    }

    /**
     * @param array<int, ActionTypeLog> $rows
     */
    private function emWithRows(array $rows): EntityManagerInterface&MockObject
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn($rows);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return $em;
    }

    public function testSaveCreatesARowForANewType(): void
    {
        $em = $this->em(null);
        $persisted = null;
        $em->expects($this->once())->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted = $entity;
        });
        $em->expects($this->once())->method('flush');

        (new ActionTypeLogEditService($em))->save('attack', '{actor} a attaqué {target}.', '');

        $this->assertInstanceOf(ActionTypeLog::class, $persisted);
        $this->assertSame('attack', $persisted->getTypeKey());
        $this->assertSame('{actor} a attaqué {target}.', $persisted->getActorTemplate());
        $this->assertNull($persisted->getTargetTemplate(), 'a blank template is stored as null');
    }

    public function testSaveUpdatesAnExistingRowWithoutPersisting(): void
    {
        $log = (new ActionTypeLog())->setTypeKey('heal')->setActorTemplate('old');
        $em = $this->em($log);
        $em->expects($this->never())->method('persist');
        $em->expects($this->once())->method('flush');

        (new ActionTypeLogEditService($em))->save('heal', 'new actor', 'new target');

        $this->assertSame('new actor', $log->getActorTemplate());
        $this->assertSame('new target', $log->getTargetTemplate());
    }

    public function testSaveRejectsAnUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ActionTypeLogEditService($this->em(null)))->save('not-a-type', 'x', 'y');
    }
}
