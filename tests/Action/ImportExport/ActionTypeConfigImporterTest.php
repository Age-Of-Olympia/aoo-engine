<?php

namespace Tests\Action\ImportExport;

use App\Entity\ActionTypeXp;
use App\Service\ImportExport\ActionTypeConfigImporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('action-import-export')]
class ActionTypeConfigImporterTest extends TestCase
{
    private function em(): EntityManagerInterface&MockObject
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null); // nothing exists -> everything "created"
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return $em;
    }

    public function testPreviewClassifiesValidObjectsAsCreated(): void
    {
        $report = (new ActionTypeConfigImporter($this->em()))->preview([
            ['typeKey' => 'attack', 'xp' => ['mode' => 'attack', 'params' => ['base' => 5]], 'logs' => ['actorTemplate' => 'x', 'targetTemplate' => null]],
        ]);

        $this->assertSame(['attack'], $report->created());
        $this->assertFalse($report->hasRejections());
    }

    public function testPreviewRejectsUnknownTypeAndUnknownMode(): void
    {
        $report = (new ActionTypeConfigImporter($this->em()))->preview([
            ['typeKey' => 'not-a-type', 'xp' => null, 'logs' => null],
            ['typeKey' => 'attack', 'xp' => ['mode' => 'nope'], 'logs' => null],
        ]);

        $this->assertTrue($report->hasRejections());
        $names = array_column($report->rejected(), 'name');
        $this->assertContains('not-a-type', $names);
        $this->assertContains('attack', $names);
    }

    public function testImportRollsBackWholeBatchOnAnyRejection(): void
    {
        $em = $this->em();
        $em->expects($this->once())->method('rollback');
        $em->expects($this->never())->method('flush');
        $em->expects($this->never())->method('commit');

        $report = (new ActionTypeConfigImporter($em))->import([
            ['typeKey' => 'attack', 'xp' => ['mode' => 'attack'], 'logs' => null],
            ['typeKey' => 'bogus', 'xp' => null, 'logs' => null],
        ]);

        $this->assertTrue($report->hasRejections());
    }

    public function testImportUpsertsXpAndLogsInOneTransaction(): void
    {
        $em = $this->em();
        $persisted = [];
        $em->expects($this->once())->method('beginTransaction');
        $em->method('persist')->willReturnCallback(function ($e) use (&$persisted) {
            $persisted[] = $e;
        });
        $em->expects($this->once())->method('flush');
        $em->expects($this->once())->method('commit');

        $report = (new ActionTypeConfigImporter($em))->import([
            ['typeKey' => 'attack', 'xp' => ['mode' => 'attack', 'params' => ['base' => '9', 'bogus' => '1']], 'logs' => ['actorTemplate' => 'x', 'targetTemplate' => '']],
        ]);

        $this->assertFalse($report->hasRejections());
        $xp = array_values(array_filter($persisted, static fn ($e) => $e instanceof ActionTypeXp))[0];
        $this->assertSame(9, $xp->getParams()['base']);
        $this->assertArrayNotHasKey('bogus', $xp->getParams());
    }
}
