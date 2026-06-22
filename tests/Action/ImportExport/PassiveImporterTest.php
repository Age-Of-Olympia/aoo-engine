<?php

namespace Tests\Action\ImportExport;

use App\Entity\ActionPassive;
use App\Service\ImportExport\PassiveExporter;
use App\Service\ImportExport\PassiveImporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('import-export')]
class PassiveImporterTest extends TestCase
{
    /** @var array<int, object> */
    private array $persisted = [];

    public function testPreviewClassifiesCreateAndUpdate(): void
    {
        $report = (new PassiveImporter($this->entityManager(null)))->preview([$this->payload()]);
        $this->assertSame(['oeil_de_lynx'], $report->created());

        $report = (new PassiveImporter($this->entityManager($this->existing())))->preview([$this->payload()]);
        $this->assertSame(['oeil_de_lynx'], $report->updated());
    }

    public function testPreviewRejectsMissingName(): void
    {
        $report = (new PassiveImporter($this->entityManager(null)))->preview([['type' => 'bonus']]);

        $this->assertStringContainsString('Nom manquant', $report->rejected()[0]['reason']);
    }

    public function testImportCreatesAPassiveAndCommits(): void
    {
        $em = $this->entityManager(null);
        $em->expects($this->once())->method('beginTransaction');
        $em->expects($this->once())->method('flush');
        $em->expects($this->once())->method('commit');
        $em->expects($this->never())->method('rollback');

        $report = (new PassiveImporter($em))->import([$this->payload()]);

        $this->assertSame(['oeil_de_lynx'], $report->created());
        $passive = $this->persistedPassive();
        $this->assertSame('oeil_de_lynx', $passive->getName());
        $this->assertSame('distance', $passive->getCategory());
        $this->assertSame(2.0, $passive->getValue());
        $this->assertSame(['ct', 'agi'], $passive->getTraits());
        $this->assertSame(['weapon' => 'bow'], $passive->getConditions());
    }

    public function testImportUpdatesAnExistingPassiveInPlace(): void
    {
        $existing = $this->existing();
        $em = $this->entityManager($existing);
        $em->expects($this->once())->method('commit');
        $em->expects($this->never())->method('rollback');

        $report = (new PassiveImporter($em))->import([$this->payload(['value' => 9.0])]);

        $this->assertSame(['oeil_de_lynx'], $report->updated());
        $this->assertSame(9.0, $existing->getValue());
        $this->assertSame([], $this->persisted); // existing entity is not re-persisted
    }

    public function testDuplicateNameRollsBack(): void
    {
        $em = $this->entityManager(null);
        $em->expects($this->once())->method('rollback');
        $em->expects($this->never())->method('commit');

        $report = (new PassiveImporter($em))->import([$this->payload(), $this->payload()]);

        $this->assertStringContainsString('Doublon', $report->rejected()[0]['reason']);
    }

    public function testRoundTripExportThenImportRebuildsThePassiveFieldByField(): void
    {
        $source = $this->existing();
        $exported = (new PassiveExporter())->toArray($source);

        (new PassiveImporter($this->entityManager(null)))->import([$exported]);

        $rebuilt = $this->persistedPassive();
        $this->assertSame((new PassiveExporter())->toArray($rebuilt), $exported);
    }

    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'oeil_de_lynx',
            'displayName' => 'Œil de lynx',
            'text' => '+2 CT',
            'type' => 'bonus',
            'carac' => 'ct',
            'category' => 'distance',
            'prerequisites' => 'arc',
            'race' => 'Elfe',
            'level' => 3,
            'value' => 2.0,
            'traits' => ['ct', 'agi'],
            'conditions' => ['weapon' => 'bow'],
        ], $overrides);
    }

    private function existing(): ActionPassive
    {
        $passive = new ActionPassive();
        $passive->setName('oeil_de_lynx');
        $passive->setDisplayName('Œil de lynx');
        $passive->setText('+2 CT');
        $passive->setType('bonus');
        $passive->setCarac('ct');
        $passive->setCategory('distance');
        $passive->setPrerequisites('arc');
        $passive->setRace('Elfe');
        $passive->setLevel(3);
        $passive->setValue(2.0);
        $passive->setTraits(['ct', 'agi']);
        $passive->setConditions(['weapon' => 'bow']);

        return $passive;
    }

    private function persistedPassive(): ActionPassive
    {
        foreach ($this->persisted as $entity) {
            if ($entity instanceof ActionPassive) {
                return $entity;
            }
        }

        $this->fail('No ActionPassive was persisted.');
    }

    private function entityManager(?ActionPassive $existing): EntityManagerInterface&MockObject
    {
        $this->persisted = [];

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        return $em;
    }
}
