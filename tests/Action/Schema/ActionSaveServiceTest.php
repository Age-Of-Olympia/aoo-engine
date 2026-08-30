<?php

namespace Tests\Action\Schema;

use App\Entity\Action;
use App\Entity\ActionCondition;
use App\Entity\ActionOutcome;
use App\Entity\OutcomeInstruction;
use App\Enum\OutcomeTarget;
use App\Service\Action\ActionSaveService;
use App\Service\OutcomeInstructionService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionSaveServiceTest extends TestCase
{
    private function setId(object $entity, string $declaringClass, int $id): void
    {
        $property = new \ReflectionProperty($declaringClass, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    private function entityManagerReturning(Action $action): EntityManagerInterface
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($action);

        return $em;
    }

    public function testRawEditorParametersAreSavedForASchemalessCondition(): void
    {
        $condition = new ActionCondition();
        $condition->setConditionType('RequiresTraitValue');
        $condition->setParameters(['pm' => 10]);
        $this->setId($condition, ActionCondition::class, 5);

        $action = $this->createMock(Action::class);
        $action->method('getConditions')->willReturn(new ArrayCollection([$condition]));
        $action->method('getOutcomes')->willReturn(new ArrayCollection());

        $service = new ActionSaveService(
            $this->entityManagerReturning($action),
            null,
            null,
            $this->createMock(OutcomeInstructionService::class),
        );

        $service->saveParameters(1, [], [], [5 => [['k' => 'a', 'v' => '1'], ['k' => 'pm', 'v' => '8']]], []);

        $this->assertSame(['a' => 1, 'pm' => 8], $condition->getParameters());
    }

    /**
     * Blocking is editable from the workbench. An unchecked box is absent from
     * the POST, and the form carries every condition, so absence means false.
     */
    public function testSavesTheBlockingFlagFromTheForm(): void
    {
        $becomesBlocking = new ActionCondition();
        $becomesBlocking->setConditionType('RequiresItem');
        $this->setId($becomesBlocking, ActionCondition::class, 5);

        $becomesFailing = (new ActionCondition())->setBlocking(true);
        $becomesFailing->setConditionType('MeleeCompute');
        $this->setId($becomesFailing, ActionCondition::class, 6);

        $action = $this->createMock(Action::class);
        $action->method('getConditions')->willReturn(new ArrayCollection([$becomesBlocking, $becomesFailing]));
        $action->method('getOutcomes')->willReturn(new ArrayCollection());

        $service = new ActionSaveService(
            $this->entityManagerReturning($action),
            null,
            null,
            $this->createMock(OutcomeInstructionService::class),
        );

        $service->saveParameters(1, [], [], [], [], [], [5 => '1']);

        $this->assertTrue($becomesBlocking->isBlocking(), 'la case cochée rend la condition bloquante');
        $this->assertFalse($becomesFailing->isBlocking(), 'la case décochée est absente du POST');
    }

    public function testSavesEachOutcomesApplyToValue(): void
    {
        $toSelf = new ActionOutcome();
        $this->setId($toSelf, ActionOutcome::class, 3);     // currently target -> set self
        $toBoth = (new ActionOutcome())->setApplyTo(OutcomeTarget::Self);
        $this->setId($toBoth, ActionOutcome::class, 4);      // currently self -> set both

        $action = $this->createMock(Action::class);
        $action->method('getConditions')->willReturn(new ArrayCollection());
        $action->method('getOutcomes')->willReturn(new ArrayCollection([$toSelf, $toBoth]));

        $service = new ActionSaveService($this->entityManagerReturning($action), null, null, $this->createMock(OutcomeInstructionService::class));

        $service->saveOutcomeTargets(1, [3 => 'self', 4 => 'both']);

        $this->assertSame(OutcomeTarget::Self, $toSelf->getApplyTo());
        $this->assertSame(OutcomeTarget::Both, $toBoth->getApplyTo());
    }

    public function testSaveOutcomeTargetsLeavesOutcomesAbsentFromThePayloadUntouched(): void
    {
        $outcome = (new ActionOutcome())->setApplyTo(OutcomeTarget::Self);
        $this->setId($outcome, ActionOutcome::class, 9);

        $action = $this->createMock(Action::class);
        $action->method('getConditions')->willReturn(new ArrayCollection());
        $action->method('getOutcomes')->willReturn(new ArrayCollection([$outcome]));

        $service = new ActionSaveService($this->entityManagerReturning($action), null, null, $this->createMock(OutcomeInstructionService::class));

        $service->saveOutcomeTargets(1, []);

        $this->assertSame(OutcomeTarget::Self, $outcome->getApplyTo());
    }

    public function testSaveOutcomeTargetsIgnoresAnUnknownPostedValue(): void
    {
        // A tampered/stale form value must not corrupt the stored enum.
        $outcome = (new ActionOutcome())->setApplyTo(OutcomeTarget::Both);
        $this->setId($outcome, ActionOutcome::class, 9);

        $action = $this->createMock(Action::class);
        $action->method('getConditions')->willReturn(new ArrayCollection());
        $action->method('getOutcomes')->willReturn(new ArrayCollection([$outcome]));

        $service = new ActionSaveService($this->entityManagerReturning($action), null, null, $this->createMock(OutcomeInstructionService::class));

        $service->saveOutcomeTargets(1, [9 => '1']);

        $this->assertSame(OutcomeTarget::Both, $outcome->getApplyTo());
    }

    private function entityManager(Action $action): EntityManagerInterface&MockObject
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($action);

        return $em;
    }

    public function testSavesTheActionDescriptionAndLevelTrimmed(): void
    {
        $action = new \App\Action\SearchAction();
        $action->setText('Ancien texte');
        $action->setLevel(2);
        $em = $this->entityManager($action);
        $em->expects($this->once())->method('flush');

        (new ActionSaveService($em, null, null, $this->createMock(OutcomeInstructionService::class)))
            ->saveDetails(1, '  Nouvelle description  ', 5);

        $this->assertSame('Nouvelle description', $action->getText());
        $this->assertSame(5, $action->getLevel());
    }

    public function testSaveDetailsIsANoOpWhenUnchanged(): void
    {
        $action = new \App\Action\SearchAction();
        $action->setText('Inchangé');
        $action->setLevel(3);
        $em = $this->entityManager($action);
        $em->expects($this->never())->method('flush');

        (new ActionSaveService($em, null, null, $this->createMock(OutcomeInstructionService::class)))
            ->saveDetails(1, 'Inchangé', 3);
    }

    public function testSaveDetailsHandlesLegacyNullColumnsWithoutThrowing(): void
    {
        // `text`/`level` left uninitialized (legacy NULL in NOT NULL columns): the
        // guard must treat them as unset and write the new values instead of throwing.
        $action = new \App\Action\SearchAction();
        $em = $this->entityManager($action);
        $em->expects($this->once())->method('flush');

        (new ActionSaveService($em, null, null, $this->createMock(OutcomeInstructionService::class)))
            ->saveDetails(1, 'Première description', 4);

        $this->assertSame('Première description', $action->getText());
        $this->assertSame(4, $action->getLevel());
    }

    public function testSavesTheActionRaceTrimmed(): void
    {
        $action = new \App\Action\SearchAction();
        $action->setRace('elfe');
        $em = $this->entityManager($action);
        $em->expects($this->once())->method('flush');

        (new ActionSaveService($em, null, null, $this->createMock(OutcomeInstructionService::class)))
            ->saveRace(1, '  nain  ');

        $this->assertSame('nain', $action->getRace());
    }

    public function testSaveRaceClearsTheRestrictionWithAnEmptyValue(): void
    {
        $action = new \App\Action\SearchAction();
        $action->setRace('nain');
        $em = $this->entityManager($action);
        $em->expects($this->once())->method('flush');

        (new ActionSaveService($em, null, null, $this->createMock(OutcomeInstructionService::class)))
            ->saveRace(1, '');

        $this->assertSame('', $action->getRace());
    }

    public function testSaveRaceIsANoOpWhenUnchanged(): void
    {
        $action = new \App\Action\SearchAction();
        $action->setRace('nain');
        $em = $this->entityManager($action);
        $em->expects($this->never())->method('flush');

        (new ActionSaveService($em, null, null, $this->createMock(OutcomeInstructionService::class)))
            ->saveRace(1, 'nain');
    }

    public function testSaveRaceTreatsAnUnsetRaceAsEmptyAndDoesNotFlushOnEmptyInput(): void
    {
        // A never-set race is null; saving "" (the "all races" choice) must
        // normalise null to "" for the comparison and skip the flush.
        $action = new \App\Action\SearchAction();
        $em = $this->entityManager($action);
        $em->expects($this->never())->method('flush');

        (new ActionSaveService($em, null, null, $this->createMock(OutcomeInstructionService::class)))
            ->saveRace(1, '');
    }

    public function testSaveIconColorStoresAPaletteTokenAndRejectsAnythingElse(): void
    {
        $action = new \App\Action\SearchAction();
        $service = new ActionSaveService($this->entityManager($action), null, null, $this->createMock(OutcomeInstructionService::class));

        $service->saveIconColor(1, 'rouge');
        $this->assertSame('rouge', $action->getIconColor());

        // Not in the palette -> resolves to default (null), never stored verbatim.
        $service->saveIconColor(1, '"><script>');
        $this->assertNull($action->getIconColor());
    }

}
