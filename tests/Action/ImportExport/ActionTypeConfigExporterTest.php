<?php

namespace Tests\Action\ImportExport;

use App\Entity\ActionTypeLog;
use App\Entity\ActionTypeXp;
use App\Service\ImportExport\ActionTypeConfigExporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-import-export')]
class ActionTypeConfigExporterTest extends TestCase
{
    /**
     * @param array<int, ActionTypeXp>  $xp
     * @param array<int, ActionTypeLog> $logs
     */
    private function em(array $xp, array $logs): EntityManagerInterface
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(function (string $class) use ($xp, $logs): EntityRepository {
            $repo = $this->createMock(EntityRepository::class);
            $repo->method('findAll')->willReturn($class === ActionTypeXp::class ? $xp : $logs);

            return $repo;
        });

        return $em;
    }

    public function testExportAllEmitsOnePayloadPerTypeWithXpAndLogs(): void
    {
        $xp = [(new ActionTypeXp())->setTypeKey('attack')->setMode('attack')->setParams(['base' => 5])];
        $logs = [(new ActionTypeLog())->setTypeKey('attack')->setActorTemplate('{actor} a attaqué {target}.')->setTargetTemplate(null)];

        $objects = (new ActionTypeConfigExporter($this->em($xp, $logs)))->exportAll();

        $this->assertCount(1, $objects);
        $this->assertSame('attack', $objects[0]['typeKey']);
        $this->assertSame('attack', $objects[0]['xp']['mode']);
        $this->assertSame(5, $objects[0]['xp']['params']['base']);
        $this->assertSame('{actor} a attaqué {target}.', $objects[0]['logs']['actorTemplate']);
        $this->assertNull($objects[0]['logs']['targetTemplate']);
    }

    public function testATypeWithOnlyOneKindHasTheOtherBlockNull(): void
    {
        $xp = [(new ActionTypeXp())->setTypeKey('buff')->setMode('fixed')->setParams(['actorSuccess' => 2])];
        $logs = [(new ActionTypeLog())->setTypeKey('rest')->setActorTemplate('Repos.')->setTargetTemplate(null)];

        $objects = (new ActionTypeConfigExporter($this->em($xp, $logs)))->exportAll();
        $byType = [];
        foreach ($objects as $object) {
            $byType[$object['typeKey']] = $object;
        }

        $this->assertNull($byType['buff']['logs'], 'buff has an XP row but no log row');
        $this->assertNull($byType['rest']['xp'], 'rest has a log row but no XP row');
        $this->assertSame('Repos.', $byType['rest']['logs']['actorTemplate']);
    }
}
