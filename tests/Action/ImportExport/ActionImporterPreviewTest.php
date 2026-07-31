<?php

namespace Tests\Action\ImportExport;

use App\Action\MeleeAction;
use App\Action\OutcomeInstruction\LifeLossOutcomeInstruction;
use App\Entity\Action;
use App\Entity\ActionCondition;
use App\Entity\ActionOutcome;
use App\Entity\CharacterRace;
use App\Entity\Race;
use App\Service\ImportExport\ActionExporter;
use App\Service\ImportExport\ActionImporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('import-export')]
class ActionImporterPreviewTest extends TestCase
{
    public function testRoundTripExportThenPreviewClassifiesAsCreate(): void
    {
        $exported = (new ActionExporter())->toArray($this->sampleAction());

        $report = $this->importer()->preview([$exported]);

        $this->assertSame(['attaquer'], $report->created());
        $this->assertSame([], $report->updated());
        $this->assertSame([], $report->rejected());
        $this->assertSame([], $report->warnings());
    }

    public function testExistingActionOfSameTypeIsClassifiedAsUpdate(): void
    {
        $report = $this->importer(existing: $this->meleeNamed('attaquer'))
            ->preview([$this->payload()]);

        $this->assertSame(['attaquer'], $report->updated());
        $this->assertSame([], $report->created());
    }

    public function testStiTypeChangeOnAnExistingActionIsRejected(): void
    {
        $report = $this->importer(existing: $this->meleeNamed('attaquer'))
            ->preview([$this->payload(['type' => 'spell'])]);

        $this->assertSame([], $report->updated());
        $this->assertCount(1, $report->rejected());
        $this->assertStringContainsString('Changement de type', $report->rejected()[0]['reason']);
    }

    public function testUnknownActionTypeIsRejected(): void
    {
        $report = $this->importer()->preview([$this->payload(['type' => 'bogus'])]);

        $this->assertCount(1, $report->rejected());
        $this->assertStringContainsString("Type d'action inconnu", $report->rejected()[0]['reason']);
    }

    public function testUnknownConditionTypeRejectsTheWholeObject(): void
    {
        $report = $this->importer()->preview([
            $this->payload(['conditions' => [['type' => 'NotACondition', 'parameters' => []]]]),
        ]);

        $this->assertSame([], $report->created());
        $this->assertStringContainsString('Condition inconnue', $report->rejected()[0]['reason']);
    }

    public function testUnknownInstructionTypeRejectsTheWholeObject(): void
    {
        $report = $this->importer()->preview([
            $this->payload(['outcomes' => [['instructions' => [['type' => 'notreal', 'parameters' => []]]]]]),
        ]);

        $this->assertSame([], $report->created());
        $this->assertStringContainsString('Instruction inconnue', $report->rejected()[0]['reason']);
    }

    public function testUnknownRaceWarnsButStillImports(): void
    {
        $report = $this->importer(knownRaces: ['Nain'])
            ->preview([$this->payload(['races' => ['Nain', 'Atlante']])]);

        $this->assertSame(['attaquer'], $report->created());
        $this->assertCount(1, $report->warnings());
        $this->assertStringContainsString('Atlante', $report->warnings()[0]['message']);
    }

    public function testMissingNameIsRejectedWithTheRowIndex(): void
    {
        $report = $this->importer()->preview([['type' => 'melee']]);

        $this->assertSame('#0', $report->rejected()[0]['name']);
        $this->assertStringContainsString('Nom manquant', $report->rejected()[0]['reason']);
    }

    public function testAnIllegalParamKeyIsRejectedAtPreviewNotJustAtCommit(): void
    {
        $report = $this->importer()->preview([
            $this->payload(['conditions' => [['type' => 'Plan', 'parameters' => ['bad key' => 1]]]]),
        ]);

        $this->assertSame([], $report->created());
        $this->assertCount(1, $report->rejected());
        $this->assertStringContainsString('Clé de paramètre invalide', $report->rejected()[0]['reason']);
    }

    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'attaquer',
            'type' => 'melee',
            'conditions' => [],
            'outcomes' => [],
            'races' => [],
        ], $overrides);
    }

    private function sampleAction(): MeleeAction
    {
        $action = $this->meleeNamed('attaquer');

        $condition = new ActionCondition();
        $condition->setConditionType('Plan');
        $condition->setExecutionOrder(0);
        $condition->setParameters([]);
        $action->addCondition($condition);

        $instruction = new LifeLossOutcomeInstruction();
        $instruction->setParameters(['amount' => 3]);
        $outcome = new ActionOutcome();
        $outcome->setOnSuccess(true);
        $outcome->addInstruction($instruction);
        $action->addOutcome($outcome);

        return $action;
    }

    private function meleeNamed(string $name): MeleeAction
    {
        $action = new MeleeAction();
        $action->setName($name);
        $action->setIcon('sword');
        $action->setDisplayName('Attaquer');
        $action->setText('Frappe');
        $action->setLevel(1);

        return $action;
    }

    private function importer(?Action $existing = null, array $knownRaces = []): ActionImporter
    {
        return new ActionImporter($this->entityManager($existing, $knownRaces));
    }

    /**
     * @param array<int, string> $knownRaces
     */
    private function entityManager(?Action $existing, array $knownRaces): EntityManagerInterface
    {
        $actionRepo = $this->createMock(EntityRepository::class);
        $actionRepo->method('findOneBy')->willReturn($existing);

        $raceRepo = $this->createMock(EntityRepository::class);
        $raceRepo->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): ?Race => in_array($criteria['name'] ?? null, $knownRaces, true) ? new CharacterRace() : null
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            static fn (string $class): EntityRepository => $class === Race::class ? $raceRepo : $actionRepo
        );

        return $em;
    }
}
