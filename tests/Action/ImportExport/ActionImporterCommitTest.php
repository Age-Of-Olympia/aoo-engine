<?php

namespace Tests\Action\ImportExport;

use App\Action\MeleeAction;
use App\Action\OutcomeInstruction\LifeLossOutcomeInstruction;
use App\Entity\Action;
use App\Entity\ActionCondition;
use App\Entity\ActionOutcome;
use App\Entity\Race;
use App\Service\ImportExport\ActionExporter;
use App\Service\ImportExport\ActionImporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('import-export')]
class ActionImporterCommitTest extends TestCase
{
    /** @var array<int, object> */
    private array $persisted = [];

    public function testCreatesANewActionWithItsChildrenAndCommits(): void
    {
        $em = $this->entityManager(existing: null, knownRaces: ['Nain']);
        $em->expects($this->once())->method('beginTransaction');
        $em->expects($this->once())->method('flush');
        $em->expects($this->once())->method('commit');
        $em->expects($this->never())->method('rollback');

        $report = (new ActionImporter($em))->import([$this->fullPayload()]);

        $this->assertSame(['attaquer'], $report->created());
        $this->assertSame([], $report->rejected());

        $action = $this->persistedAction();
        $this->assertInstanceOf(MeleeAction::class, $action);
        $this->assertSame('attaquer', $action->getName());
        $this->assertSame('sword', $action->getIcon());
        $this->assertSame('Frappe', $action->getText());
        $this->assertSame(2, $action->getLevel());

        $this->assertCount(1, $action->getConditions());
        $condition = $action->getConditions()->first();
        $this->assertSame('Plan', $condition->getConditionType());
        $this->assertSame(['min' => 2], $condition->getParameters());

        $this->assertCount(1, $action->getOutcomes());
        $outcome = $action->getOutcomes()->first();
        $this->assertCount(1, $outcome->getInstructions());
        $instruction = $outcome->getInstructions()->first();
        $this->assertInstanceOf(LifeLossOutcomeInstruction::class, $instruction);
        $this->assertSame(['amount' => 5], $instruction->getParameters());

        $this->assertCount(1, $action->getRaces());
    }

    public function testUpdatesAnExistingActionAndReplacesItsChildrenWholesale(): void
    {
        $existing = $this->meleeNamed('attaquer');
        $stale = new ActionCondition();
        $stale->setConditionType('NoBerserk');
        $stale->setExecutionOrder(0);
        $existing->addCondition($stale);

        $em = $this->entityManager(existing: $existing, knownRaces: ['Nain']);
        $em->expects($this->once())->method('commit');
        $em->expects($this->never())->method('rollback');

        $report = (new ActionImporter($em))->import([$this->fullPayload()]);

        $this->assertSame(['attaquer'], $report->updated());
        $this->assertCount(1, $existing->getConditions());
        $this->assertSame('Plan', $existing->getConditions()->first()->getConditionType());
    }

    public function testRejectionRollsBackWithoutFlushingOrCommitting(): void
    {
        $em = $this->entityManager(existing: null, knownRaces: []);
        $em->expects($this->once())->method('beginTransaction');
        $em->expects($this->once())->method('rollback');
        $em->expects($this->never())->method('flush');
        $em->expects($this->never())->method('commit');

        $report = (new ActionImporter($em))->import([$this->fullPayload(['type' => 'bogus'])]);

        $this->assertTrue($report->hasRejections());
        $this->assertSame([], $this->persisted);
    }

    public function testStiTypeChangeOnAnExistingActionRollsBack(): void
    {
        $em = $this->entityManager(existing: $this->meleeNamed('attaquer'), knownRaces: []);
        $em->expects($this->once())->method('rollback');
        $em->expects($this->never())->method('commit');

        $report = (new ActionImporter($em))->import([$this->fullPayload(['type' => 'spell'])]);

        $this->assertStringContainsString('Changement de type', $report->rejected()[0]['reason']);
    }

    public function testRoundTripExportThenImportRebuildsTheActionFieldByField(): void
    {
        $source = $this->sampleAction();
        $exported = (new ActionExporter())->toArray($source);

        $em = $this->entityManager(existing: null, knownRaces: ['Nain', 'Elfe']);
        (new ActionImporter($em))->import([$exported]);

        $rebuilt = $this->persistedAction();
        $this->assertSame((new ActionExporter())->toArray($rebuilt), $exported);
    }

    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function fullPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'attaquer',
            'type' => 'melee',
            'icon' => 'sword',
            'displayName' => 'Attaquer',
            'text' => 'Frappe',
            'level' => 2,
            'race' => null,
            'category' => null,
            'cost' => null,
            'prerequisites' => null,
            'races' => ['Nain'],
            'conditions' => [
                ['type' => 'Plan', 'executionOrder' => 0, 'blocking' => false, 'parameters' => ['min' => 2]],
            ],
            'outcomes' => [
                [
                    'name' => null,
                    'onSuccess' => true,
                    'applyToSelf' => false,
                    'instructions' => [
                        ['type' => 'lifeloss', 'orderIndex' => 0, 'parameters' => ['amount' => 5]],
                    ],
                ],
            ],
        ], $overrides);
    }

    private function sampleAction(): MeleeAction
    {
        $action = $this->meleeNamed('attaquer');
        $action->setRace('hs');
        $action->setCategory('warrior');

        $condition = new ActionCondition();
        $condition->setConditionType('Plan');
        $condition->setExecutionOrder(0);
        $condition->setBlocking(true);
        $condition->setParameters(['min' => 2, 'type' => ['tir', 'jet']]);
        $action->addCondition($condition);

        $instruction = new LifeLossOutcomeInstruction();
        $instruction->setParameters(['actorDamagesTrait' => 'f', 'amount' => 3]);
        $instruction->setOrderIndex(0);
        $outcome = new ActionOutcome();
        $outcome->setName('hit');
        $outcome->setOnSuccess(true);
        $outcome->setApplyToSelf(false);
        $outcome->addInstruction($instruction);
        $action->addOutcome($outcome);

        $action->addRace($this->namedRace('Elfe'));
        $action->addRace($this->namedRace('Nain'));

        return $action;
    }

    private function meleeNamed(string $name): MeleeAction
    {
        $action = new MeleeAction();
        $action->setName($name);
        $action->setIcon('sword');
        $action->setDisplayName('Attaquer');
        $action->setText('Frappe');
        $action->setLevel(2);

        return $action;
    }

    private function namedRace(string $name): Race
    {
        $race = new Race();
        $race->setName($name);

        return $race;
    }

    private function persistedAction(): Action
    {
        foreach ($this->persisted as $entity) {
            if ($entity instanceof Action) {
                return $entity;
            }
        }

        $this->fail('No Action was persisted.');
    }

    /**
     * @param array<int, string> $knownRaces
     */
    private function entityManager(?Action $existing, array $knownRaces): EntityManagerInterface&MockObject
    {
        $this->persisted = [];

        $actionRepo = $this->createMock(EntityRepository::class);
        $actionRepo->method('findOneBy')->willReturn($existing);

        $raceRepo = $this->createMock(EntityRepository::class);
        $raceRepo->method('findOneBy')->willReturnCallback(
            fn (array $criteria): ?Race => in_array($criteria['name'] ?? null, $knownRaces, true)
                ? $this->namedRace((string) $criteria['name'])
                : null
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            static fn (string $class): EntityRepository => $class === Race::class ? $raceRepo : $actionRepo
        );
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        return $em;
    }
}
