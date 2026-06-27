<?php

namespace Tests\Action\Schema;

use App\Entity\Action;
use App\Entity\ActionCondition;
use App\Entity\ActionOutcome;
use App\Entity\OutcomeInstruction;
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

    public function testSavesEachOutcomesApplyToSelfFlag(): void
    {
        $toSelf = new ActionOutcome();
        $this->setId($toSelf, ActionOutcome::class, 3);     // currently target -> set self
        $toTarget = (new ActionOutcome())->setApplyToSelf(true);
        $this->setId($toTarget, ActionOutcome::class, 4);    // currently self -> set target

        $action = $this->createMock(Action::class);
        $action->method('getConditions')->willReturn(new ArrayCollection());
        $action->method('getOutcomes')->willReturn(new ArrayCollection([$toSelf, $toTarget]));

        $service = new ActionSaveService($this->entityManagerReturning($action), null, null, $this->createMock(OutcomeInstructionService::class));

        $service->saveOutcomeTargets(1, [3 => '1', 4 => '0']);

        $this->assertTrue($toSelf->getApplyToSelf());
        $this->assertFalse($toTarget->getApplyToSelf());
    }

    public function testSaveOutcomeTargetsLeavesOutcomesAbsentFromThePayloadUntouched(): void
    {
        $outcome = (new ActionOutcome())->setApplyToSelf(true);
        $this->setId($outcome, ActionOutcome::class, 9);

        $action = $this->createMock(Action::class);
        $action->method('getConditions')->willReturn(new ArrayCollection());
        $action->method('getOutcomes')->willReturn(new ArrayCollection([$outcome]));

        $service = new ActionSaveService($this->entityManagerReturning($action), null, null, $this->createMock(OutcomeInstructionService::class));

        $service->saveOutcomeTargets(1, []);

        $this->assertTrue($outcome->getApplyToSelf());
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
